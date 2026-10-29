<?php

use App\Domain\Access\Models\Position;
use App\Domain\Access\ViewAs;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Models\MemberChangeRequest;
use App\Domain\Notifications\Models\InboxItem;
use App\Domain\Notifications\Models\OutboundMessage;
use App\Domain\Notifications\Notifier;
use App\Domain\Votes\Models\Poll;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

// Deleting

it('deletes a member added for testing, with their login and positions, and reuses the number', function () {
    $secretary = $this->official('secretary', ['member_number' => 'M001']);
    $test = $this->member(['member_number' => 'M002', 'name' => 'Testing Member'], 'treasurer');
    $userId = $test->user_id;

    $this->actingAs($secretary)->get("/members/{$test->id}")->assertInertia(fn ($p) => $p->where('deleteBlockers', []));
    $this->actingAs($test->user)->delete("/members/{$test->id}")->assertForbidden();

    $this->actingAs($secretary)->delete("/members/{$test->id}")->assertRedirect('/members');

    expect(Member::query()->find($test->id))->toBeNull()
        ->and(User::query()->find($userId))->toBeNull()
        ->and(DB::table('position_terms')->where('member_id', $test->id)->exists())->toBeFalse();

    $this->actingAs($secretary)->post('/members', ['name' => 'Real Person', 'phone' => '0799000222', 'joined_on' => '2026-10-29', 'locale' => 'en']);
    expect(Member::query()->where('name', 'Real Person')->value('member_number'))->toBe('M002');
});

