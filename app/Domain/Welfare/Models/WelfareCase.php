<?php

namespace App\Domain\Welfare\Models;

use App\Domain\Groups\Concerns\BelongsToGroup;
use App\Domain\Members\Models\Dependant;
use App\Domain\Members\Models\Member;
use App\Domain\Payouts\Models\Payout;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $member_id
 * @property int|null $dependant_id
 * @property string $beneficiary_name
 * @property string $relationship self|spouse|child|parent
 * @property string $event death|hospitalisation|other
 * @property string|null $description
 * @property CarbonImmutable $event_on
 * @property int $benefit_amount
 * @property int|null $levy_per_member
 * @property string $status open|approved|payout_requested|paid|rejected
 * @property int|null $payout_id
 * @property int|null $opened_by
 * @property-read Member $member
 */
class WelfareCase extends Model
{
    use BelongsToGroup;

    public const EVENTS = ['death' => 'Death', 'hospitalisation' => 'Hospitalisation', 'other' => 'Other'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'event_on' => 'immutable_date',
            'benefit_amount' => 'integer',
            'levy_per_member' => 'integer',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<Dependant, $this> */
    public function dependant(): BelongsTo
    {
        return $this->belongsTo(Dependant::class);
    }

    /** @return BelongsTo<Payout, $this> */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }
}
