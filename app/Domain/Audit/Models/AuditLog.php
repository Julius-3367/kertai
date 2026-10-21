<?php

namespace App\Domain\Audit\Models;

use App\Domain\Groups\Concerns\BelongsToGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property int|null $group_id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string $description
 * @property array<string, mixed>|null $properties
 * @property string|null $ip_address
 */
class AuditLog extends Model
{
    use BelongsToGroup;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['properties' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit entries cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Audit entries cannot be deleted.'));
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
