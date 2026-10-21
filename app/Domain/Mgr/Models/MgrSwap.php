<?php

namespace App\Domain\Mgr\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request to trade turns. The other member consents first, then the chair.
 *
 * @property int $id
 * @property int $mgr_cycle_id
 * @property int $requester_slot_id
 * @property int $target_slot_id
 * @property string $status pending_member|pending_chair|approved|declined
 * @property string|null $reason
 * @property-read MgrSlot $requesterSlot
 * @property-read MgrSlot $targetSlot
 */
class MgrSwap extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'member_responded_at' => 'datetime',
            'chair_responded_at' => 'datetime',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(MgrCycle::class, 'mgr_cycle_id');
    }

    public function requesterSlot(): BelongsTo
    {
        return $this->belongsTo(MgrSlot::class, 'requester_slot_id');
    }

    public function targetSlot(): BelongsTo
    {
        return $this->belongsTo(MgrSlot::class, 'target_slot_id');
    }
}
