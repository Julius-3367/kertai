<?php

namespace App\Domain\Members\Models;

use App\Domain\Groups\Concerns\BelongsToGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A member's request to change their own register details. Nothing changes
 * until someone who manages members approves it.
 *
 * @property int $id
 * @property int $group_id
 * @property int $member_id
 * @property array<string, string|null> $changes
 * @property array<string, string|null> $previous
 * @property string $status pending|approved|rejected|withdrawn
 * @property string|null $reason
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property Carbon $created_at
 * @property-read Member $member
 * @property-read User|null $decider
 */
class MemberChangeRequest extends Model
{
    use BelongsToGroup;

    /** Fields a member may ask to change, with how they read on screen. */
    public const FIELDS = [
        'phone' => 'Phone',
        'email' => 'Email',
        'date_of_birth' => 'Date of birth',
        'nominee_name' => 'Nominee name',
        'nominee_phone' => 'Nominee phone',
        'nominee_relationship' => 'Nominee relationship',
        'nominee_id_number' => 'Nominee ID number',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'previous' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
