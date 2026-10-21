<?php

namespace App\Domain\Integrations;

use App\Domain\Integrations\Models\Integration;

/**
 * Resolves which driver and keys to use for a group: the saved integration
 * if there is an active one, otherwise config/kertai.php.
 */
final class IntegrationSettings
{
    /**
     * Fields each driver needs. "secret" fields are never sent back to the
     * browser; leaving one blank on save keeps the stored value.
     *
     * @var array<string, array<string, array{label: string, fields: list<array{key: string, label: string, secret?: bool, placeholder?: string}>}>>
     */
    public const DRIVERS = [
        'sms' => [
            'onfon' => ['label' => 'Onfon Media', 'fields' => [
                ['key' => 'api_key', 'label' => 'API key', 'secret' => true],
                ['key' => 'client_id', 'label' => 'Client ID'],
                ['key' => 'access_key', 'label' => 'Access key (header)', 'secret' => true],
                ['key' => 'sender_id', 'label' => 'Sender ID', 'placeholder' => 'KERTAI006'],
                ['key' => 'url', 'label' => 'API URL', 'placeholder' => 'https://api.onfonmedia.co.ke/v1/sms/SendBulkSMS'],
            ]],
            'africastalking' => ['label' => "Africa's Talking", 'fields' => [
                ['key' => 'username', 'label' => 'Username'],
                ['key' => 'api_key', 'label' => 'API key', 'secret' => true],
                ['key' => 'sender_id', 'label' => 'Sender ID (optional)'],
                ['key' => 'sandbox', 'label' => 'Sandbox (yes/no)', 'placeholder' => 'no'],
            ]],
            'advanta' => ['label' => 'Advanta SMS', 'fields' => [
                ['key' => 'api_key', 'label' => 'API key', 'secret' => true],
                ['key' => 'partner_id', 'label' => 'Partner ID'],
                ['key' => 'sender_id', 'label' => 'Shortcode / sender ID'],
                ['key' => 'url', 'label' => 'API URL', 'placeholder' => 'https://quicksms.advantasms.com/api/services/sendsms/'],
            ]],
            'http' => ['label' => 'Any HTTP SMS API', 'fields' => [
                ['key' => 'url', 'label' => 'Endpoint URL'],
                ['key' => 'headers', 'label' => 'Headers (JSON)', 'secret' => true, 'placeholder' => '{"Authorization":"Bearer ..."}'],
                ['key' => 'body', 'label' => 'Body template (JSON, use {to} {message} {sender})'],
                ['key' => 'sender_id', 'label' => 'Sender ID'],
                ['key' => 'success_contains', 'label' => 'Response text that means success'],
            ]],
            'log' => ['label' => 'Log only (testing)', 'fields' => []],
        ],
        'email' => [
            'smtp' => ['label' => 'SMTP', 'fields' => [
                ['key' => 'host', 'label' => 'Host'],
                ['key' => 'port', 'label' => 'Port', 'placeholder' => '587'],
                ['key' => 'username', 'label' => 'Username'],
                ['key' => 'password', 'label' => 'Password', 'secret' => true],
                ['key' => 'from_address', 'label' => 'From address'],
                ['key' => 'from_name', 'label' => 'From name'],
            ]],
            'mailgun' => ['label' => 'Mailgun', 'fields' => [
                ['key' => 'domain', 'label' => 'Domain'],
                ['key' => 'secret', 'label' => 'API key', 'secret' => true],
                ['key' => 'endpoint', 'label' => 'Endpoint', 'placeholder' => 'api.mailgun.net'],
                ['key' => 'from_address', 'label' => 'From address'],
                ['key' => 'from_name', 'label' => 'From name'],
            ]],
            'postmark' => ['label' => 'Postmark', 'fields' => [
                ['key' => 'token', 'label' => 'Server token', 'secret' => true],
                ['key' => 'from_address', 'label' => 'From address'],
                ['key' => 'from_name', 'label' => 'From name'],
            ]],
            'ses' => ['label' => 'Amazon SES (SMTP credentials)', 'fields' => [
                ['key' => 'region', 'label' => 'Region', 'placeholder' => 'eu-west-1'],
                ['key' => 'username', 'label' => 'SMTP username'],
                ['key' => 'password', 'label' => 'SMTP password', 'secret' => true],
                ['key' => 'from_address', 'label' => 'From address'],
                ['key' => 'from_name', 'label' => 'From name'],
            ]],
            'log' => ['label' => 'Log only (testing)', 'fields' => [
                ['key' => 'from_address', 'label' => 'From address'],
            ]],
        ],
        'payments' => [
            'simulinkpay' => ['label' => 'SimulinkPay', 'fields' => [
                ['key' => 'base_url', 'label' => 'Base URL', 'placeholder' => 'https://simulinkpay.co.ke'],
                ['key' => 'token', 'label' => 'API token', 'secret' => true],
                ['key' => 'receiving_account_id', 'label' => 'Receiving account ID'],
                ['key' => 'webhook_secret', 'label' => 'Webhook secret (whsec_...)', 'secret' => true],
            ]],
            'fake' => ['label' => 'Fake gateway (testing)', 'fields' => []],
        ],
    ];

    /**
     * @return array{driver: string, credentials: array<string, mixed>, source: string}
     */
    public function for(?int $groupId, string $kind): array
    {
        $integration = $groupId === null ? null : Integration::withoutGlobalScopes()
            ->where('group_id', $groupId)
            ->where('kind', $kind)
            ->where('is_active', true)
            ->first();

        if ($integration) {
            return [
                'driver' => $integration->driver,
                'credentials' => array_filter($integration->credentials ?? [], fn ($v) => $v !== null && $v !== ''),
                'source' => 'database',
            ];
        }

        $driver = (string) config("kertai.{$kind}.driver");

        return [
            'driver' => $driver,
            'credentials' => array_filter((array) config("kertai.{$kind}.{$driver}", []), fn ($v) => $v !== null && $v !== ''),
            'source' => 'config',
        ];
    }
}
