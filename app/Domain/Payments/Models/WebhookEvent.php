<?php

namespace App\Domain\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $provider
 * @property string $event_type
 * @property string|null $external_id
 * @property bool $signature_valid
 * @property array<string, mixed> $payload
 * @property Carbon|null $processed_at
 * @property string|null $error
 */
class WebhookEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'signature_valid' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }
}
