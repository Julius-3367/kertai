<?php

namespace App\Domain\Notifications\Sms;

interface SmsDriver
{
    /**
     * Send one message to a 2547XXXXXXXX number. Throws SmsException when the
     * provider refuses or cannot be reached.
     */
    public function send(string $to, string $message): SmsResult;

    public function name(): string;
}
