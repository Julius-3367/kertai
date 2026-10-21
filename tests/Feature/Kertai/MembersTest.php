<?php

use App\Domain\Dues\Actions\GenerateMonthlyDues;
use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Ledger\Ledger;
use App\Domain\Members\Models\Member;
use App\Domain\Notifications\Models\OutboundMessage;
use App\Domain\Payouts\Models\Payout;
use App\Domain\Welfare\Models\WelfareCase;
use App\Models\User;

function giveSavings(Member $member, int $cents): void
{
    $accounts = app(ChartOfAccounts::class);
    $group = test()->group();

    app(Ledger::class)->post('Opening savings', [
        ['account' => $accounts->get($group, ChartOfAccounts::BANK), 'debit' => $cents, 'member_id' => $member->id],
        ['account' => $accounts->get($group, ChartOfAccounts::SAVINGS), 'credit' => $cents, 'member_id' => $member->id],
    ]);
}

it('lets the secretary add and edit members', function () {
    $secretary = $this->official('secretary');

    $this->actingAs($secretary)->get('/members/create')->assertOk();
    $this->actingAs($secretary)->post('/members', [
        'name' => 'Grace Chebet',
        'phone' => '+254 722 000 111',
        'email' => 'Grace@Example.com',
        'joined_on' => '2026-01-10',
        'locale' => 'sw',
        'notify_sms' => true,
        'notify_email' => true,
        'nominee_name' => 'Kiprotich Chebet',
        'nominee_phone' => '0722000222',
    ])->assertSessionHasNoErrors();

    $grace = Member::query()->where('name', 'Grace Chebet')->firstOrFail();

    expect($grace->phone)->toBe('254722000111')
        ->and($grace->email)->toBe('grace@example.com')
        ->and($grace->nominee_phone)->toBe('254722000222')
        ->and($grace->member_number)->toBe('M002');

    $this->actingAs($secretary)->post('/members', ['name' => 'Copy', 'phone' => '0722000111', 'joined_on' => '2026-01-10', 'locale' => 'en'])
        ->assertSessionHasErrors('phone');
    $this->actingAs($secretary)->post('/members', ['name' => 'Bad', 'phone' => '12345', 'joined_on' => '2026-01-10', 'locale' => 'en'])
        ->assertSessionHasErrors('phone');

    $this->actingAs($secretary)->put("/members/{$grace->id}", [
        'name' => 'Grace Chebet Koech',
        'phone' => '0722000111',
        'joined_on' => '2026-01-10',
        'status' => 'suspended',
        'locale' => 'en',
    ])->assertSessionHasNoErrors();

    expect($grace->refresh()->name)->toBe('Grace Chebet Koech')->and($grace->status)->toBe('suspended');

    $this->actingAs($secretary)->get('/members?q=koech')->assertInertia(fn ($p) => $p->has('members', 1));
});

it('keeps the register and other profiles private from ordinary members', function () {
    $alice = $this->member();
    $bob = $this->member();

    $this->actingAs($alice->user)->get('/members')->assertForbidden();
    $this->actingAs($alice->user)->get("/members/{$bob->id}")->assertForbidden();
    $this->actingAs($alice->user)->get("/members/{$alice->id}")->assertOk()->assertInertia(fn ($p) => $p->where('isSelf', true)->has('finance'));
    $this->actingAs($alice->user)->post('/members', [])->assertForbidden();
});

it('issues a one-time login by SMS and makes the member choose a password', function () {
    $secretary = $this->official('secretary');
    $member = $this->member(['phone' => '0733123456', 'email' => null], login: false);

    $this->actingAs($secretary)->post("/members/{$member->id}/login")->assertSessionHasNoErrors();

    $user = $member->refresh()->user;
    $sms = OutboundMessage::query()->where('member_id', $member->id)->where('template_key', 'account_created')->firstOrFail();

    expect($user)->not->toBeNull()
        ->and($user->phone)->toBe('254733123456')
        ->and($user->must_change_password)->toBeTrue()
        ->and($sms->status)->toBe('sent')
        ->and($sms->body)->toContain('[password hidden]')
        ->and($sms->body)->toContain('0733123456');

    auth()->logout();
    $user->forceFill(['password' => 'Temp-1234-ABCD'])->save();

    $this->post('/login', ['login' => '0733 123 456', 'password' => 'Temp-1234-ABCD']);
    $this->get('/dashboard')->assertRedirect('/welcome/password');
    $this->put('/welcome/password', ['password' => 'NewSecret99', 'password_confirmation' => 'NewSecret99'])->assertRedirect('/dashboard');
    $this->get('/dashboard')->assertOk();

    expect($user->refresh()->must_change_password)->toBeFalse();
});

