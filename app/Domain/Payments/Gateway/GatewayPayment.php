<?php

namespace App\Domain\Payments\Gateway;

final readonly class GatewayPayment
{
    /**
     * @param  string  $status  pending|processing|succeeded|failed|expired|cancelled
     * @param  int  $amount  cents
     */
    public function __construct(
        public string $externalId,
        public string $status,
        public int $amount,
        public ?string $receiptNumber = null,
        public ?string $failureReason = null,
        public ?string $completedAt = null,
    ) {}
}
