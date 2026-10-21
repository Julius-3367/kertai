<?php

namespace App\Domain\Notifications\Sms;

final readonly class SmsResult
{
    public function __construct(
        public ?string $messageId,
        public ?string $raw = null,
    ) {}
}
