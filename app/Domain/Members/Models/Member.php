<?php

namespace App\Domain\Members\Models;

use App\Domain\Access\Models\PositionTerm;
use App\Domain\Dues\Models\Due;
use App\Domain\Groups\Concerns\BelongsToGroup;
use App\Domain\Payments\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $group_id
 * @property int|null $user_id
 * @property string $member_number
 * @property string $name
 * @property string $phone
 * @property string|null $email
 * @property string|null $id_number
 * @property string|null $gender
 * @property CarbonImmutable|null $date_of_birth
 * @property CarbonImmutable $joined_on
 * @property string $status
 * @property string|null $nominee_name
 * @property string|null $nominee_phone
 * @property string|null $nominee_relationship
 * @property string|null $nominee_id_number
 * @property bool $notify_sms
 * @property bool $notify_email
 * @property bool $reminders_opt_out
 * @property bool $show_name_publicly
 * @property string $locale
 * @property CarbonImmutable|null $exit_notice_on
 * @property CarbonImmutable|null $exited_on
 * @property string|null $exit_reason
 * @property string|null $notes
 * @property-read User|null $user
 */
class Member extends Model
{
    use BelongsToGroup;

    public const STATUSES = ['active', 'suspended', 'exiting', 'exited', 'deceased'];

    protected $guarded = ['id', 'group_id'];

    /** @var list<string>|null */
    private ?array $permissionCache = null;

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'immutable_date',
            'joined_on' => 'immutable_date',
            'exit_notice_on' => 'immutable_date',
            'exited_on' => 'immutable_date',
            'notify_sms' => 'boolean',
            'notify_email' => 'boolean',
            'reminders_opt_out' => 'boolean',
            'show_name_publicly' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dependants(): HasMany
    {
        return $this->hasMany(Dependant::class);
    }

    public function terms(): HasMany
    {
        return $this->hasMany(PositionTerm::class);
    }

    public function currentTerms(): HasMany
    {
        return $this->terms()->current()->with('position');
    }

    public function dues(): HasMany
    {
        return $this->hasMany(Due::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['active', 'exiting']);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'exiting'], true);
    }

    /** @return list<string> */
    public function permissions(): array
    {
        if ($this->permissionCache !== null) {
            return $this->permissionCache;
        }

        if (! $this->isActive()) {
            return $this->permissionCache = [];
        }

        return $this->permissionCache = $this->currentTerms()->get()
            ->flatMap(fn (PositionTerm $term) => $term->position->permissions ?? [])
            ->unique()
            ->values()
            ->all();
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    public function forgetPermissions(): void
    {
        $this->permissionCache = null;
    }

    public function refresh(): static
    {
        $this->permissionCache = null;

        return parent::refresh();
    }

    /** @return list<string> */
    public function positionNames(): array
    {
        return $this->currentTerms()->get()
            ->map(fn (PositionTerm $t) => $t->position->name.($t->is_acting ? ' (acting)' : ''))
            ->all();
    }

    public function firstName(): string
    {
        return explode(' ', trim($this->name))[0];
    }
}
