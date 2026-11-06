<?php

namespace App\Domain\Payouts\Models;

use App\Domain\Loans\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A change the treasurer makes to what a member actually receives: part of
 * a merry-go-round turn kept back for an old loan or for the group, or money
 * the group owes the member added on top.
 *
 * @property int $id
 * @property int $payout_id
 * @property string $kind loan|group|savings|reimburse
 * @property int $amount
 * @property int|null $loan_id
 * @property string $reason
 * @property int|null $added_by
 * @property-read Loan|null $loan
 * @property-read User|null $adder
 */
class PayoutAdjustment extends Model
{
    public const KINDS = [
        'loan' => 'Deduct: loan repayment',
        'group' => 'Deduct: kept by the group',
        'savings' => 'Deduct: into their savings',
        'reimburse' => 'Add: refund from the group fund',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    /** @return BelongsTo<Payout, $this> */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class)->withoutGlobalScopes();
    }

    /** @return BelongsTo<Loan, $this> */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class)->withoutGlobalScopes();
    }

    /** @return BelongsTo<User, $this> */
    public function adder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function isAddition(): bool
    {
        return $this->kind === 'reimburse';
    }

    /** What it does to the amount sent: negative for a deduction. */
    public function effect(): int
    {
        return $this->isAddition() ? $this->amount : -$this->amount;
    }
}
