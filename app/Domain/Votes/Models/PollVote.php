<?php

namespace App\Domain\Votes\Models;

use App\Domain\Members\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $poll_id
 * @property int $member_id
 * @property int $choice index into the poll's options
 * @property-read Member $member
 */
class PollVote extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['choice' => 'integer'];
    }

    /** @return BelongsTo<Poll, $this> */
    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
