<?php

namespace App\Domain\Access\Models;

use App\Domain\Groups\Concerns\BelongsToGroup;
use App\Domain\Members\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member holding a position for a period. Acting terms are ordinary terms
 * with an end date, so they lapse on their own.
 *
 * @property int $id
 * @property int $position_id
 * @property int $member_id
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable|null $ends_on
 * @property bool $is_acting
 * @property string|null $resolution_reference
 * @property string|null $ended_reason
 */
class PositionTerm extends Model
{
    use BelongsToGroup;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'is_acting' => 'boolean',
        ];
    }

    /** @return BelongsTo<Position, $this> */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeCurrent(Builder $query, ?CarbonImmutable $on = null): Builder
    {
        $date = ($on ?? CarbonImmutable::today())->toDateString();

        return $query->whereDate('starts_on', '<=', $date)
            ->where(fn (Builder $q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $date));
    }

    public function isCurrent(): bool
    {
        $today = CarbonImmutable::today();

        return $this->starts_on->lte($today) && ($this->ends_on === null || $this->ends_on->gte($today));
    }
}
