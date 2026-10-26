<?php

namespace App\Domain\Payouts\Actions;

use App\Domain\Loans\LoanApplications;
use App\Domain\Loans\Models\Loan;
use App\Domain\Mgr\Models\MgrSlot;
use App\Domain\Payouts\Models\Payout;
use App\Domain\Welfare\Models\WelfareCase;

/**
 * When a payout is rejected or cancelled, put the thing it was paying for
 * back where it was so it can be raised again.
 */
final class ReopenPayable
{
    public static function for(Payout $payout): void
    {
        $payable = $payout->payable;

        if ($payable instanceof MgrSlot && $payable->payout_id === $payout->id) {
            $payable->forceFill(['status' => 'upcoming', 'payout_id' => null])->save();
        }

        if ($payable instanceof WelfareCase && $payable->payout_id === $payout->id) {
            $payable->forceFill(['status' => 'approved', 'payout_id' => null])->save();
        }

        if ($payable instanceof Loan) {
            LoanApplications::payoutClosed($payable, $payout);
        }
    }
}
