<?php

namespace App\Domain\Mgr\Models;

use App\Domain\Members\Models\Member;
use App\Domain\Payouts\Models\Payout;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $mgr_cycle_id
 * @property int $member_id
 * @property int $position
 * @property CarbonImmutable $scheduled_on
 * @property string $status upcoming|requested|paid
 * @property int|null $payout_id
 * @property int $deferred_count
 * @property-read MgrCycle $cycle
 * @property-read Member $member
 */
class MgrSlot extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['scheduled_on' => 'immutable_date'];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(MgrCycle::class, 'mgr_cycle_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }
}
