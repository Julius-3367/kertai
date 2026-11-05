<?php

namespace App\Domain\Members\Models;

use App\Domain\Groups\Concerns\BelongsToGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A request for a login made from the sign-up page. member_id is set when
 * the phone or ID number matches someone already on the register.
 *
 * @property int $id
 * @property int $group_id
 * @property int|null $member_id
 * @property string $name
 * @property string $phone
 * @property string|null $email
 * @property string|null $id_number
 * @property string $locale
 * @property string $password
 * @property string $status pending|approved|rejected
 * @property string|null $reason
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property Carbon $created_at
 * @property-read Member|null $member
 * @property-read User|null $decider
 */
class SignupRequest extends Model
{
    use BelongsToGroup;

    protected $guarded = ['id'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
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
