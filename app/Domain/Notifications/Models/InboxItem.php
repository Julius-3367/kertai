<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Groups\Concerns\BelongsToGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One line under the bell.
 *
 * @property int $id
 * @property int $group_id
 * @property int $user_id
 * @property string $title
 * @property string $body
 * @property string|null $url
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 */
class InboxItem extends Model
{
    use BelongsToGroup;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