it('keeps anyone with money history, and never deletes your own record', function () {
    $secretary = $this->official('secretary');
    $member = $this->member();

    DB::table('payments')->insert([
        'group_id' => $member->group_id, 'member_id' => $member->id, 'channel' => 'cash', 'status' => 'allocated',
        'purpose' => 'contribution', 'amount' => 100000, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->actingAs($secretary)->get("/members/{$member->id}")->assertInertia(fn ($p) => $p->where('deleteBlockers', ['payments']));
    $this->actingAs($secretary)->delete("/members/{$member->id}")->assertSessionHasErrors('action');
    $this->actingAs($secretary)->delete("/members/{$secretary->member->id}")->assertSessionHasErrors('action');

    expect(Member::query()->find($member->id))->not->toBeNull();
});

// View as

it('lets someone who manages positions look at the app as another position without changing anything', function () {
    $chair = $this->official('chairperson');
    $secretary = Position::query()->where('slug', 'secretary')->firstOrFail();

    $this->actingAs($chair)->post("/positions/{$secretary->id}/view-as")->assertRedirect('/dashboard');

    $this->actingAs($chair)->get('/dashboard')
        ->assertInertia(fn ($p) => $p->where('auth.viewing_as', 'Secretary')->where('auth.permissions', $secretary->permissions));
    // The secretary cannot manage positions, so neither can the preview.
    $this->actingAs($chair)->get('/positions')->assertForbidden();

    $before = Member::query()->count();
    $this->actingAs($chair)->post('/members', ['name' => 'New Person', 'phone' => '0799000111', 'joined_on' => '2026-01-01', 'locale' => 'en'])
        ->assertRedirect()->assertSessionHas('inertia.flash_data.toast');
    expect(Member::query()->count())->toBe($before);

    $this->actingAs($chair)->post('/view-as/stop')->assertRedirect('/positions');
    $this->actingAs($chair)->get('/positions')->assertOk()->assertInertia(fn ($p) => $p->where('auth.viewing_as', null));
});

it('previews an ordinary member, and only for people who manage positions', function () {
    $chair = $this->official('chairperson');
    $member = $this->member()->user;

    $this->actingAs($chair)->post('/view-as/member');
    $this->actingAs($chair)->get('/members')->assertForbidden();
    $this->actingAs($chair)->get('/dashboard')->assertInertia(fn ($p) => $p->where('auth.viewing_as', 'an ordinary member')->where('auth.permissions', []));
    $this->actingAs($chair)->post('/view-as/stop');

    $secretary = Position::query()->where('slug', 'secretary')->firstOrFail();
    $this->actingAs($member)->post("/positions/{$secretary->id}/view-as")->assertForbidden();
    expect(session()->has(ViewAs::SESSION_KEY))->toBeFalse();
});

// Import

it('imports members from a CSV, skipping people already on the register', function () {
    $secretary = $this->official('secretary');
    $this->member(['phone' => '0700111222', 'name' => 'Already Here']);

    $csv = "\xEF\xBB\xBFName,Phone,Member number,Email,Gender,Date of birth,Joined on\n"
        ."Jane Wanjiku,0712 345 678,,jane@example.com,F,14/05/1990,2024-01-01\n"
        ."John Kamau,+254722000333,M200,,male,,\n"
        ."Already Here,0700111222,,,,,\n";

    $this->actingAs($secretary)->post('/members/import', [
        'file' => UploadedFile::fake()->createWithContent('members.csv', $csv),
        'joined_on' => '2026-03-01',
        'send_logins' => true,
    ])->assertRedirect('/members')->assertSessionHasNoErrors();

    $jane = Member::query()->where('name', 'Jane Wanjiku')->firstOrFail();
    $john = Member::query()->where('member_number', 'M200')->firstOrFail();

    expect($jane->phone)->toBe('254712345678')
        ->and($jane->gender)->toBe('female')
        ->and($jane->date_of_birth->toDateString())->toBe('1990-05-14')
        ->and($jane->user_id)->not->toBeNull()
        ->and($john->joined_on->toDateString())->toBe('2026-03-01')
        ->and(Member::query()->where('phone', '254700111222')->count())->toBe(1)
        ->and(OutboundMessage::query()->where('template_key', 'account_created')->count())->toBeGreaterThanOrEqual(2);
});

it('imports nothing when any row is wrong, and says which rows', function () {
    $secretary = $this->official('secretary');
    $before = Member::query()->count();

    $csv = "Name,Phone\nGood Person,0711222333\n,0711222444\nBad Phone,12345\n";

    $this->actingAs($secretary)->post('/members/import', [
        'file' => UploadedFile::fake()->createWithContent('members.csv', $csv),
        'joined_on' => '2026-03-01',
    ])->assertSessionHasErrors('action');

    expect(Member::query()->count())->toBe($before)
        ->and(session('errors')->first('action'))->toContain('Row 3')->toContain('Row 4');

    $this->actingAs($this->member()->user)->get('/members/import')->assertForbidden();
});

// Votes

it('runs a secret vote between meetings', function () {
    $secretary = $this->official('secretary');
    $alice = $this->member(['name' => 'Alice Test']);
    $bob = $this->member(['name' => 'Bob Test']);

    $this->actingAs($alice->user)->post('/votes', ['question' => 'x', 'closes_on' => now()->toDateString()])->assertForbidden();

    $this->actingAs($secretary)->post('/votes', [
        'question' => 'Raise contributions to KES 2,000?',
        'options' => ['', ''],
        'closes_on' => now()->addDays(3)->toDateString(),
        'notify' => true,
    ])->assertSessionHasNoErrors();

    $poll = Poll::query()->firstOrFail();
    expect($poll->options)->toBe(['Yes', 'No'])
        ->and(OutboundMessage::query()->where('template_key', 'poll_opened')->where('member_id', $alice->id)->exists())->toBeTrue()
        ->and(InboxItem::query()->where('user_id', $bob->user_id)->where('url', "/votes/{$poll->id}")->exists())->toBeTrue();

    // No peeking before voting.
    $this->actingAs($bob->user)->get("/votes/{$poll->id}")->assertInertia(fn ($p) => $p->where('results', null)->where('voters', null)->where('canVote', true));

    $this->actingAs($alice->user)->post("/votes/{$poll->id}/vote", ['choice' => 1])->assertSessionHasNoErrors();
    $this->actingAs($alice->user)->post("/votes/{$poll->id}/vote", ['choice' => 0])->assertSessionHasNoErrors();
    $this->actingAs($bob->user)->post("/votes/{$poll->id}/vote", ['choice' => 0]);
    $this->actingAs($bob->user)->post("/votes/{$poll->id}/vote", ['choice' => 5])->assertSessionHasErrors('choice');

    expect($poll->tally())->toBe([['option' => 'Yes', 'votes' => 2], ['option' => 'No', 'votes' => 0]]);

    $this->actingAs($secretary)->get("/votes/{$poll->id}")
        ->assertInertia(fn ($p) => $p->where('voters', ['Alice Test', 'Bob Test'])->where('turnout.votes', 2));

    $this->actingAs($secretary)->post("/votes/{$poll->id}/close")->assertSessionHasNoErrors();
    $this->actingAs($bob->user)->post("/votes/{$poll->id}/vote", ['choice' => 1])->assertSessionHasErrors('action');
    $this->actingAs($bob->user)->get('/votes')->assertInertia(fn ($p) => $p->where('polls.0.open', false)->where('polls.0.voted', true));
});

// Notifications

it('files every templated message under the bell with a link, but never login passwords', function () {
    $member = $this->member();
    $notifier = app(Notifier::class);

    $notifier->toMember($member, 'guarantor_request', ['borrower' => 'Jane', 'amount' => 'KES 1,000', 'loan_amount' => 'KES 5,000', 'link' => url('/loans/7')]);
    $notifier->toMember($member, 'payment_received', ['amount' => 'KES 500', 'purpose' => 'contribution', 'receipt' => 'ABC']);
    $notifier->toMember($member, 'account_created', ['login' => '0711', 'password' => 'Secret-1234-ABCD', 'link' => url('/login')]);

    $items = InboxItem::query()->where('user_id', $member->user_id)->orderBy('id')->get();

    expect($items)->toHaveCount(2)
        ->and($items[0]->url)->toBe('/loans/7')
        ->and($items[0]->title)->toContain('Jane')
        ->and($items[1]->url)->toBe("/statements/{$member->id}")
        // The loan's own link reaches the SMS, not the plain site address.
        ->and(OutboundMessage::query()->where('template_key', 'guarantor_request')->where('channel', 'sms')->value('body'))->toContain('/loans/7')
        ->and(InboxItem::query()->where('body', 'like', '%Secret-1234%')->exists())->toBeFalse();

    $this->actingAs($member->user)->get('/dashboard')->assertInertia(fn ($p) => $p->where('auth.unread', 2));
    $this->actingAs($member->user)->post("/inbox/{$items[0]->id}/open")->assertRedirect('/loans/7');
    expect($items[0]->refresh()->read_at)->not->toBeNull();

    $this->actingAs($member->user)->post('/inbox/read-all');
    $this->actingAs($member->user)->get('/inbox')->assertOk()->assertInertia(fn ($p) => $p->component('inbox/index')->has('items', 2)->where('auth.unread', 0));

    $other = $this->member();
    $this->actingAs($other->user)->post("/inbox/{$items[0]->id}/open")->assertNotFound();
});

// Birthdays

it('sends a birthday SMS on the day, including 29 February birthdays in other years', function () {
    $this->group();
    $this->member(['date_of_birth' => '1990-11-03']);
    $this->member(['date_of_birth' => '1988-02-29']);
    $this->member(['date_of_birth' => '1990-11-04']);
    $this->member(['date_of_birth' => '1990-11-03', 'reminders_opt_out' => true]);

    $this->artisan('kertai:daily', ['--date' => '2026-11-03'])->assertSuccessful();
    expect(OutboundMessage::query()->where('template_key', 'birthday')->count())->toBe(1);

    $this->artisan('kertai:daily', ['--date' => '2027-02-28'])->assertSuccessful();
    expect(OutboundMessage::query()->where('template_key', 'birthday')->count())->toBe(2);

    $this->group(['birthday_greetings' => false]);
    $this->artisan('kertai:daily', ['--date' => '2027-11-03'])->assertSuccessful();
    expect(OutboundMessage::query()->where('template_key', 'birthday')->count())->toBe(2);
});

// Change requests

it('lets a member ask to change their details and applies them once the secretary approves', function () {
    $secretary = $this->official('secretary');
    $member = $this->member(['phone' => '0711000999', 'nominee_name' => 'Old Nominee']);

    $this->actingAs($member->user)->post('/me/changes', ['phone' => '0711000999', 'nominee_name' => 'Old Nominee'])->assertSessionHasErrors('action');

    $this->actingAs($member->user)->post('/me/changes', [
        'phone' => '0722 555 444',
        'email' => $member->email,
        'nominee_name' => 'Mary Atieno',
        'nominee_phone' => '0733111222',
        'date_of_birth' => '1985-07-01',
    ])->assertSessionHasNoErrors();

    $change = MemberChangeRequest::query()->firstOrFail();
    expect($change->changes)->toBe([
        'phone' => '254722555444',
        'date_of_birth' => '1985-07-01',
        'nominee_name' => 'Mary Atieno',
        'nominee_phone' => '254733111222',
    ])->and($member->refresh()->phone)->toBe('254711000999')
        ->and(OutboundMessage::query()->where('template_key', 'change_request_new')->where('member_id', $secretary->member->id)->exists())->toBeTrue();

    $this->actingAs($member->user)->post("/member-changes/{$change->id}/decide", ['decision' => 'approved'])->assertForbidden();
    $this->actingAs($secretary)->get('/members')->assertInertia(fn ($p) => $p->where('pendingChanges.0.member_id', $member->id));
    $this->actingAs($secretary)->get("/members/{$member->id}")->assertInertia(fn ($p) => $p->where('changeRequest.id', $change->id)->where('canDecideChanges', true));

    $this->actingAs($secretary)->post("/member-changes/{$change->id}/decide", ['decision' => 'approved'])->assertSessionHasNoErrors();

    $member->refresh();
    expect($member->phone)->toBe('254722555444')
        ->and($member->user->phone)->toBe('254722555444')
        ->and($member->email)->not->toBeNull()
        ->and($member->nominee_name)->toBe('Mary Atieno')
        ->and($member->date_of_birth->toDateString())->toBe('1985-07-01')
        ->and($change->refresh()->status)->toBe('approved')
        ->and(OutboundMessage::query()->where('template_key', 'change_request_decided')->where('member_id', $member->id)->exists())->toBeTrue();
});

it('rejects a change with a reason, and refuses a phone number someone else has', function () {
    $secretary = $this->official('secretary');
    $member = $this->member();
    $taken = $this->member(['phone' => '0733999888']);

    $this->actingAs($member->user)->post('/me/changes', ['phone' => '0733999888'])->assertSessionHasErrors('phone');

    $this->actingAs($member->user)->post('/me/changes', ['nominee_name' => 'Someone'])->assertSessionHasNoErrors();
    $change = MemberChangeRequest::query()->firstOrFail();

    $this->actingAs($secretary)->post("/member-changes/{$change->id}/decide", ['decision' => 'rejected', 'reason' => 'Bring the ID first'])->assertSessionHasNoErrors();
    expect($change->refresh()->status)->toBe('rejected')
        ->and($member->refresh()->nominee_name)->toBeNull()
        ->and($taken->phone)->toBe('254733999888');

    $this->actingAs($secretary)->post("/member-changes/{$change->id}/decide", ['decision' => 'approved'])->assertSessionHasErrors('action');

    // A second request replaces the first one still waiting.
    $this->actingAs($member->user)->post('/me/changes', ['nominee_name' => 'A']);
    $this->actingAs($member->user)->post('/me/changes', ['nominee_name' => 'B']);
    expect(MemberChangeRequest::query()->where('status', 'pending')->count())->toBe(1)
        ->and(User::query()->count())->toBeGreaterThan(0);
});
