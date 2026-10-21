<?php

use App\Domain\Dues\Actions\GenerateMonthlyDues;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Notifications\Models\InboundMessage;
use App\Domain\Notifications\Models\MessageTemplate;
use App\Domain\Notifications\Models\OutboundMessage;
use App\Domain\Notifications\Notifier;
use App\Domain\Notifications\Sms\Drivers\AdvantaDriver;
use App\Domain\Notifications\Sms\Drivers\AfricasTalkingDriver;
use App\Domain\Notifications\Sms\Drivers\HttpDriver;
use App\Domain\Notifications\Sms\Drivers\OnfonDriver;
use App\Domain\Notifications\Sms\SmsException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('writes to each member in their language and respects reminder opt-outs', function () {
    $swahili = $this->member(['locale' => 'sw', 'email' => null]);
    $quiet = $this->member(['reminders_opt_out' => true, 'email' => null]);
    $notifier = app(Notifier::class);

    $notifier->toMember($swahili, 'payment_received', ['amount' => 'KES 100.00', 'purpose' => 'contributions', 'receipt' => 'X1']);
    $notifier->toMember($quiet, 'arrears_reminder');
    $notifier->toMember($quiet, 'payment_received', ['amount' => 'KES 5.00', 'purpose' => 'x', 'receipt' => 'X2']);

    $sw = OutboundMessage::query()->where('member_id', $swahili->id)->firstOrFail();

    expect($sw->body)->toStartWith('Mpendwa Member')
        ->and($sw->channel)->toBe('sms')
        ->and($sw->status)->toBe('sent')
        ->and($sw->provider)->toBe('log')
        ->and(OutboundMessage::query()->where('member_id', $quiet->id)->pluck('template_key')->all())->toBe(['payment_received']);
});

it('uses a template the group has customised, and can reset it', function () {
    $secretary = $this->official('secretary');
    $member = $this->member(['email' => null]);

    $this->actingAs($secretary)->put('/messages/templates', [
        'key' => 'meeting_notice', 'channel' => 'sms', 'locale' => 'en', 'body' => 'Hi {name}, meeting {date}. {group}',
    ])->assertSessionHasNoErrors();

    app(Notifier::class)->toMember($member, 'meeting_notice', ['date' => 'Sat 1pm']);
    expect(OutboundMessage::query()->latest('id')->first()->body)->toBe('Hi Member, meeting Sat 1pm. Test Self Help Group');

    $this->actingAs($secretary)->put('/messages/templates', ['key' => 'meeting_notice', 'channel' => 'sms', 'locale' => 'en', 'body' => 'x', 'reset' => true]);
    expect(MessageTemplate::query()->count())->toBe(0);

    $this->actingAs($secretary)->get('/messages/templates')->assertOk()->assertInertia(fn ($p) => $p->has('templates', 12 * 4));
});

it('sends a message to members in arrears with their own balance filled in', function () {
    $secretary = $this->official('secretary');
    $late = $this->member(['email' => null]);
    app(GenerateMonthlyDues::class)->handle($this->group(), now()->subMonth()->format('Y-m'));
    $late->dues()->getQuery()->update(['status' => 'open']);
    $secretary->member->dues()->getQuery()->update(['status' => 'paid', 'paid' => 100000]);

    $this->actingAs($secretary)->post('/messages', [
        'audience' => 'arrears',
        'channels' => ['sms'],
        'body' => 'Dear {name}, please clear {arrears}.',
    ])->assertSessionHasNoErrors();

    $sent = OutboundMessage::query()->whereNull('template_key')->get();
    expect($sent)->toHaveCount(1)->and($sent->first()->body)->toBe('Dear Member, please clear KES 1,000.00.');

    $this->actingAs($secretary)->post('/messages', ['audience' => 'selected', 'member_ids' => [], 'channels' => ['sms'], 'body' => 'x'])->assertSessionHasErrors();
});

