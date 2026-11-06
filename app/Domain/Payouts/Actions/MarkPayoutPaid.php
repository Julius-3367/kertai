<?php

namespace App\Domain\Payouts\Actions;

use App\Domain\Audit\Audit;
use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Ledger\Ledger;
use App\Domain\Ledger\Models\Account;
use App\Domain\Loans\LoanBook;
use App\Domain\Loans\Models\Loan;
use App\Domain\Members\Models\Member;
use App\Domain\Mgr\Models\MgrSlot;
use App\Domain\Notifications\Notifier;
use App\Domain\Payouts\Models\Payout;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectEntry;
use App\Domain\Welfare\Models\WelfareCase;
use App\Models\User;
use App\Support\Money;
use App\Support\RuleViolation;
use Illuminate\Support\Facades\DB;

/**
 * The treasurer confirms the money has left (M-Pesa, bank or cash) and the
 * ledger moves: the fund is debited and the asset it left from credited.
 */
final class MarkPayoutPaid
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly ChartOfAccounts $accounts,
        private readonly RequestPayout $requests,
        private readonly Notifier $notifier,
        private readonly LoanBook $loans,
    ) {}

    public function handle(Payout $payout, User $user, string $method, string $reference): Payout
    {
        if (! array_key_exists($method, Payout::METHODS)) {
            throw new RuleViolation('Choose how the money was paid.');
        }

        $paid = DB::transaction(function () use ($payout, $user, $method, $reference) {
            $payout = Payout::withoutGlobalScopes()->with('account', 'payee', 'adjustments.loan')->lockForUpdate()->findOrFail($payout->id);

            if ($payout->status !== 'approved') {
                throw new RuleViolation("Payout #{$payout->number} is {$payout->status}; only approved payouts can be paid.");
            }

            $this->requests->ensureFunds($payout->account, $payout->amount, $payout->payee);

            $entry = $this->ledger->post(
                "Payout #{$payout->number}: {$payout->description} to {$payout->payee_name}",
                [
                    [
                        'account' => $payout->account,
                        'debit' => $payout->amount,
                        'member_id' => $payout->payee_member_id,
                        'memo' => $payout->typeLabel(),
                    ],
                    ...$this->adjustmentLines($payout),
                    [
                        'account' => $this->accounts->forChannel($payout->group_id, $method),
                        'credit' => $payout->net(),
                        'memo' => $reference,
                    ],
                ],
                $payout,
                reference: $reference,
            );

            foreach ($payout->adjustments->where('kind', 'loan') as $adjustment) {
                $this->loans->repayFromPayout($adjustment->loan ?? throw new RuleViolation('A loan on this payout no longer exists.'), $adjustment->amount, $payout, $entry->id);
            }

            $payout->forceFill([
                'status' => 'paid',
                'paid_at' => now(),
                'paid_method' => $method,
                'paid_reference' => $reference,
                'paid_by' => $user->id,
                'journal_entry_id' => $entry->id,
            ])->save();

            $this->settle($payout);

            return $payout;
        });

        Audit::log('payout.paid', "Payout #{$paid->number} of ".Money::format($paid->amount)." paid by {$method} ({$reference})", $paid, [], $paid->group_id);

        if ($paid->payee) {
            $deducted = $paid->amount - $paid->net();
            $this->notifier->toMember($paid->payee, 'payout_paid', [
                'amount' => Money::format($paid->net()),
                'description' => $paid->adjustments->isEmpty() ? $paid->description : $paid->description.' ('.Money::format($paid->amount).($deducted >= 0 ? ' less '.Money::format($deducted) : ' plus '.Money::format(-$deducted)).': '.$paid->adjustments->pluck('reason')->implode(', ').')',
                'reference' => $reference,
            ]);
        }

        return $paid->refresh();
    }

    /**
     * Ledger lines for the treasurer's deductions and refunds. A deduction is
     * money that stays in the group (in a loan, the group fund or the member's
     * savings) instead of being sent; a refund is extra sent from the group fund.
     *
     * @return list<array{account: Account, debit?: int, credit?: int, member_id?: int|null, memo?: string|null}>
     */
    private function adjustmentLines(Payout $payout): array
    {
        if ($payout->net() < 0) {
            throw new RuleViolation('Deductions are more than the payout.');
        }

        $refunds = (int) $payout->adjustments->where('kind', 'reimburse')->sum('amount');
        $groupFund = $this->accounts->get($payout->group_id, ChartOfAccounts::GROUP_FUND);

        if ($refunds > 0) {
            $this->requests->ensureFunds($groupFund, $refunds);
        }

        foreach ($payout->adjustments->where('kind', 'loan')->groupBy('loan_id') as $forLoan) {
            $this->loans->checkPayoutDeduction($forLoan->first()->loan ?? throw new RuleViolation('A loan on this payout no longer exists.'), (int) $forLoan->sum('amount'));
        }

        $lines = [];

        foreach ($payout->adjustments as $a) {
            $lines[] = match ($a->kind) {
                'loan' => [
                    'account' => $this->accounts->get($payout->group_id, ChartOfAccounts::LOANS),
                    'credit' => $a->amount,
                    'member_id' => $a->loan?->member_id,
                    'memo' => 'Loan '.$a->loan?->reference()." repaid from payout #{$payout->number}",
                ],
                'group' => ['account' => $groupFund, 'credit' => $a->amount, 'memo' => "Kept from payout #{$payout->number}: {$a->reason}"],
                'savings' => [
                    'account' => $this->accounts->get($payout->group_id, ChartOfAccounts::SAVINGS),
                    'credit' => $a->amount,
                    'member_id' => $payout->payee_member_id,
                    'memo' => "Saved from payout #{$payout->number}: {$a->reason}",
                ],
                default => ['account' => $groupFund, 'debit' => $a->amount, 'member_id' => $payout->payee_member_id, 'memo' => "Refund: {$a->reason}"],
            };
        }

        return $lines;
    }

    /**
     * Close whatever the payout was for.
     */
    private function settle(Payout $payout): void
    {
        $payable = $payout->payable;

        match (true) {
            $payable instanceof MgrSlot => $payable->forceFill(['status' => 'paid'])->save(),
            $payable instanceof WelfareCase => $payable->forceFill(['status' => 'paid'])->save(),
            $payable instanceof Project => ProjectEntry::create([
                'project_id' => $payable->id,
                'kind' => 'expense',
                'amount' => $payout->amount,
                'entry_on' => now()->toDateString(),
                'description' => $payout->description,
                'payout_id' => $payout->id,
            ]),
            $payable instanceof Member && $payout->type === 'exit_refund' => $this->closeMembership($payable),
            $payable instanceof Loan => $this->loans->disburse($payable, $payout),
            default => null,
        };
    }

    private function closeMembership(Member $member): void
    {
        $member->forceFill([
            'status' => $member->status === 'deceased' ? 'deceased' : 'exited',
            'exited_on' => $member->exited_on ?? now()->toDateString(),
        ])->save();

        $member->terms()->whereNull('ends_on')->update(['ends_on' => now()->toDateString(), 'ended_reason' => 'Left the group']);
    }
}
