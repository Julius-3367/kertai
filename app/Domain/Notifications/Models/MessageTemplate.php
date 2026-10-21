<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Groups\Concerns\BelongsToGroup;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $group_id
 * @property string $key
 * @property string $channel
 * @property string $locale
 * @property string|null $subject
 * @property string $body
 */
class MessageTemplate extends Model
{
    use BelongsToGroup;

    protected $guarded = ['id'];
}
