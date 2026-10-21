<?php

namespace App\Domain\Access\Models;

use App\Domain\Groups\Concerns\BelongsToGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $group_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property list<string> $permissions
 * @property bool $is_official
 * @property bool $is_system
 * @property int|null $max_holders
 * @property int $sort
 */
class Position extends Model
{
    use BelongsToGroup;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'is_official' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    /** @return HasMany<PositionTerm, $this> */
    public function terms(): HasMany
    {
        return $this->hasMany(PositionTerm::class);
    }

    /** @return HasMany<PositionTerm, $this> */
    public function currentTerms(): HasMany
    {
        return $this->terms()->current();
    }
}
