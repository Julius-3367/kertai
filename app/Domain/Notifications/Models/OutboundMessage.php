<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Groups\Concerns\BelongsToGroup;
use App\Domain\Members\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int|null $member_id
 * @property string $channel
 * @property string $recipient
 * @property string|null $subject
 * @property string $body
 * @property string|null $template_key
 * @property array<string, mixed>|null $meta
 * @property string $status
 * @property string|null $provider
 * @property string|null $provider_message_id
 * @property int $attempts
 * @property string|null $error
 * @property CarbonImmutable|null $sent_at
 * @property int|null $created_by
 */
class OutboundMessage extends Model
{
    use BelongsToGroup;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'meta' => 'array'];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
