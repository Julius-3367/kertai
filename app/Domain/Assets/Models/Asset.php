<?php

namespace App\Domain\Assets\Models;

use App\Domain\Groups\Concerns\BelongsToGroup;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Group property: tents, chairs and equipment. A hire rate of null means the
 * group has not set one yet; bookings then need a rate typed in.
 *
 * @property int $id
 * @property int $group_id
 * @property string $name
 * @property string $category
 * @property int $quantity
 * @property int|null $hire_rate per unit per day, cents
 * @property int|null $purchase_cost
 * @property CarbonImmutable|null $acquired_on
 * @property string $condition
 * @property string|null $notes
 */
class Asset extends Model
{
    use BelongsToGroup;

    public const CATEGORIES = ['tents' => 'Tents', 'chairs' => 'Chairs', 'equipment' => 'Equipment', 'other' => 'Other'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'hire_rate' => 'integer',
            'purchase_cost' => 'integer',
            'acquired_on' => 'immutable_date',
        ];
    }

    public function hires(): HasMany
    {
        return $this->hasMany(Hire::class)->latest('starts_on');
    }

    /**
     * Units free for the whole of a date range.
     */
    public function availableBetween(CarbonImmutable $from, CarbonImmutable $to, ?int $ignoreHireId = null): int
    {
        $booked = Hire::withoutGlobalScopes()
            ->where('asset_id', $this->id)
            ->whereIn('status', ['booked', 'out'])
            ->whereDate('starts_on', '<=', $to->toDateString())
            ->whereDate('ends_on', '>=', $from->toDateString())
            ->when($ignoreHireId, fn ($q) => $q->where('id', '!=', $ignoreHireId))
            ->sum('quantity');

        return max(0, $this->quantity - (int) $booked);
    }
}
