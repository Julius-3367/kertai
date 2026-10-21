<?php

namespace App\Domain\Dues\Models;

use App\Domain\Groups\Concerns\BelongsToGroup;
use App\Domain\Members\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Anything a member owes the group: a monthly contribution, a welfare levy
 * or a fine. The breakdown is an ordered map of fund purpose => cents, so
 * partial payments fill the first fund before the next.
 *
 * @property int $id
 * @property int $group_id
 * @property int $member_id
 * @property string $type
 * @property string|null $period
 * @property string $description
 * @property int $amount
 * @property int $paid
 * @property array<string, int> $breakdown
 * @property CarbonImmutable $due_on
 * @property string $status
 * @property string|null $source_type
 * @property int|null $source_id
 * @property int|null $waived_by
 * @property string|null $waived_reason
 * @property CarbonImmutable|null $fined_at
 * @property-read Member $member
 */
class Due extends Model
{
    use BelongsToGroup;

    public const TYPES = ['contribution', 'levy', 'fine'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid' => 'integer',
            'breakdown' => 'array',
            'due_on' => 'immutable_date',
            'fined_at' => 'immutable_datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function scopeOverdue(Builder $query, ?CarbonImmutable $on = null): Builder
    {
        return $query->open()->whereDate('due_on', '<', ($on ?? CarbonImmutable::today())->toDateString());
    }

    public function outstanding(): int
    {
        return $this->status === 'open' ? max(0, $this->amount - $this->paid) : 0;
    }

    /**
     * Which funds receive the money when paid moves from $from to $to.
     *
     * @param  array<string, int>  $breakdown
     * @return array<string, int>
     */
    public static function split(array $breakdown, int $from, int $to): array
    {
        $out = [];
        $cursor = 0;

        foreach ($breakdown as $component => $amount) {
            $start = $cursor;
            $end = $cursor + (int) $amount;
            $cursor = $end;
            $overlap = max(0, min($to, $end) - max($from, $start));

            if ($overlap > 0) {
                $out[$component] = $overlap;
            }
        }

        return $out;
    }
}
