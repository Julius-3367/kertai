<?php

namespace App\Domain\Groups\Models;

use App\Domain\Groups\CurrentGroup;
use App\Domain\Groups\Support\GroupSettings;
use App\Domain\Members\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $registration_number
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $location
 * @property string|null $paybill
 * @property string|null $paybill_account
 * @property string $currency
 * @property array<string, mixed>|null $settings
 */
class Group extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function settings(): GroupSettings
    {
        return new GroupSettings($this->settings ?? []);
    }

    public static function current(): ?self
    {
        return app(CurrentGroup::class)->get();
    }
}
