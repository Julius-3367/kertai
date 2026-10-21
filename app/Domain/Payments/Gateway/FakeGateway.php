<?php

namespace App\Domain\Payments\Gateway;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Stand-in for SimulinkPay in development and tests. Mirrors SimulinkPay's
 * own fake provider: the last four digits of the phone pick the outcome
 * (0000 insufficient funds, 1111 cancelled, 2222 timeout, anything else
 * succeeds). The push is "processing" until the first status check.
 */
final class FakeGateway implements PaymentGateway
{
    public const WEBHOOK_SECRET = 'whsec_fake_kertai_development';

    public function name(): string
    {
        return 'fake';
    }

    public function requestStkPush(int $amount, string $phone, string $reference, string $idempotencyKey): GatewayPayment
    {
        if ($amount % 100 !== 0) {
            throw new GatewayException('M-Pesa only accepts whole shillings.');
        }

        $id = Cache::remember("fake-gateway:key:{$idempotencyKey}", now()->addDay(), fn () => 'fake_'.Str::lower(Str::random(12)));
        Cache::put("fake-gateway:payment:{$id}", ['amount' => $amount, 'phone' => $phone], now()->addDay());

        return new GatewayPayment($id, 'processing', $amount);
    }

    public function fetch(string $externalId): GatewayPayment
    {
        $stored = Cache::get("fake-gateway:payment:{$externalId}");

        if ($stored === null) {
            throw new GatewayException("Unknown payment {$externalId}.");
        }

        $outcome = match (substr($stored['phone'], -4)) {
            '0000' => ['failed', 'Insufficient M-Pesa balance'],
            '1111' => ['failed', 'Cancelled by the payer'],
            '2222' => ['failed', 'The payer did not respond in time'],
            default => ['succeeded', null],
        };

        return new GatewayPayment(
            externalId: $externalId,
            status: $outcome[0],
            amount: $stored['amount'],
            receiptNumber: $outcome[0] === 'succeeded' ? 'FK'.strtoupper(substr(md5($externalId), 0, 8)) : null,
            failureReason: $outcome[1],
        );
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signature): bool
    {
        return $signature !== null && hash_equals(hash_hmac('sha256', $rawBody, self::WEBHOOK_SECRET), strtolower(trim($signature)));
    }
}
