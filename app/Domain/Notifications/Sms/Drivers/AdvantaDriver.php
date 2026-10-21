<?php

namespace App\Domain\Notifications\Sms\Drivers;

use App\Domain\Notifications\Sms\SmsDriver;
use App\Domain\Notifications\Sms\SmsException;
use App\Domain\Notifications\Sms\SmsResult;
use Illuminate\Support\Facades\Http;

final class AdvantaDriver implements SmsDriver
{
    /** @param  array<string, mixed>  $config */
    public function __construct(private readonly array $config, private readonly string $senderId) {}

    public function name(): string
    {
        return 'advanta';
    }

    public function send(string $to, string $message): SmsResult
    {
        if (empty($this->config['api_key']) || empty($this->config['partner_id'])) {
            throw new SmsException('Advanta is not configured: API key and partner ID are required.');
        }

        $response = Http::timeout(20)
            ->acceptJson()
            ->post($this->config['url'] ?? 'https://quicksms.advantasms.com/api/services/sendsms/', [
                'apikey' => $this->config['api_key'],
                'partnerID' => $this->config['partner_id'],
                'message' => $message,
                'shortcode' => $this->senderId,
                'mobile' => $to,
            ]);

        $item = $response->json('responses.0');
        $code = (int) ($item['response-code'] ?? 0);

        if (! $response->successful() || $code !== 200) {
            throw new SmsException('Advanta refused the message: '.($item['response-description'] ?? $response->body()));
        }

        return new SmsResult(isset($item['messageid']) ? (string) $item['messageid'] : null, $response->body());
    }
}
