<?php

namespace App\Domain\Notifications\Sms;

use App\Domain\Groups\Models\Group;
use App\Domain\Integrations\IntegrationSettings;
use App\Domain\Notifications\Sms\Drivers\AdvantaDriver;
use App\Domain\Notifications\Sms\Drivers\AfricasTalkingDriver;
use App\Domain\Notifications\Sms\Drivers\HttpDriver;
use App\Domain\Notifications\Sms\Drivers\LogDriver;
use App\Domain\Notifications\Sms\Drivers\OnfonDriver;

final class SmsManager
{
    public function __construct(private readonly IntegrationSettings $settings) {}

    public function driver(?Group $group): SmsDriver
    {
        $resolved = $this->settings->for($group?->id, 'sms');
        $credentials = $resolved['credentials'];
        $sender = (string) ($credentials['sender_id'] ?? $group?->settings()->get('sms_sender_id') ?? config('kertai.sms.sender_id'));

        return match ($resolved['driver']) {
            'onfon' => new OnfonDriver($credentials, $sender),
            'africastalking' => new AfricasTalkingDriver($credentials, $credentials['sender_id'] ?? null),
            'advanta' => new AdvantaDriver($credentials, $sender),
            'http' => new HttpDriver($credentials, $sender),
            default => new LogDriver,
        };
    }
}