it('marks a refused SMS failed with the reason, and retries it', function () {
    $secretary = $this->official('secretary');
    Integration::query()->create(['kind' => 'sms', 'driver' => 'onfon', 'credentials' => ['api_key' => 'k', 'client_id' => 'c'], 'is_active' => true]);
    Http::fake(['*' => Http::sequence()
        ->push(['ErrorCode' => 7, 'ErrorDescription' => 'Insufficient credit'])
        ->push(['ErrorCode' => 0, 'Data' => [['MessageErrorCode' => 0, 'MessageId' => 'abc-1']]]),
    ]);

    $member = $this->member(['email' => null]);
    app(Notifier::class)->toMember($member, 'arrears_reminder');

    $message = OutboundMessage::query()->where('member_id', $member->id)->firstOrFail();
    expect($message->status)->toBe('failed')->and($message->error)->toContain('Insufficient credit');

    $this->actingAs($secretary)->post("/messages/{$message->id}/retry")->assertSessionHasNoErrors();

    expect($message->refresh()->status)->toBe('sent')->and($message->provider_message_id)->toBe('abc-1');

    $this->actingAs($secretary)->get('/messages?status=sent')->assertOk()->assertInertia(fn ($p) => $p->has('messages.data'));
    $this->actingAs($this->member()->user)->get('/messages')->assertForbidden();
});

it('speaks each SMS provider\'s API', function () {
    Http::fake([
        'api.onfonmedia.co.ke/*' => Http::response(['ErrorCode' => 0, 'Data' => [['MessageErrorCode' => 0, 'MessageId' => 'on-1']]]),
        'api.africastalking.com/*' => Http::response(['SMSMessageData' => ['Recipients' => [['status' => 'Success', 'messageId' => 'ATXid_1']]]]),
        'quicksms.advantasms.com/*' => Http::response(['responses' => [['response-code' => 200, 'messageid' => 9001]]]),
        'sms.example.test/*' => Http::response(['id' => 'gen-1', 'ok' => true]),
    ]);

    expect((new OnfonDriver(['api_key' => 'k', 'client_id' => 'c', 'access_key' => 'a'], 'KERTAI006'))->send('254712345678', 'Hi')->messageId)->toBe('on-1')
        ->and((new AfricasTalkingDriver(['username' => 'u', 'api_key' => 'k'], null))->send('254712345678', 'Hi')->messageId)->toBe('ATXid_1')
        ->and((new AdvantaDriver(['api_key' => 'k', 'partner_id' => '1'], 'KERTAI006'))->send('254712345678', 'Hi')->messageId)->toBe('9001')
        ->and((new HttpDriver(['url' => 'https://sms.example.test/send', 'headers' => '{"X-Key":"s"}', 'body' => '{"msisdn":"{to}","text":"{message}","from":"{sender}"}', 'success_contains' => '"ok":true'], 'KERTAI006'))->send('254712345678', 'Say "hi"')->messageId)->toBe('gen-1');

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'onfonmedia') && $r['SenderId'] === 'KERTAI006' && $r['MessageParameters'][0]['Number'] === '254712345678' && $r->hasHeader('AccessKey', 'a'));
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'africastalking') && $r['to'] === '+254712345678' && $r->hasHeader('apiKey', 'k'));
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'advantasms') && $r['shortcode'] === 'KERTAI006' && $r['mobile'] === '254712345678');
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'sms.example.test') && $r['text'] === 'Say "hi"' && $r->hasHeader('X-Key', 's'));

    expect(fn () => (new OnfonDriver([], 'X'))->send('254712345678', 'Hi'))->toThrow(SmsException::class, 'not configured');
});

it('answers BAL with the member\'s balances', function () {
    config(['kertai.inbound_sms_token' => 'tok-123']);
    $member = $this->member(['phone' => '0712555666', 'email' => null]);

    $this->postJson('/webhooks/sms/wrong', ['from' => '+254712555666', 'text' => 'BAL'])->assertNotFound();
    $this->postJson('/webhooks/sms/tok-123', ['from' => '+254712555666', 'text' => 'bal'])->assertOk();

    $inbound = InboundMessage::query()->firstOrFail();
    $reply = OutboundMessage::query()->find($inbound->reply_id);

    expect($inbound->member_id)->toBe($member->id)
        ->and($reply->template_key)->toBe('balance_reply')
        ->and($reply->body)->toContain('savings KES 0.00');
});
