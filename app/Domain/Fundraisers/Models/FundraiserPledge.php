<?php

namespace App\Domain\Fundraisers\Models;

use App\Domain\Members\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $fundraiser_id
 * @property int|null $member_id
 * @property string $name
 * @property string|null $phone
 * @property int $amount
 * @property string|null $note
 */
class FundraiserPledge extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    /** @return BelongsTo<Fundraiser, $this> */
    public function fundraiser(): BelongsTo
    {
        return $this->belongsTo(Fundraiser::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
