<?php

namespace App\Domain\Projects\Models;

use App\Domain\Members\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $project_id
 * @property int $member_id
 * @property int $contributed
 * @property CarbonImmutable $joined_on
 * @property-read Member $member
 */
class ProjectParticipant extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['contributed' => 'integer', 'joined_on' => 'immutable_date'];
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
}
