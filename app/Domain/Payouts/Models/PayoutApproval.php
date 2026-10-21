<?php

namespace App\Domain\Payouts\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $payout_id
 * @property int $user_id
 * @property string $position_name
 * @property string $decision approved|rejected
 * @property string|null $comment
 */
class PayoutApproval extends Model
{
    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }
}
