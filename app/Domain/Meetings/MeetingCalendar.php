<?php

namespace App\Domain\Meetings;

use App\Domain\Groups\Models\Group;
use App\Domain\Meetings\Models\Meeting;
use Carbon\CarbonImmutable;

/**
 * Calendar entries for a meeting: an "Add to Google Calendar" link and an
 * .ics file that any calendar (Google, Outlook, Samsung, Apple) can import.
 * Both carry the online link and reminders a day and an hour before.
 */
final class MeetingCalendar
{
    public function googleLink(Meeting $meeting, Group $group): string
    {
        return 'https://calendar.google.com/calendar/render?'.http_build_query([
            'action' => 'TEMPLATE',
            'text' => "{$group->name}: {$meeting->title}",
            'dates' => $this->utc($meeting->scheduled_at).'/'.$this->utc($meeting->endsAt()),
            'details' => $this->details($meeting),
            'location' => $meeting->online_link ?? $meeting->venue ?? '',
            'ctz' => 'Africa/Nairobi',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function ics(Meeting $meeting, Group $group): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'kertai';

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Kertai//Meetings//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            "UID:meeting-{$meeting->id}@{$host}",
            'DTSTAMP:'.$this->utc(CarbonImmutable::now()),
            'DTSTART:'.$this->utc($meeting->scheduled_at),
            'DTEND:'.$this->utc($meeting->endsAt()),
            'SUMMARY:'.$this->escape("{$group->name}: {$meeting->title}"),
            'DESCRIPTION:'.$this->escape($this->details($meeting)),
            'LOCATION:'.$this->escape($meeting->online_link ?? $meeting->venue ?? ''),
        ];

        if ($meeting->online_link) {
            $lines[] = 'URL:'.$meeting->online_link;
        }

        if ($meeting->status === 'cancelled') {
            $lines[] = 'STATUS:CANCELLED';
        }

        foreach (['-P1D' => 'tomorrow', '-PT1H' => 'in one hour'] as $trigger => $when) {
            $lines[] = 'BEGIN:VALARM';
            $lines[] = "TRIGGER:{$trigger}";
            $lines[] = 'ACTION:DISPLAY';
            $lines[] = 'DESCRIPTION:'.$this->escape("{$meeting->title} {$when}");
            $lines[] = 'END:VALARM';
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    private function details(Meeting $meeting): string
    {
        return trim(implode("\n\n", array_filter([
            $meeting->online_link ? "Join online: {$meeting->online_link}" : null,
            $meeting->venue ? "Venue: {$meeting->venue}" : null,
            $meeting->agenda ? "Agenda:\n{$meeting->agenda}" : null,
        ])));
    }

    private function utc(CarbonImmutable $time): string
    {
        return $time->utc()->format('Ymd\THis\Z');
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $text);
    }

    /**
     * Calendar lines may not exceed 75 octets; longer ones continue on the
     * next line after a space (RFC 5545).
     */
    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $parts = [];
        while (strlen($line) > 75) {
            $cut = 75;
            // Do not split a multi-byte character.
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--;
            }
            $parts[] = substr($line, 0, $cut);
            $line = ' '.substr($line, $cut);
        }
        $parts[] = $line;

        return implode("\r\n", $parts);
    }
}
