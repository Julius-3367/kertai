<?php

use App\Domain\Members\Models\Member;
use App\Domain\Members\Models\SignupRequest;
use App\Domain\Notifications\Models\OutboundMessage;
use App\Models\User;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;

function signupForm(array $overrides = []): array
{
    return [
        'name' => 'Grace Wanjiku',
        'phone' => '0722 123 456',
        'id_number' => '23456789',
        'email' => '',
        'password' => 'kahawa-2026',
        'password_confirmation' => 'kahawa-2026',
        ...$overrides,
    ];
}

it('files a sign-up for approval and tells whoever manages members', function () {
    $secretary = $this->official('secretary');

    $this->get('/register')->assertOk();
    $this->post('/register', signupForm())->assertRedirect('/login')->assertSessionHasNoErrors();

    $signup = SignupRequest::query()->firstOrFail();
    expect($signup->status)->toBe('pending')
        ->and($signup->phone)->toBe('254722123456')
        ->and($signup->member_id)->toBeNull()
        ->and(User::query()->where('phone', '254722123456')->exists())->toBeFalse()
        ->and(OutboundMessage::query()->where('template_key', 'signup_new')->where('member_id', $secretary->member->id)->exists())->toBeTrue();

    $this->actingAs($secretary)->get('/members')->assertInertia(fn ($p) => $p->where('pendingSignups.0.id', $signup->id));
});

it('refuses a second request, or a number that already signs in', function () {
    $this->group();
    $member = $this->member(['phone' => '0733222111']);

    $this->post('/register', signupForm(['phone' => '0733 222 111']))->assertSessionHasErrors('phone');
    $this->post('/register', signupForm(['email' => $member->email]))->assertSessionHasErrors('email');

    $this->post('/register', signupForm())->assertSessionHasNoErrors();
    $this->post('/register', signupForm())->assertSessionHasErrors('phone');
    expect(SignupRequest::query()->count())->toBe(1);
});

it('tells a waiting applicant why they cannot sign in yet, but only with the right password', function () {
    $this->group();
    $this->post('/register', signupForm());

    $this->post(route('login.store'), ['login' => '0722123456', 'password' => 'wrong-password'])
        ->assertSessionHasErrors(['login' => trans('auth.failed')]);

    $this->post(route('login.store'), ['login' => '0722123456', 'password' => 'kahawa-2026'])
        ->assertSessionHasErrors(['login' => 'Your account is waiting for approval. You will get an SMS once the secretary approves it.']);
    $this->assertGuest();
});

it('adds a new applicant to the register on approval and lets them sign in with their own password', function () {
    $secretary = $this->official('secretary');
    $this->post('/register', signupForm());
    $signup = SignupRequest::query()->firstOrFail();

    $this->actingAs($secretary)->post("/signups/{$signup->id}/decide", ['decision' => 'approved'])->assertSessionHasNoErrors();
    $this->post(route('logout'));

    $member = Member::query()->where('phone', '254722123456')->firstOrFail();
    expect($member->user)->not->toBeNull()
        ->and($member->member_number)->toStartWith('M')
        ->and($member->id_number)->toBe('23456789')
        ->and($member->user->must_change_password)->toBeFalse()
        ->and($signup->refresh()->status)->toBe('approved')
        ->and(OutboundMessage::query()->where('template_key', 'signup_approved')->where('member_id', $member->id)->exists())->toBeTrue();

    $this->post(route('login.store'), ['login' => '0722 123 456', 'password' => 'kahawa-2026']);
    $this->assertAuthenticatedAs($member->user);
});

it('links an existing member without a login instead of adding them twice', function () {
    $secretary = $this->official('secretary');
    $existing = $this->member(['phone' => '0711555444', 'id_number' => '11223344'], login: false);
    $count = Member::query()->count();

    // Same ID, new phone: approval moves the register to the number they sign in with.
    $this->post('/register', signupForm(['phone' => '0722999888', 'id_number' => '11223344']));
    $signup = SignupRequest::query()->firstOrFail();
    expect($signup->member_id)->toBe($existing->id);

    $this->actingAs($secretary)->post("/signups/{$signup->id}/decide", ['decision' => 'approved'])->assertSessionHasNoErrors();

    $existing->refresh();
    expect(Member::query()->count())->toBe($count)
        ->and($existing->user_id)->not->toBeNull()
        ->and($existing->phone)->toBe('254722999888')
        ->and($existing->user->phone)->toBe('254722999888');
});

it('rejects with a reason sent by SMS, and only members managers may decide', function () {
    $secretary = $this->official('secretary');
    $someone = $this->member();
    $this->post('/register', signupForm());
    $signup = SignupRequest::query()->firstOrFail();

    $this->actingAs($someone->user)->post("/signups/{$signup->id}/decide", ['decision' => 'approved'])->assertForbidden();

    $this->actingAs($secretary)->post("/signups/{$signup->id}/decide", ['decision' => 'rejected', 'reason' => 'Not a member yet.'])->assertSessionHasNoErrors();
    $this->actingAs($secretary)->post("/signups/{$signup->id}/decide", ['decision' => 'approved'])->assertSessionHasErrors('action');
    $this->post(route('logout'));

    expect($signup->refresh()->status)->toBe('rejected')
        ->and(OutboundMessage::query()->where('recipient', '254722123456')->where('body', 'like', '%Not a member yet.%')->exists())->toBeTrue()
        ->and(User::query()->where('phone', '254722123456')->exists())->toBeFalse();

    $this->post(route('login.store'), ['login' => '0722123456', 'password' => 'kahawa-2026'])
        ->assertSessionHasErrors(['login' => 'Your sign-up was not approved. Please contact the secretary.']);
    $this->assertGuest();
});

it('keeps suspended members out when they use a passkey', function () {
    $member = $this->member(['status' => 'suspended']);
    $active = $this->member();

    $passkey = new Passkey;
    $passkey->setRelation('user', $member->user);
    expect(Passkeys::allowsLogin(request(), $passkey))->toBeFalse();

    $passkey->setRelation('user', $active->user);
    expect(Passkeys::allowsLogin(request(), $passkey))->toBeTrue();
});
