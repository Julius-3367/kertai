<?php

use App\Domain\Fundraisers\Models\Fundraiser;
use App\Domain\Ledger\Ledger;
use App\Domain\Notifications\Models\OutboundMessage;
use App\Domain\Payments\Models\Payment;

beforeEach(function () {
    $this->coordinator = $this->official('coordinator');
});

function createFundraiser(array $overrides = []): Fundraiser
{
    test()->actingAs(test()->coordinator)->post('/fundraisers', [
        'title' => 'Empowerment day',
        'summary' => 'Seed capital for members',
        'description' => 'Join us on 20 November.',
        'beneficiary' => 'KERTAI006 members',
        'target' => '300000',
        'starts_on' => now()->subDay()->toDateString(),
        'ends_on' => now()->addMonth()->toDateString(),
        'show_contributors' => true,
        ...$overrides,
    ])->assertSessionHasNoErrors();

    return Fundraiser::query()->latest('id')->firstOrFail();
}

it('starts as a hidden draft and goes public when published', function () {
    $fundraiser = createFundraiser();

    expect($fundraiser->status)->toBe('draft')
        ->and($fundraiser->slug)->toBe('empowerment-day')
        ->and($fundraiser->target)->toBe(30000000)
        ->and($fundraiser->account_id)->not->toBeNull();

    $this->get('/f/empowerment-day')->assertNotFound();

    $this->actingAs($this->coordinator)->post("/fundraisers/{$fundraiser->id}/status", ['status' => 'active']);
    auth()->logout();

    $this->get('/f/empowerment-day')->assertOk()->assertInertia(fn ($p) => $p->component('public/fundraiser')->where('fundraiser.open', true)->where('fundraiser.raised', 0));

    expect(createFundraiser()->slug)->toBe('empowerment-day-2');
});

it('takes M-Pesa from the public, thanks the giver and lists only those who agreed', function () {
    $fundraiser = createFundraiser();
    $this->actingAs($this->coordinator)->post("/fundraisers/{$fundraiser->id}/status", ['status' => 'active']);
    auth()->logout();

    $this->post('/f/empowerment-day/pay', ['name' => 'Mary Wanjiku', 'phone' => '0799111222', 'amount' => 2000, 'show_name' => true])->assertSessionHasNoErrors();
    $this->post('/f/empowerment-day/pay', ['name' => 'Shy Giver', 'phone' => '0799333444', 'amount' => 500, 'show_name' => false])->assertSessionHasNoErrors();

    $payment = Payment::query()->where('phone', '254799111222')->firstOrFail();
    $this->getJson("/f/empowerment-day/payments/{$payment->idempotency_key}")->assertJson(['status' => 'processing']);

    $this->travel(10)->seconds();
    $this->getJson("/f/empowerment-day/payments/{$payment->idempotency_key}")->assertJson(['status' => 'succeeded']);
    $other = Payment::query()->where('phone', '254799333444')->firstOrFail();
    $this->getJson("/f/empowerment-day/payments/{$other->idempotency_key}")->assertJson(['status' => 'succeeded']);

    expect(app(Ledger::class)->balance($fundraiser->fundAccount()))->toBe(250000)
        ->and(OutboundMessage::query()->where('recipient', '254799111222')->first()->body)->toContain('Thank you Mary');

    $this->get('/f/empowerment-day')->assertInertia(fn ($p) => $p
        ->where('fundraiser.raised', 250000)
        ->where('fundraiser.count', 2)
        ->has('contributors', 1)
        ->where('contributors.0.name', 'Mary W.'));
});

it('refuses payments once closed and rejects bad numbers', function () {
    $fundraiser = createFundraiser();
    $this->actingAs($this->coordinator)->post("/fundraisers/{$fundraiser->id}/status", ['status' => 'active']);
    auth()->logout();

    $this->post('/f/empowerment-day/pay', ['name' => 'X', 'phone' => '12345', 'amount' => 100])->assertSessionHasErrors('action');

    $fundraiser->update(['status' => 'closed']);
    $this->post('/f/empowerment-day/pay', ['name' => 'X', 'phone' => '0799111222', 'amount' => 100])->assertSessionHasErrors('action');
    $this->get('/f/empowerment-day')->assertInertia(fn ($p) => $p->where('fundraiser.open', false));

    expect(Payment::query()->count())->toBe(0);
});

it('tracks pledges, exports contributions and can be edited', function () {
    $fundraiser = createFundraiser();
    $member = $this->member();
    $treasurer = $this->official('treasurer');

    $this->actingAs($this->coordinator)->post("/fundraisers/{$fundraiser->id}/pledges", ['member_id' => $member->id, 'amount' => '5000'])->assertSessionHasNoErrors();
    $this->actingAs($this->coordinator)->post("/fundraisers/{$fundraiser->id}/pledges", ['name' => 'Uncle Kip', 'phone' => '0700111222', 'amount' => '10000'])->assertSessionHasNoErrors();

    $this->actingAs($treasurer)->post('/payments', [
        'channel' => 'cash', 'amount' => '2000', 'purpose' => 'fundraiser', 'target_id' => $fundraiser->id,
        'member_id' => $member->id, 'received_on' => now()->toDateString(),
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->coordinator)->get("/fundraisers/{$fundraiser->id}")
        ->assertInertia(fn ($p) => $p->has('pledges', 2)->where('fundraiser.raised', 200000)->where('stats.pledged', 1500000));

    $csv = $this->actingAs($treasurer)->get("/fundraisers/{$fundraiser->id}/export");
    $csv->assertOk();
    expect($csv->streamedContent())->toContain('2000.00');

    $this->actingAs($this->coordinator)->put("/fundraisers/{$fundraiser->id}", [
        'title' => 'Empowerment day 2026', 'target' => '350000', 'starts_on' => now()->toDateString(),
    ])->assertSessionHasNoErrors();
    expect($fundraiser->refresh()->target)->toBe(35000000);

    $pledge = $fundraiser->pledges()->first();
    $this->actingAs($this->coordinator)->delete("/fundraisers/{$fundraiser->id}/pledges/{$pledge->id}")->assertSessionHasNoErrors();

    $this->actingAs($member->user)->get('/fundraisers')->assertOk();
    $this->actingAs($member->user)->get("/fundraisers/{$fundraiser->id}")->assertOk()->assertInertia(fn ($p) => $p->where('payments', []));
    $this->actingAs($member->user)->post('/fundraisers', [])->assertForbidden();
});
