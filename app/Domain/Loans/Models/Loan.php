<?php

namespace App\Domain\Loans\Models;

use App\Domain\Groups\Concerns\BelongsToGroup;
use App\Domain\Members\Models\Member;
use App\Domain\Payouts\Models\Payout;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A loan to a member. "brought_forward" loans were given before the group
 * used Kertai and start active; "application" loans go through guarantors
 * and the usual two payout approvals before any money leaves.
 *
 * Interest terms are copied from the group rules when the loan is made, so
 * changing the group rate later only affects new loans.
 *
 * @property int $id
 * @property int $group_id
 * @property int $member_id
 * @property int $number
 * @property string $source brought_forward|application
 * @property string $status pending|submitted|active|cleared|written_off|rejected|cancelled
 * @property int $principal
 * @property string|null $purpose
 * @property int|null $term_months
 * @property int|null $instalment
 * @property string $interest_rate
 * @property string $interest_method flat|reducing
 * @property string $interest_period month|once
 * @property bool $interest_paused
 * @property string|null $interest_note
 * @property CarbonImmutable|null $next_interest_on
 * @property CarbonImmutable|null $issued_on
 * @property CarbonImmutable|null $due_on
 * @property CarbonImmutable|null $closed_on
 * @property int|null $payout_id
 * @property string|null $resolution_reference
 * @property string|null $notes
 * @property int|null $created_by
 * @property-read Member $member
 * @property-read Payout|null $payout
 */
class Loan extends Model
{
    use BelongsToGroup;

    /** Statuses where the borrower still owes, or may soon owe, money. */
    public const OPEN = ['pending', 'submitted', 'active'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'principal' => 'integer',
            'instalment' => 'integer',
            'term_months' => 'integer',
            'interest_rate' => 'decimal:2',
            'interest_paused' => 'boolean',
            'next_interest_on' => 'immutable_date',
            'issued_on' => 'immutable_date',
            'due_on' => 'immutable_date',
            'closed_on' => 'immutable_date',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<Payout, $this> */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<LoanTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(LoanTransaction::class);
    }

    /** @return HasMany<LoanGuarantor, $this> */
    public function guarantors(): HasMany
    {
        return $this->hasMany(LoanGuarantor::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN);
    }

    public function reference(): string
    {
        return 'L-'.str_pad((string) $this->number, 3, '0', STR_PAD_LEFT);
    }

    public function rate(): float
    {
        return (float) $this->interest_rate;
    }

    public function isOverdue(?CarbonImmutable $on = null): bool
    {
        return $this->status === 'active'
            && $this->due_on !== null
            && $this->due_on->lt(($on ?? CarbonImmutable::today())->startOfDay());
    }
}
