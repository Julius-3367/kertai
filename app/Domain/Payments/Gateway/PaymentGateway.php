<?php

namespace App\Domain\Payments\Gateway;

interface PaymentGateway
{
    /**
     * Ask the payer's phone for an M-Pesa PIN. Amount is in cents and must be
     * whole shillings.
     *
     * @throws GatewayException
     */
    public function requestStkPush(int $amount, string $phone, string $reference, string $idempotencyKey): GatewayPayment;

    /**
     * Current state of a payment at the gateway.
     *
     * @throws GatewayException
     */
    public function fetch(string $externalId): GatewayPayment;

    public function verifyWebhookSignature(string $rawBody, ?string $signature): bool;

    public function name(): string;
}
