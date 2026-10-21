<?php

namespace App\Domain\Meetings\Models;

use App\Domain\Members\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $meeting_id
 * @property int $member_id
 * @property string $status present|late|apology|absent
 */
class MeetingAttendance extends Model
{
    public const STATUSES = ['present', 'late', 'apology', 'absent'];

    protected $guarded = ['id'];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
