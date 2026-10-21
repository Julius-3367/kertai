<?php

use App\Domain\Dues\Models\Due;
use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Ledger\Ledger;
use App\Domain\Notifications\Models\OutboundMessage;
use App\Domain\Payouts\Models\Payout;
use App\Domain\Welfare\Models\WelfareCase;

it('runs a welfare case from opening to payment', function () {
    $secretary = $this->official('secretary');
    $treasurer = $this->official('treasurer');
    $chair = $this->official('chairperson');
    $member = $this->member();
    $others = [$this->member(), $this->member()];
    $parent = $member->dependants()->create(['name' => 'Mama Rose', 'relationship' => 'parent']);

    $accounts = app(ChartOfAccounts::class);
    app(Ledger::class)->post('Welfare opening', [
        ['account' => $accounts->get($this->group(), ChartOfAccounts::BANK), 'debit' => 5000000],
        ['account' => $accounts->get($this->group(), ChartOfAccounts::WELFARE), 'credit' => 5000000],
    ]);

    $this->actingAs($secretary)->post('/welfare', [
        'member_id' => $member->id,
        'dependant_id' => $parent->id,
        'event' => 'death',
        'event_on' => now()->toDateString(),
        'description' => 'Burial in Kericho',
    ])->assertRedirect();

    $case = WelfareCase::query()->firstOrFail();
    expect($case->beneficiary_name)->toBe('Mama Rose')
        ->and($case->relationship)->toBe('parent')
        ->and($case->benefit_amount)->toBe(2000000);

    $this->actingAs($treasurer)->post("/welfare/{$case->id}/payout")->assertSessionHasErrors('action');

    $this->actingAs($secretary)->post("/welfare/{$case->id}/levy", ['amount' => '500', 'due_on' => now()->addWeek()->toDateString()])->assertSessionHasNoErrors();
    $this->actingAs($secretary)->post("/welfare/{$case->id}/levy", ['amount' => '500', 'due_on' => now()->addWeek()->toDateString()])->assertSessionHasErrors('action');

    $levies = Due::query()->where('type', 'levy')->get();
    expect($levies)->toHaveCount(5)
        ->and($levies->pluck('member_id'))->not->toContain($member->id)
        ->and(OutboundMessage::query()->where('template_key', 'welfare_levy')->count())->toBeGreaterThanOrEqual(5);

    $this->actingAs($secretary)->post("/welfare/{$case->id}/decide", ['decision' => 'approved', 'benefit_amount' => '25000'])->assertSessionHasNoErrors();
    expect($case->refresh()->status)->toBe('approved')->and($case->benefit_amount)->toBe(2500000);

    $this->actingAs($treasurer)->post("/welfare/{$case->id}/payout")->assertRedirect();
    $payout = Payout::query()->where('type', 'welfare')->firstOrFail();
    expect($case->refresh()->status)->toBe('payout_requested')->and($payout->payee_member_id)->toBe($member->id);

    $this->actingAs($chair)->post("/payouts/{$payout->id}/decide", ['decision' => 'approved']);
    $this->actingAs($secretary)->post("/payouts/{$payout->id}/decide", ['decision' => 'approved']);
    $this->actingAs($treasurer)->post("/payouts/{$payout->id}/pay", ['method' => 'mpesa', 'reference' => 'WF1']);

    expect($case->refresh()->status)->toBe('paid');

    $this->actingAs($secretary)->get("/welfare/{$case->id}")->assertOk()->assertInertia(fn ($p) => $p->has('levies', 5)->where('levyTotals.expected', 250000));
    $this->actingAs($secretary)->get('/welfare')->assertOk()->assertInertia(fn ($p) => $p->has('cases', 1));
});

it('can reject a case, and keeps welfare from ordinary members', function () {
    $secretary = $this->official('secretary');
    $member = $this->member();

    $this->actingAs($secretary)->post('/welfare', ['member_id' => $member->id, 'event' => 'hospitalisation', 'event_on' => now()->toDateString()]);
    $case = WelfareCase::query()->firstOrFail();

    expect($case->benefit_amount)->toBe(1000000);

    $this->actingAs($secretary)->post("/welfare/{$case->id}/decide", ['decision' => 'rejected'])->assertSessionHasNoErrors();
    expect($case->refresh()->status)->toBe('rejected');

    $this->actingAs($member->user)->get('/welfare')->assertForbidden();
});
