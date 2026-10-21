<?php

namespace App\Domain\Notifications\Sms\Drivers;

use App\Domain\Notifications\Sms\SmsDriver;
use App\Domain\Notifications\Sms\SmsException;
use App\Domain\Notifications\Sms\SmsResult;
use Illuminate\Support\Facades\Http;

/**
 * For any provider with a JSON HTTP API: the body is a JSON template with
 * {to}, {message} and {sender} placeholders.
 */
final class HttpDriver implements SmsDriver
{
    /** @param  array<string, mixed>  $config */
    public function __construct(private readonly array $config, private readonly string $senderId) {}

    public function name(): string
    {
        return 'http';
    }

    public function send(string $to, string $message): SmsResult
    {
        if (empty($this->config['url'])) {
            throw new SmsException('The HTTP SMS driver needs an endpoint URL.');
        }

        $headers = json_decode((string) ($this->config['headers'] ?? '{}'), true) ?: [];
        $template = (string) ($this->config['body'] ?? '{"to":"{to}","message":"{message}","from":"{sender}"}');

        // JSON-escape the value and drop only the surrounding quotes, so a
        // message that ends in a quote mark stays valid JSON.
        $replace = fn (string $value) => substr((string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 1, -1);
        $body = strtr($template, [
            '{to}' => $replace($to),
            '{message}' => $replace($message),
            '{sender}' => $replace($this->senderId),
        ]);

        $payload = json_decode($body, true);

        if (! is_array($payload)) {
            throw new SmsException('The HTTP SMS body template is not valid JSON.');
        }

        $response = Http::timeout(20)->withHeaders($headers)->acceptJson()->post($this->config['url'], $payload);
        $expected = $this->config['success_contains'] ?? null;

        if (! $response->successful() || ($expected && ! str_contains($response->body(), $expected))) {
            throw new SmsException('SMS endpoint refused the message: HTTP '.$response->status().' '.mb_substr($response->body(), 0, 200));
        }

        return new SmsResult($response->json('id') ?? $response->json('messageId'), $response->body());
    }
}
