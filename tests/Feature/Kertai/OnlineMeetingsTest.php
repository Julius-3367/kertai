<?php

use App\Domain\Meetings\MeetingCalendar;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Notifications\Models\OutboundMessage;
use Illuminate\Support\Facades\Mail;

function scheduleOnline(array $overrides = []): Meeting
{
    test()->actingAs(test()->secretary)->post('/meetings', [
        'title' => 'Monthly meeting',
        'kind' => 'ordinary',
        'scheduled_at' => '2026-11-07T14:00',
        'venue' => '',
        'online_link' => 'https://meet.google.com/abc-defg-hij',
        'duration_minutes' => 90,
        ...$overrides,
    ])->assertSessionHasNoErrors();

    return Meeting::query()->latest('id')->firstOrFail();
}

beforeEach(function () {
    $this->secretary = $this->official('secretary');
});

it('stores the meeting link and refuses anything that is not a web link', function () {
    $this->actingAs($this->secretary)->post('/meetings', [
        'title' => 'Bad', 'kind' => 'ordinary', 'scheduled_at' => '2026-11-07T14:00', 'online_link' => 'meet.google.com/abc',
    ])->assertSessionHasErrors('online_link');

    $meeting = scheduleOnline();

    expect($meeting->online_link)->toBe('https://meet.google.com/abc-defg-hij')
        ->and($meeting->duration_minutes)->toBe(90)
        ->and($meeting->place())->toBe('online');

    $this->actingAs($this->secretary)->post('/meetings', ['title' => 'In person', 'kind' => 'ordinary', 'scheduled_at' => '2026-11-07T14:00'])
        ->assertSessionHasNoErrors();
    expect(Meeting::query()->latest('id')->first()->duration_minutes)->toBe(120);
});

it('gives every member a calendar file with the link and two reminders', function () {
    $meeting = scheduleOnline();
    $member = $this->member();

    $response = $this->actingAs($member->user)->get("/meetings/{$meeting->id}/calendar.ics");
    $response->assertOk()->assertHeader('content-type', 'text/calendar; charset=utf-8');
    $ics = str_replace("\r\n ", '', $response->getContent());

    expect($ics)->toContain('BEGIN:VCALENDAR')
        ->toContain('DTSTART:20261107T110000Z')
        ->toContain('DTEND:20261107T123000Z')
        ->toContain('URL:https://meet.google.com/abc-defg-hij')
        ->toContain('TRIGGER:-P1D')
        ->toContain('TRIGGER:-PT1H')
        ->and(collect(explode("\r\n", $response->getContent()))->every(fn ($line) => strlen($line) <= 75))->toBeTrue();

    $this->actingAs($member->user)->get("/meetings/{$meeting->id}")->assertInertia(fn ($p) => $p
        ->where('meeting.online_link', 'https://meet.google.com/abc-defg-hij')
        ->where('meeting.google_calendar_url', fn ($url) => str_starts_with($url, 'https://calendar.google.com/calendar/render?action=TEMPLATE')
            && str_contains($url, 'dates=20261107T110000Z%2F20261107T123000Z')));

    $this->actingAs($member->user)->get('/dashboard')->assertInertia(fn ($p) => $p->where('meetings.0.online_link', 'https://meet.google.com/abc-defg-hij'));
});

it('puts the link in the notice SMS and adds join and calendar buttons and the file to the email', function () {
    $member = $this->member(['email' => 'mary@example.test']);
    $meeting = scheduleOnline();

    $this->actingAs($this->secretary)->post("/meetings/{$meeting->id}/notify")->assertSessionHasNoErrors();

    $sms = OutboundMessage::query()->where('member_id', $member->id)->where('channel', 'sms')->firstOrFail();
    expect($sms->body)->toContain('Join: https://meet.google.com/abc-defg-hij')
        ->and($sms->meta)->toBe(['meeting_id' => $meeting->id]);

    $sent = collect(Mail::mailer('array')->getSymfonyTransport()->messages())
        ->map->getOriginalMessage()
        ->first(fn ($m) => $m->getTo()[0]->getAddress() === 'mary@example.test');

    expect($sent)->not->toBeNull()
        ->and($sent->getHtmlBody())->toContain('Join the meeting')->toContain('Add to Google Calendar')
        ->and($sent->getAttachments()[0]->getFilename())->toBe('meeting.ics')
        ->and($sent->getAttachments()[0]->getBody())->toContain('URL:https://meet.google.com/abc-defg-hij');
});

it('reminds members on the morning of the meeting, once', function () {
    $member = $this->member(['email' => null]);
    $quiet = $this->member(['email' => null, 'reminders_opt_out' => true]);
    scheduleOnline();

    $this->artisan('kertai:daily', ['--date' => '2026-11-06'])->assertSuccessful();
    expect(OutboundMessage::query()->where('template_key', 'meeting_reminder')->count())->toBe(0);

    $this->artisan('kertai:daily', ['--date' => '2026-11-07'])->assertSuccessful();
    $this->artisan('kertai:daily', ['--date' => '2026-11-07'])->assertSuccessful();

    $reminder = OutboundMessage::query()->where('template_key', 'meeting_reminder')->where('member_id', $member->id)->get();

    expect($reminder)->toHaveCount(1)
        ->and($reminder->first()->body)->toContain('today at 2:00pm')->toContain('Join: https://meet.google.com/abc-defg-hij')
        ->and(OutboundMessage::query()->where('template_key', 'meeting_reminder')->where('member_id', $quiet->id)->exists())->toBeFalse();
});

it('escapes calendar text and marks cancelled meetings', function () {
    $meeting = scheduleOnline(['title' => 'AGM; budget, plans']);
    $meeting->update(['status' => 'cancelled']);

    $ics = app(MeetingCalendar::class)->ics($meeting, $this->group());

    expect($ics)->toContain('AGM\; budget\, plans')->toContain('STATUS:CANCELLED');
});