it('manages welfare beneficiaries', function () {
    $secretary = $this->official('secretary');
    $member = $this->member();

    $this->actingAs($secretary)->post("/members/{$member->id}/dependants", ['name' => 'Ann', 'relationship' => 'child'])->assertSessionHasNoErrors();
    $dependant = $member->dependants()->firstOrFail();

    $this->actingAs($secretary)->delete("/members/{$member->id}/dependants/{$dependant->id}")->assertSessionHasNoErrors();
    expect($member->dependants()->count())->toBe(0);
});

it('handles notice, refund of savings less arrears, and leaving', function () {
    $secretary = $this->official('secretary');
    $treasurer = $this->official('treasurer');
    $member = $this->member();
    giveSavings($member, 500000);
    app(GenerateMonthlyDues::class)->handle($this->group(), now()->subMonth()->format('Y-m'));

    $this->actingAs($secretary)->post("/members/{$member->id}/notice", ['reason' => 'Relocating', 'notice_on' => now()->toDateString()])->assertSessionHasNoErrors();
    expect($member->refresh()->status)->toBe('exiting');

    $this->actingAs($treasurer)->post("/members/{$member->id}/exit")->assertSessionHasErrors('action');
    $this->actingAs($treasurer)->post("/members/{$member->id}/exit", ['ignore_notice' => true])->assertRedirect();

    $payout = Payout::query()->where('type', 'exit_refund')->firstOrFail();

    // 5,000 savings, less the 1,000 owed, plus the 300 savings share of that contribution.
    expect($payout->amount)->toBe(430000)
        ->and($member->dues()->first()->status)->toBe('paid');

    $chair = $this->official('chairperson');
    $this->actingAs($chair)->post("/payouts/{$payout->id}/decide", ['decision' => 'approved']);
    $this->actingAs($secretary)->post("/payouts/{$payout->id}/decide", ['decision' => 'approved']);
    $this->actingAs($treasurer)->post("/payouts/{$payout->id}/pay", ['method' => 'mpesa', 'reference' => 'RKZ9']);

    expect($member->refresh()->status)->toBe('exited')
        ->and($member->exited_on)->not->toBeNull();

    $this->post('/logout');
    $this->post('/login', ['login' => $member->user->email, 'password' => 'password']);
    $this->assertGuest();
});

it('withdraws a notice', function () {
    $secretary = $this->official('secretary');
    $member = $this->member();

    $this->actingAs($secretary)->post("/members/{$member->id}/notice", ['reason' => 'Moving', 'notice_on' => now()->toDateString()]);
    $this->actingAs($secretary)->post("/members/{$member->id}/notice/withdraw");

    expect($member->refresh()->status)->toBe('active')->and($member->exit_notice_on)->toBeNull();
});

it('records a death, opens welfare and pays savings to the nominee', function () {
    $secretary = $this->official('secretary');
    $treasurer = $this->official('treasurer');
    $member = $this->member(['nominee_name' => 'Jane Nominee', 'nominee_phone' => '254722999888']);
    giveSavings($member, 200000);

    $this->actingAs($secretary)->post("/members/{$member->id}/death", ['died_on' => now()->toDateString()])->assertRedirect();

    $case = WelfareCase::query()->firstOrFail();
    expect($member->refresh()->status)->toBe('deceased')
        ->and($case->event)->toBe('death')
        ->and($case->benefit_amount)->toBe(5000000);

    $this->actingAs($treasurer)->post("/members/{$member->id}/exit")->assertRedirect();
    $payout = Payout::query()->where('type', 'exit_refund')->firstOrFail();

    expect($payout->payee_name)->toBe('Jane Nominee')
        ->and($payout->payee_phone)->toBe('254722999888')
        ->and($payout->amount)->toBe(200000);
});

it('lets members set their own message and privacy preferences', function () {
    $member = $this->member();

    $this->actingAs($member->user)->put('/me/preferences', [
        'notify_sms' => true,
        'notify_email' => false,
        'reminders_opt_out' => true,
        'show_name_publicly' => true,
        'locale' => 'sw',
    ])->assertSessionHasNoErrors();

    expect($member->refresh()->reminders_opt_out)->toBeTrue()
        ->and($member->locale)->toBe('sw')
        ->and(User::find($member->user_id)->locale)->toBe('sw');
});
