<?php

namespace App\Domain\Payouts\Actions;

use App\Domain\Audit\Audit;
use App\Domain\Payouts\Models\Payout;
use App\Models\User;
use App\Support\RuleViolation;

final class CancelPayout
{
    public function handle(Payout $payout, User $user, string $reason): Payout
    {
        if (! in_array($payout->status, ['pending', 'approved'], true)) {
            throw new RuleViolation("Payout #{$payout->number} is {$payout->status} and cannot be cancelled.");
        }

        $payout->forceFill(['status' => 'cancelled'])->save();
        ReopenPayable::for($payout);

        Audit::log('payout.cancelled', "Payout #{$payout->number} cancelled by {$user->name}: {$reason}", $payout);

        return $payout;
    }
}
