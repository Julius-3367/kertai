<?php

namespace App\Domain\Notifications\Sms\Drivers;

use App\Domain\Notifications\Sms\SmsDriver;
use App\Domain\Notifications\Sms\SmsException;
use App\Domain\Notifications\Sms\SmsResult;
use Illuminate\Support\Facades\Http;

/**
 * Onfon Media bulk SMS API (v1 SendBulkSMS).
 */
final class OnfonDriver implements SmsDriver
{
    /** @param  array<string, mixed>  $config */
    public function __construct(private readonly array $config, private readonly string $senderId) {}

    public function name(): string
    {
        return 'onfon';
    }

    public function send(string $to, string $message): SmsResult
    {
        if (empty($this->config['api_key']) || empty($this->config['client_id'])) {
            throw new SmsException('Onfon is not configured: API key and client ID are required.');
        }

        $headers = array_filter(['AccessKey' => $this->config['access_key'] ?? null]);

        $response = Http::timeout(20)
            ->withHeaders($headers)
            ->acceptJson()
            ->post($this->config['url'] ?? 'https://api.onfonmedia.co.ke/v1/sms/SendBulkSMS', [
                'SenderId' => $this->senderId,
                'IsUnicode' => true,
                'IsFlash' => false,
                'MessageParameters' => [['Number' => $to, 'Text' => $message]],
                'ApiKey' => $this->config['api_key'],
                'ClientId' => $this->config['client_id'],
            ]);

        $body = $response->json() ?? [];

        if (! $response->successful() || (int) ($body['ErrorCode'] ?? -1) !== 0) {
            throw new SmsException('Onfon refused the message: '.($body['ErrorDescription'] ?? $response->body()));
        }

        $item = $body['Data'][0] ?? [];

        if ((int) ($item['MessageErrorCode'] ?? 0) !== 0) {
            throw new SmsException('Onfon refused the number: '.($item['MessageErrorDescription'] ?? 'unknown error'));
        }

        return new SmsResult($item['MessageId'] ?? null, $response->body());
    }
}
