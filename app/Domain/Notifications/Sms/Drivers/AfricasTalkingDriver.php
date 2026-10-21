<?php

namespace App\Domain\Notifications\Sms\Drivers;

use App\Domain\Notifications\Sms\SmsDriver;
use App\Domain\Notifications\Sms\SmsException;
use App\Domain\Notifications\Sms\SmsResult;
use Illuminate\Support\Facades\Http;

final class AfricasTalkingDriver implements SmsDriver
{
    /** @param  array<string, mixed>  $config */
    public function __construct(private readonly array $config, private readonly ?string $senderId) {}

    public function name(): string
    {
        return 'africastalking';
    }

    public function send(string $to, string $message): SmsResult
    {
        if (empty($this->config['username']) || empty($this->config['api_key'])) {
            throw new SmsException("Africa's Talking is not configured: username and API key are required.");
        }

        $sandbox = filter_var($this->config['sandbox'] ?? false, FILTER_VALIDATE_BOOL);
        $url = $sandbox
            ? 'https://api.sandbox.africastalking.com/version1/messaging'
            : 'https://api.africastalking.com/version1/messaging';

        $response = Http::timeout(20)
            ->asForm()
            ->acceptJson()
            ->withHeaders(['apiKey' => $this->config['api_key']])
            ->post($url, array_filter([
                'username' => $this->config['username'],
                'to' => '+'.$to,
                'message' => $message,
                'from' => $this->senderId,
            ]));

        $recipient = $response->json('SMSMessageData.Recipients.0');

        if (! $response->successful() || ! $recipient || ($recipient['status'] ?? '') !== 'Success') {
            throw new SmsException("Africa's Talking refused the message: ".($recipient['status'] ?? $response->json('SMSMessageData.Message') ?? $response->body()));
        }

        return new SmsResult($recipient['messageId'] ?? null, $response->body());
    }
}
