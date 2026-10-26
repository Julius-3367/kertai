<?php

namespace App\Domain\Loans\Models;

use App\Domain\Members\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A member standing behind part of someone else's loan. If the borrower
 * defaults, up to `amount` can be recovered from this member's savings.
 *
 * @property int $id
 * @property int $loan_id
 * @property int $member_id
 * @property int $amount
 * @property string $status pending|accepted|declined|released
 * @property Carbon|null $responded_at
 * @property-read Loan $loan
 * @property-read Member $member
 */
class LoanGuarantor extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'responded_at' => 'datetime',
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
}
