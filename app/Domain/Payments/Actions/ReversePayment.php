<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Audit\Audit;
use App\Domain\Dues\Models\Due;
use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Ledger\Ledger;
use App\Domain\Ledger\Models\JournalEntry;
use App\Domain\Loans\LoanBook;
use App\Domain\Members\Models\Member;
use App\Domain\Payments\Models\Payment;
use App\Support\RuleViolation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Undo a contribution, loan repayment or donation that was recorded by
 * mistake: reverse its ledger entry and take back what it paid off, newest
 * dues first. Or keep the money and change what it was for, e.g. a paybill
 * payment that was matched as contributions but was meant for a loan.
 */
final class ReversePayment
{
    private const UNDOABLE = ['contribution', 'loan', 'donation'];

    public function __construct(
        private readonly Ledger $ledger,
        private readonly LoanBook $loans,
        private readonly AllocatePayment $allocate,
    ) {}

    public function handle(Payment $payment, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $reason) {
            $this->undo($payment, $reason);

            $payment->forceFill(['status' => 'cancelled', 'failure_reason' => mb_substr("Reversed: {$reason}", 0, 255)])->save();
            Audit::log('payment.reversed', "Payment #{$payment->id} reversed: {$reason}", $payment, [], $payment->group_id);

            return $payment;
        });
    }

    public function reallocate(Payment $payment, string $purpose, ?Member $member, ?Model $target, string $reason): Payment
    {
        if (! in_array($purpose, self::UNDOABLE, true)) {
            throw new RuleViolation('A payment can be moved to contributions, a loan or a donation.');
        }

        $from = $payment->purpose;

        DB::transaction(function () use ($payment, $purpose, $member, $target, $reason) {
            $this->undo($payment, $reason);

            $payment->forceFill([
                'purpose' => $purpose,
                'member_id' => $member?->id,
                'payable_type' => $target?->getMorphClass(),
                'payable_id' => $target?->getKey(),
                'allocated_at' => null,
                'journal_entry_id' => null,
            ])->save();
        });

        $this->allocate->handle($payment, notify: false);
        Audit::log('payment.reallocated', "Payment #{$payment->id} moved from {$from} to {$purpose}: {$reason}", $payment, [], $payment->group_id);

        return $payment->refresh();
    }

    private function undo(Payment $payment, string $reason): void
    {
        if ($payment->journal_entry_id === null || $payment->status !== 'succeeded') {
            throw new RuleViolation('Only payments that have been posted to the ledger can be reversed.');
        }

        if (! in_array($payment->purpose, self::UNDOABLE, true)) {
            throw new RuleViolation('Only contributions, loan repayments and donations can be reversed here; correct sales, hires and project money from their own pages.');
        }

        $entry = JournalEntry::withoutGlobalScopes()->with('lines.account')->findOrFail($payment->journal_entry_id);
        $this->ledger->reverse($entry, $reason);

        if ($payment->purpose === 'loan') {
            $this->loans->reverseRepayment($payment, $reason);
        }

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
    }
}
