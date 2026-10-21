<?php

namespace App\Domain\Notifications\Email;

use App\Domain\Groups\Models\Group;
use App\Domain\Integrations\IntegrationSettings;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;

/**
 * Sends through the group's saved email provider, or Laravel's default
 * mailer when none is saved. SES uses its SMTP interface so only SMTP
 * credentials are needed.
 */
final class EmailSender
{
    public function __construct(private readonly IntegrationSettings $settings) {}

    /**
     * @param  list<array{label: string, url: string}>  $actions  buttons under the text
     * @param  list<array{name: string, data: string, mime: string}>  $attachments
     */
    public function send(?Group $group, string $to, string $subject, string $body, array $actions = [], array $attachments = []): string
    {
        $resolved = $this->settings->for($group?->id, 'email');
        $mailer = $this->configureMailer($resolved['driver'], $resolved['credentials']);
        $from = $resolved['credentials']['from_address'] ?? config('mail.from.address');
        $fromName = $resolved['credentials']['from_name'] ?? $group->name ?? config('mail.from.name');

        Mail::mailer($mailer)->html(
            view('mail.message', ['body' => $body, 'group' => $group, 'subject' => $subject, 'actions' => $actions])->render(),
            function (Message $message) use ($to, $subject, $from, $fromName, $attachments) {
                $message->to($to)->subject($subject)->from($from, $fromName);

                foreach ($attachments as $file) {
                    $message->attachData($file['data'], $file['name'], ['mime' => $file['mime']]);
                }
            },
        );

        return $mailer;
    }

    /** @param  array<string, mixed>  $c */
    private function configureMailer(string $driver, array $c): string
    {
        $config = match ($driver) {
            'smtp' => [
                'transport' => 'smtp',
                'host' => $c['host'] ?? null,
                'port' => (int) ($c['port'] ?? 587),
                'username' => $c['username'] ?? null,
                'password' => $c['password'] ?? null,
                'timeout' => 20,
            ],
            'ses' => [
                'transport' => 'smtp',
                'host' => 'email-smtp.'.($c['region'] ?? 'eu-west-1').'.amazonaws.com',
                'port' => 587,
                'username' => $c['username'] ?? null,
                'password' => $c['password'] ?? null,
                'timeout' => 20,
            ],
            'mailgun' => ['transport' => 'mailgun'],
            'postmark' => ['transport' => 'postmark'],
            'log' => ['transport' => 'log', 'channel' => config('mail.mailers.log.channel')],
            default => null,
        };

        if ($config === null) {
            return (string) config('mail.default');
        }

        if ($driver === 'mailgun') {
            config(['services.mailgun' => [
                'domain' => $c['domain'] ?? null,
                'secret' => $c['secret'] ?? null,
                'endpoint' => $c['endpoint'] ?? 'api.mailgun.net',
                'scheme' => 'https',
            ]]);
        }

        if ($driver === 'postmark') {
            config(['services.postmark' => ['key' => $c['token'] ?? null, 'token' => $c['token'] ?? null]]);
        }

        config(['mail.mailers.kertai' => $config]);
        Mail::purge('kertai');

        return 'kertai';
    }
}
