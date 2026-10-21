<?php

namespace App\Domain\Mgr\Models;

use App\Domain\Groups\Concerns\BelongsToGroup;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One round of the merry-go-round: every participant receives the pot once.
 *
 * @property int $id
 * @property int $group_id
 * @property string $name
 * @property int $payout_amount
 * @property CarbonImmutable $starts_on
 * @property string $status draft|active|completed
 * @property string|null $ballot_seed
 * @property CarbonImmutable|null $balloted_at
 * @property int|null $balloted_by
 */
class MgrCycle extends Model
{
    use BelongsToGroup;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payout_amount' => 'integer',
            'starts_on' => 'immutable_date',
            'balloted_at' => 'immutable_datetime',
        ];
    }

    public function slots(): HasMany
    {
        return $this->hasMany(MgrSlot::class)->orderBy('position');
    }

    public function swaps(): HasMany
    {
        return $this->hasMany(MgrSwap::class)->latest();
    }

    public function dateForPosition(int $position): CarbonImmutable
    {
        return $this->starts_on->addMonthsNoOverflow($position - 1);
    }
}
