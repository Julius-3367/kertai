<?php

/*
| Provider settings. Each value here is only the fallback: keys saved under
| Administration > Integrations are stored encrypted in the database and
| take precedence, so a running system can switch provider without a deploy.
*/

return [

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'sender_id' => env('SMS_SENDER_ID', 'KERTAI006'),

        'onfon' => [
            'url' => env('ONFON_URL', 'https://api.onfonmedia.co.ke/v1/sms/SendBulkSMS'),
            'api_key' => env('ONFON_API_KEY'),
            'client_id' => env('ONFON_CLIENT_ID'),
            'access_key' => env('ONFON_ACCESS_KEY'),
        ],

        'africastalking' => [
            'username' => env('AT_USERNAME'),
            'api_key' => env('AT_API_KEY'),
            'sandbox' => env('AT_SANDBOX', false),
        ],

        'advanta' => [
            'url' => env('ADVANTA_URL', 'https://quicksms.advantasms.com/api/services/sendsms/'),
            'api_key' => env('ADVANTA_API_KEY'),
            'partner_id' => env('ADVANTA_PARTNER_ID'),
        ],

        'http' => [
            'url' => env('SMS_HTTP_URL'),
            'headers' => env('SMS_HTTP_HEADERS', '{}'),
            'body' => env('SMS_HTTP_BODY', '{"to":"{to}","message":"{message}","from":"{sender}"}'),
            'success_contains' => env('SMS_HTTP_SUCCESS_CONTAINS'),
        ],
    ],

    'email' => [
        // "default" sends through Laravel's own MAIL_* settings.
        'driver' => env('KERTAI_EMAIL_DRIVER', 'default'),
    ],

    'payments' => [
        'driver' => env('PAYMENTS_DRIVER', 'fake'),

        'simulinkpay' => [
            'base_url' => env('SIMULINKPAY_BASE_URL', 'https://simulinkpay.co.ke'),
            'token' => env('SIMULINKPAY_TOKEN'),
            'receiving_account_id' => env('SIMULINKPAY_RECEIVING_ACCOUNT_ID'),
            'webhook_secret' => env('SIMULINKPAY_WEBHOOK_SECRET'),
        ],
    ],

    // Secret path segment for the inbound SMS webhook (members text BAL).
    // The provider is pointed at /webhooks/sms/{token}.
    'inbound_sms_token' => env('INBOUND_SMS_TOKEN'),

    // Pending STK pushes older than this are checked against the gateway.
    'reconcile_after_seconds' => 60,

    // ...and given up on after this long without an answer.
    'expire_after_minutes' => 60,

];
