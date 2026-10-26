<?php

namespace App\Domain\Loans\Models;

use App\Domain\Members\Models\Member;
use App\Domain\Payments\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line in a loan's own book. Charges are positive, money coming off the
 * loan is negative; summing a loan's lines gives what is still owed.
 *
 * @property int $id
 * @property int $loan_id
 * @property string $type opening|disbursement|interest|repayment|recovery|waiver|reversal
 * @property int $principal
 * @property int $interest
 * @property CarbonImmutable $occurred_on
 * @property string|null $period
 * @property string $description
 * @property int|null $member_id whose savings a recovery came from
 * @property int|null $payment_id
 * @property int|null $journal_entry_id
 * @property int|null $created_by
 * @property-read Loan $loan
 * @property-read Member|null $member
 * @property-read Payment|null $payment
 */
class LoanTransaction extends Model
{
    public const LABELS = [
        'opening' => 'Balance brought forward',
        'disbursement' => 'Loan paid out',
        'interest' => 'Interest',
        'repayment' => 'Repayment',
        'recovery' => 'Recovered from savings',
        'waiver' => 'Waived',
        'reversal' => 'Repayment reversed',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'principal' => 'integer',
            'interest' => 'integer',
            'occurred_on' => 'immutable_date',
        ];
    }

    /** @return BelongsTo<Loan, $this> */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function label(): string
    {
        return self::LABELS[$this->type] ?? ucfirst($this->type);
    }
}
