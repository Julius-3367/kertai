<?php

namespace App\Domain\Assets\Models;

use App\Domain\Groups\Concerns\BelongsToGroup;
use App\Domain\Members\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $asset_id
 * @property int|null $member_id
 * @property string $hirer_name
 * @property string|null $hirer_phone
 * @property int $quantity
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property int $rate
 * @property int $total
 * @property int $paid
 * @property string $status booked|out|returned|cancelled
 * @property string|null $notes
 * @property-read Asset $asset
 */
class Hire extends Model
{
    use BelongsToGroup;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'rate' => 'integer',
            'total' => 'integer',
            'paid' => 'integer',
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withoutGlobalScopes();
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function days(): int
    {
        return (int) $this->starts_on->diffInDays($this->ends_on) + 1;
    }

    public function balance(): int
    {
        return max(0, $this->total - $this->paid);
    }

    public function recordPayment(int $amount): void
    {
        $this->forceFill(['paid' => $this->paid + $amount])->save();
    }
}
