<?php

namespace App\Domain\Projects\Models;

use App\Domain\Members\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A piece of land a project farms: owned by the group, leased, or lent by
 * a member.
 *
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property string|null $location
 * @property string $size
 * @property string $size_unit acres|hectares
 * @property string $tenure owned|leased|member|free
 * @property int|null $member_id
 * @property string|null $owner_name
 * @property string|null $owner_phone
 * @property int|null $lease_amount
 * @property string|null $lease_per season|year|month|once
 * @property CarbonImmutable|null $lease_starts_on
 * @property CarbonImmutable|null $lease_ends_on
 * @property string|null $notes
 * @property string $status active|released
 */
class ProjectPlot extends Model
{
    public const TENURES = [
        'owned' => 'Owned by the group',
        'leased' => 'Leased',
        'member' => 'Lent by a member',
        'free' => 'Free use',
    ];

    public const LEASE_PER = [
        'season' => 'per season',
        'year' => 'per year',
        'month' => 'per month',
        'once' => 'for the whole period',
    ];

    private const ACRES_PER_HECTARE = 2.47105;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'size' => 'decimal:2',
            'lease_amount' => 'integer',
            'lease_starts_on' => 'immutable_date',
            'lease_ends_on' => 'immutable_date',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function acres(): float
    {
        $size = (float) $this->size;

        return $this->size_unit === 'hectares' ? round($size * self::ACRES_PER_HECTARE, 2) : $size;
    }

    /** A lease that runs out within two months, so there is time to renew. */
    public function leaseEndingSoon(): bool
    {
        return $this->tenure === 'leased'
            && $this->status === 'active'
            && $this->lease_ends_on !== null
            && $this->lease_ends_on->lte(CarbonImmutable::today()->addMonths(2));
    }
}
