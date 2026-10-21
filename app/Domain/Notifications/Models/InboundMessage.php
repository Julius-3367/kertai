<?php

namespace App\Domain\Notifications\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $group_id
 * @property string $provider
 * @property string $sender
 * @property string $body
 * @property int|null $member_id
 * @property int|null $reply_id
 */
class InboundMessage extends Model
{
    protected $guarded = ['id'];
}
