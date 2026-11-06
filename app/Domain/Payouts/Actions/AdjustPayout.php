<?php

namespace App\Domain\Payouts\Actions;

use App\Domain\Access\Permission;
use App\Domain\Audit\Audit;
use App\Domain\Groups\Models\Group;
use App\Domain\Loans\LoanBook;
use App\Domain\Loans\Models\Loan;
use App\Domain\Notifications\Notifier;
use App\Domain\Payouts\Models\Payout;
use App\Domain\Payouts\Models\PayoutAdjustment;
use App\Models\User;
use App\Support\Money;
use App\Support\RuleViolation;
use Illuminate\Support\Facades\DB;

/**
 * Deductions and refunds on a payout to a member, entered by the treasurer
 * before it is paid. Nothing moves until the payout is marked paid.
 *
 * Changing them after anyone has approved sends the payout back for
 * approval, so the two approvals are always for the final figure. The
 * official who changed them may still approve: on a chair's own turn the
 * treasurer and secretary are the only two who can.
 */
final class AdjustPayout
{
    public function __construct(
        private readonly LoanBook $loans,
        private readonly Notifier $notifier,
    ) {}

    public function add(Payout $payout, User $by, string $kind, int $amount, string $reason, ?int $loanId = null): PayoutAdjustment
    {
        if (! array_key_exists($kind, PayoutAdjustment::KINDS)) {
            throw new RuleViolation('Choose what kind of deduction or refund this is.');
        }

        if ($amount <= 0) {
            throw new RuleViolation('Amount must be more than zero.');
        }

        return DB::transaction(function () use ($payout, $by, $kind, $amount, $reason, $loanId) {
            $payout = $this->lockOpen($payout);

            $loan = null;
            if ($kind === 'loan') {
                $loan = Loan::withoutGlobalScopes()->whereKey($loanId)->where('member_id', $payout->payee_member_id)->first()
                    ?? throw new RuleViolation("Choose one of {$payout->payee_name}'s loans.");

                $alreadyDeducted = (int) $payout->adjustments->where('loan_id', $loan->id)->sum('amount');
                $owed = $this->loans->balance($loan)['total'] - $alreadyDeducted;

                if ($loan->status !== 'active' || $amount > $owed) {
                    throw new RuleViolation('Only '.Money::format(max(0, $owed))." is owed on loan {$loan->reference()}.");
                }
            }

            $net = $payout->net() + ($kind === 'reimburse' ? $amount : -$amount);

            if ($net < 0) {
                throw new RuleViolation('Deductions cannot be more than the payout. '.Money::format($payout->net()).' is left to deduct from.');
            }

            $adjustment = $payout->adjustments()->create([
                'kind' => $kind,
                'amount' => $amount,
                'loan_id' => $loan?->id,
                'reason' => $reason,
                'added_by' => $by->id,
            ]);

            $label = $loan ? "loan {$loan->reference()}" : PayoutAdjustment::KINDS[$kind];
            $this->changed($payout, $by, ($kind === 'reimburse' ? 'Added ' : 'Deducted ').Money::format($amount)." ({$label}: {$reason})");

            return $adjustment;
        });
    }

    public function remove(Payout $payout, PayoutAdjustment $adjustment, User $by): void
    {
        DB::transaction(function () use ($payout, $adjustment, $by) {
            $payout = $this->lockOpen($payout);

            if ($adjustment->payout_id !== $payout->id) {
                throw new RuleViolation('That line is not on this payout.');
            }

            $adjustment->delete();
            $payout->unsetRelation('adjustments');
            $this->changed($payout, $by, 'Removed '.Money::format($adjustment->amount)." ({$adjustment->reason})");
        });
    }

    private function lockOpen(Payout $payout): Payout
    {
        $payout = Payout::withoutGlobalScopes()->with('adjustments')->lockForUpdate()->findOrFail($payout->id);

        if (! in_array($payout->status, ['pending', 'approved'], true)) {
            throw new RuleViolation("Payout #{$payout->number} is {$payout->status}, so it can no longer be changed.");
        }

        if ($payout->payee_member_id === null || $payout->type === 'loan') {
            throw new RuleViolation('Deductions and refunds are only for payouts to a member.');
        }

        return $payout;
    }

    private function changed(Payout $payout, User $by, string $what): void
    {
        $payout->unsetRelation('adjustments');
        $hadApprovals = $payout->approvals()->exists();

        $payout->approvals()->delete();
        $payout->forceFill(['status' => 'pending', 'approved_at' => null, 'adjusted_by' => $by->id])->save();

        Audit::log('payout.adjusted', "Payout #{$payout->number}: {$what}. Now ".Money::format($payout->net()).' to send.', $payout, [], $payout->group_id);

        if ($hadApprovals) {
            $group = Group::findOrFail($payout->group_id);
            $this->notifier->toHoldersOf($group, Permission::PayoutsApprove->value, 'payout_approval_needed', [
                'number' => $payout->number,
                'amount' => Money::format($payout->net()),
                'payee' => $payout->payee_name,
                'description' => "{$payout->description} (changed: {$what})",
                'link' => url("/payouts/{$payout->id}"),
            ], $by->id);
        }
    }
}
