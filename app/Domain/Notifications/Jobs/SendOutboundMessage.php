<?php

namespace App\Domain\Notifications\Jobs;

use App\Domain\Groups\Models\Group;
use App\Domain\Meetings\MeetingCalendar;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Notifications\Email\EmailSender;
use App\Domain\Notifications\Models\OutboundMessage;
use App\Domain\Notifications\Sms\SmsManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Delivers one queued message. A provider failure is written to the message
 * and retried with back-off; it never bubbles up into the action that
 * queued it (a failed receipt SMS must not undo a payment).
 */
final class SendOutboundMessage implements ShouldQueue
{
    use Queueable;

    public const MAX_ATTEMPTS = 3;

    /** @var list<int> seconds */
    private const BACKOFF = [60, 300];

    public function __construct(public readonly int $messageId) {}

    public function handle(SmsManager $sms, EmailSender $email): void
    {
        $message = OutboundMessage::withoutGlobalScopes()->find($this->messageId);

        if (! $message || $message->status === 'sent') {
            return;
        }

        $group = Group::find($message->group_id);
        $message->attempts++;

        try {
            if ($message->channel === 'sms') {
                $driver = $sms->driver($group);
                $result = $driver->send($message->recipient, $message->body);
                $message->provider = $driver->name();
                $message->provider_message_id = $result->messageId;
            } else {
                [$actions, $attachments] = $this->meetingExtras($message, $group);
                $message->provider = $email->send($group, $message->recipient, $message->subject ?? '', $message->body, $actions, $attachments);
            }

            $message->status = 'sent';
            $message->sent_at = now();
            $message->error = null;
            $this->redactSecrets($message);
            $message->save();
        } catch (Throwable $e) {
            $message->status = $message->attempts >= self::MAX_ATTEMPTS ? 'failed' : 'queued';
            $message->error = mb_substr($e->getMessage(), 0, 1000);

            if ($message->status === 'queued' && ($this->job === null || $this->job->getConnectionName() === 'sync')) {
                // The sync queue cannot delay, so mark it failed and let an
                // official retry from the message log.
                $message->status = 'failed';
            }

            if ($message->status === 'failed') {
                $this->redactSecrets($message);
            }

            $message->save();

            if ($message->status === 'queued') {
                $this->release(self::BACKOFF[$message->attempts - 1] ?? 300);
            }
        }
    }

    /**
     * Meeting emails get Join and Add-to-calendar buttons and the calendar
     * file, built now so they reflect any change to the meeting.
     *
     * @return array{0: list<array{label: string, url: string}>, 1: list<array{name: string, data: string, mime: string}>}
     */
    private function meetingExtras(OutboundMessage $message, ?Group $group): array
    {
        $meetingId = (int) ($message->meta['meeting_id'] ?? 0);

        if ($meetingId === 0 || $group === null) {
            return [[], []];
        }

        $meeting = Meeting::withoutGlobalScopes()->whereKey($meetingId)->first();

        if ($meeting === null) {
            return [[], []];
        }

        $calendar = app(MeetingCalendar::class);
        $actions = array_values(array_filter([
            $meeting->online_link ? ['label' => 'Join the meeting', 'url' => $meeting->online_link] : null,
            ['label' => 'Add to Google Calendar', 'url' => $calendar->googleLink($meeting, $group)],
        ]));

        return [$actions, [[
            'name' => 'meeting.ics',
            'data' => $calendar->ics($meeting, $group),
            'mime' => 'text/calendar; charset=utf-8',
        ]]];
    }

    /**
     * One-time passwords must not sit in the message log or in exports.
     * A login message that never arrived is re-issued, not retried.
     */
    private function redactSecrets(OutboundMessage $message): void
    {
        if ($message->template_key === 'account_created') {
            $message->body = (string) preg_replace('/\b[A-Z][a-z]+-\d{4}-[A-Z0-9]{4}\b/', '[password hidden]', $message->body);
        }
    }
}
