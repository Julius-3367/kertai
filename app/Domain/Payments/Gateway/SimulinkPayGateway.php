<?php

namespace App\Domain\Payments\Gateway;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * SimulinkPay REST API v1. Amounts there are whole shillings and payment ids
 * are integers; webhooks are signed with HMAC-SHA256 of the raw body using
 * the endpoint secret, sent in X-SimulinkPay-Signature.
 */
final class SimulinkPayGateway implements PaymentGateway
{
    /** @param  array<string, mixed>  $config */
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'simulinkpay';
    }

    public function requestStkPush(int $amount, string $phone, string $reference, string $idempotencyKey): GatewayPayment
    {
        if ($amount % 100 !== 0) {
            throw new GatewayException('M-Pesa only accepts whole shillings.');
        }

        if (empty($this->config['receiving_account_id'])) {
            throw new GatewayException('SimulinkPay receiving account is not configured.');
        }

        $response = $this->call(fn () => $this->client()
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post('/api/v1/payments', [
                'receiving_account_id' => (int) $this->config['receiving_account_id'],
                'amount' => intdiv($amount, 100),
                'customer_phone' => $phone,
                'reference' => mb_substr($reference, 0, 100),
            ]));

        return $this->toPayment($response->json());
    }

    public function fetch(string $externalId): GatewayPayment
    {
        $response = $this->call(fn () => $this->client()->get('/api/v1/payments/'.rawurlencode($externalId)));

        return $this->toPayment($response->json());
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signature): bool
    {
        $secret = (string) ($this->config['webhook_secret'] ?? '');

        if ($secret === '' || $signature === null || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawBody, $secret), strtolower(trim($signature)));
    }

    private function client(): PendingRequest
    {
        if (empty($this->config['token'])) {
            throw new GatewayException('SimulinkPay API token is not configured.');
        }

        return Http::baseUrl(rtrim((string) ($this->config['base_url'] ?? 'https://simulinkpay.co.ke'), '/'))
            ->withToken((string) $this->config['token'])
            ->acceptJson()
            ->asJson()
            ->timeout(30);
    }

    /**
     * @param  callable(): Response  $request
     */
    private function call(callable $request): Response
    {
        try {
            $response = $request();
        } catch (ConnectionException $e) {
            throw new GatewayException('Could not reach SimulinkPay: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            $message = $response->json('error.message') ?? 'HTTP '.$response->status();
            $type = $response->json('error.type');

            throw new GatewayException($type ? "{$message} ({$type})" : $message);
        }

        return $response;
    }

    /** @param  array<string, mixed>|null  $data */
    private function toPayment(?array $data): GatewayPayment
    {
        if (! is_array($data) || ! isset($data['id'], $data['status'])) {
            throw new GatewayException('SimulinkPay returned an unexpected response.');
        }

        return new GatewayPayment(
            externalId: (string) $data['id'],
            status: (string) $data['status'],
            amount: (int) ($data['amount'] ?? 0) * 100,
            receiptNumber: $data['receipt_number'] ?? null,
            failureReason: in_array($data['status'], ['failed', 'expired', 'cancelled'], true) ? 'Payment '.$data['status'].' at M-Pesa' : null,
            completedAt: $data['completed_at'] ?? null,
        );
    }
}
