<?php

namespace App\Domain\Integrations\Models;

use App\Domain\Groups\Concerns\BelongsToGroup;
use Illuminate\Database\Eloquent\Model;

/**
 * Provider keys for one kind of integration (sms, email, payments).
 * Credentials are encrypted at rest with the application key.
 *
 * @property int $id
 * @property int $group_id
 * @property string $kind
 * @property string $driver
 * @property array<string, string|null>|null $credentials
 * @property bool $is_active
 */
class Integration extends Model
{
    use BelongsToGroup;

    protected $guarded = ['id'];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'is_active' => 'boolean',
        ];
    }
}
