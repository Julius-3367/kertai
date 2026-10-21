<?php

namespace App\Domain\Payouts\Actions;

use App\Domain\Audit\Audit;
use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Ledger\Ledger;
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
    ) {}

    public function handle(Payout $payout, User $user, string $method, string $reference): Payout
    {
        if (! array_key_exists($method, Payout::METHODS)) {
            throw new RuleViolation('Choose how the money was paid.');
        }

        $paid = DB::transaction(function () use ($payout, $user, $method, $reference) {
            $payout = Payout::withoutGlobalScopes()->with('account', 'payee')->lockForUpdate()->findOrFail($payout->id);

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
                    [
                        'account' => $this->accounts->forChannel($payout->group_id, $method),
                        'credit' => $payout->amount,
                        'memo' => $reference,
                    ],
                ],
                $payout,
                reference: $reference,
            );

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
            $this->notifier->toMember($paid->payee, 'payout_paid', [
                'amount' => Money::format($paid->amount),
                'description' => $paid->description,
                'reference' => $reference,
            ]);
        }

        return $paid->refresh();
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
