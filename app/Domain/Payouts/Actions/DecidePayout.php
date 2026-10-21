<?php

namespace App\Domain\Payouts\Actions;

use App\Domain\Access\Permission;
use App\Domain\Audit\Audit;
use App\Domain\Groups\Models\Group;
use App\Domain\Payouts\Models\Payout;
use App\Domain\Payouts\Models\PayoutApproval;
use App\Models\User;
use App\Support\RuleViolation;
use Illuminate\Support\Facades\DB;

/**
 * Records one official's decision. Any rejection rejects the payout; the
 * configured number of distinct approvals (two by default) approves it.
 */
final class DecidePayout
{
    public function handle(Payout $payout, User $user, string $decision, ?string $comment = null): Payout
    {
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw new RuleViolation('Decision must be approved or rejected.');
        }

        if (! $user->hasPermission(Permission::PayoutsApprove->value)) {
            throw new RuleViolation('Only the chair, secretary or treasurer can approve payouts.');
        }

        if ($payout->requested_by === $user->id) {
            throw new RuleViolation('You requested this payout, so another official must approve it.');
        }

        if ($payout->payee?->user_id !== null && $payout->payee->user_id === $user->id) {
            throw new RuleViolation('You cannot approve a payout to yourself.');
        }

        return DB::transaction(function () use ($payout, $user, $decision, $comment) {
            $payout = Payout::withoutGlobalScopes()->lockForUpdate()->findOrFail($payout->id);

            if ($payout->status !== 'pending') {
                throw new RuleViolation("Payout #{$payout->number} is already {$payout->status}.");
            }

            if ($payout->approvals()->where('user_id', $user->id)->exists()) {
                throw new RuleViolation('You have already decided on this payout.');
            }

            PayoutApproval::create([
                'payout_id' => $payout->id,
                'user_id' => $user->id,
                'position_name' => implode(', ', $user->member?->positionNames() ?? []) ?: 'Official',
                'decision' => $decision,
                'comment' => $comment,
            ]);

            $required = (int) Group::find($payout->group_id)?->settings()->get('payout_approvals_required', 2);

            if ($decision === 'rejected') {
                $payout->forceFill(['status' => 'rejected', 'rejected_at' => now()])->save();
                ReopenPayable::for($payout);
            } elseif ($payout->approvals()->where('decision', 'approved')->count() >= max(2, $required)) {
                $payout->forceFill(['status' => 'approved', 'approved_at' => now()])->save();
            }

            Audit::log("payout.{$decision}", "Payout #{$payout->number} {$decision} by {$user->name}", $payout, ['comment' => $comment], $payout->group_id);

            return $payout->refresh();
        });
    }
}
