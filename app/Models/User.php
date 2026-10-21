<?php

namespace App\Models;

use App\Domain\Groups\Models\Group;
use App\Domain\Members\Models\Member;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * Accounts are created by officials from the member register, so email is
 * optional: members can sign in with their phone number instead.
 *
 * @property int $id
 * @property int|null $group_id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string $locale
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $must_change_password
 * @property Carbon|null $last_login_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Member|null $member
 */
#[Fillable(['name', 'email', 'phone', 'password', 'group_id', 'locale', 'must_change_password', 'email_verified_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Group, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /** @return HasOne<Member, $this> */
    public function member(): HasOne
    {
        return $this->hasOne(Member::class);
    }

    /** @return array<int, string> */
    public function permissions(): array
    {
        return $this->member?->permissions() ?? [];
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }
}
