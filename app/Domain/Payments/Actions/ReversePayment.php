<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Audit\Audit;
use App\Domain\Dues\Models\Due;
use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Ledger\Ledger;
use App\Domain\Ledger\Models\JournalEntry;
use App\Domain\Payments\Models\Payment;
use App\Support\RuleViolation;
use Illuminate\Support\Facades\DB;

/**
 * Undo a contribution or donation that was recorded by mistake: reverse its
 * ledger entry and take back what it paid off, newest dues first.
 */
final class ReversePayment
{
    public function __construct(private readonly Ledger $ledger) {}

    public function handle(Payment $payment, string $reason): Payment
    {
        if ($payment->journal_entry_id === null || $payment->status !== 'succeeded') {
            throw new RuleViolation('Only payments that have been posted to the ledger can be reversed.');
        }

        if (! in_array($payment->purpose, ['contribution', 'donation'], true)) {
            throw new RuleViolation('Only contributions and donations can be reversed here; correct sales, hires and project money from their own pages.');
        }

        return DB::transaction(function () use ($payment, $reason) {
            $entry = JournalEntry::withoutGlobalScopes()->with('lines.account')->findOrFail($payment->journal_entry_id);
            $this->ledger->reverse($entry, $reason);

            if ($payment->purpose === 'contribution' && $payment->member_id) {
                $prepaid = (int) $entry->lines
                    ->filter(fn ($l) => $l->account->code === ChartOfAccounts::PREPAYMENTS)
                    ->sum('credit');
                $remaining = $payment->amount - $prepaid;

                $dues = Due::withoutGlobalScopes()
                    ->where('member_id', $payment->member_id)
                    ->where('paid', '>', 0)
                    ->orderByDesc('due_on')
                    ->orderByDesc('id')
                    ->get();

                foreach ($dues as $due) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $undo = min($remaining, $due->paid);
                    $due->paid -= $undo;
                    $due->status = $due->status === 'waived' ? 'waived' : 'open';
                    $due->save();
                    $remaining -= $undo;
                }
            }

            $payment->forceFill(['status' => 'cancelled', 'failure_reason' => mb_substr("Reversed: {$reason}", 0, 255)])->save();
            Audit::log('payment.reversed', "Payment #{$payment->id} reversed: {$reason}", $payment, [], $payment->group_id);

            return $payment;
        });
    }
}
