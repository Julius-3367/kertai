<?php

namespace App\Domain\Notifications\Sms\Drivers;

use App\Domain\Notifications\Sms\SmsDriver;
use App\Domain\Notifications\Sms\SmsResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class LogDriver implements SmsDriver
{
    public function name(): string
    {
        return 'log';
    }

    public function send(string $to, string $message): SmsResult
    {
        Log::info("SMS to {$to}: {$message}");

        return new SmsResult('log-'.Str::random(10));
    }
}
