<?php

use App\Domain\Dues\Models\Due;
use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Ledger\Ledger;
use App\Domain\Mgr\Models\MgrCycle;
use App\Domain\Mgr\Models\MgrSlot;
use App\Domain\Mgr\Models\MgrSwap;
use App\Domain\Payouts\Models\Payout;

function fillPot(int $cents): void
{
    $accounts = app(ChartOfAccounts::class);
    $group = test()->group();

    app(Ledger::class)->post('Pot', [
        ['account' => $accounts->get($group, ChartOfAccounts::BANK), 'debit' => $cents],
        ['account' => $accounts->get($group, ChartOfAccounts::MGR), 'credit' => $cents],
    ]);
}

beforeEach(function () {
    $this->treasurer = $this->official('treasurer');
    $this->chair = $this->official('chairperson');
    $this->members = collect([$this->treasurer->member, $this->chair->member, $this->member(), $this->member()]);
});

function balloted(): MgrCycle
{
    test()->actingAs(test()->treasurer)->post('/mgr', ['name' => 'MGR 2027', 'starts_on' => '2027-01-10'])->assertRedirect();
    $cycle = MgrCycle::query()->firstOrFail();
    test()->actingAs(test()->treasurer)->post("/mgr/{$cycle->id}/ballot", ['member_ids' => test()->members->pluck('id')->all()])->assertSessionHasNoErrors();

    return $cycle->refresh();
}

it('ballots every member into a dated, monthly order', function () {
    $cycle = balloted();

    expect($cycle->status)->toBe('active')
        ->and($cycle->payout_amount)->toBe(4 * 50000)
        ->and($cycle->ballot_seed)->not->toBeNull()
        ->and($cycle->slots()->count())->toBe(4)
        ->and($cycle->slots()->pluck('scheduled_on')->map->toDateString()->all())->toBe(['2027-01-10', '2027-02-10', '2027-03-10', '2027-04-10']);

    $this->actingAs($this->treasurer)->post("/mgr/{$cycle->id}/ballot", ['member_ids' => $this->members->pluck('id')->all()])->assertSessionHasErrors('action');
    $this->actingAs($this->members[2]->user)->get("/mgr/{$cycle->id}")->assertOk()->assertInertia(fn ($p) => $p->has('slots', 4)->where('mySlotId', fn ($id) => $id !== null));
});

it('pays turns in order and sends members in arrears to the back', function () {
    $cycle = balloted();
    fillPot(1000000);
    $first = $cycle->slots()->first();

    Due::query()->create([
        'group_id' => $this->group()->id,
        'member_id' => $first->member_id,
        'type' => 'contribution',
        'description' => 'Old contribution',
        'amount' => 100000,
        'breakdown' => ['mgr' => 50000, 'welfare' => 20000, 'savings' => 30000],
        'due_on' => now()->subMonth()->toDateString(),
    ]);

    $this->actingAs($this->treasurer)->post("/mgr/{$cycle->id}/release")->assertSessionHasNoErrors();

    $payout = Payout::query()->where('type', 'mgr')->firstOrFail();
    $first->refresh();

    expect($payout->payee_member_id)->not->toBe($first->member_id)
        ->and($payout->amount)->toBe(200000)
        ->and($first->position)->toBe(4)
        ->and($first->deferred_count)->toBe(1)
        ->and(MgrSlot::query()->where('status', 'requested')->count())->toBe(1);

    $this->actingAs($this->treasurer)->post("/mgr/{$cycle->id}/release")->assertSessionHasErrors('action');

    // The ballot is random, so the payee may be one of the officials; any
    // two of the others can approve because the rules raised this payout.
    $secretary = $this->official('secretary');
    foreach ([$secretary, $this->chair, $this->treasurer] as $official) {
        if ($payout->refresh()->status === 'pending' && $official->member->id !== $payout->payee_member_id) {
            $this->actingAs($official)->post("/payouts/{$payout->id}/decide", ['decision' => 'approved'])->assertSessionHasNoErrors();
        }
    }
    $this->actingAs($this->treasurer)->post("/payouts/{$payout->id}/pay", ['method' => 'mpesa', 'reference' => 'MG1']);

    expect(MgrSlot::query()->where('payout_id', $payout->id)->value('status'))->toBe('paid');
});

it('lets two other officials approve an official\'s own turn', function () {
    $this->actingAs($this->treasurer)->post('/mgr', ['name' => 'Officials', 'starts_on' => '2027-01-10']);
    $cycle = MgrCycle::query()->firstOrFail();
    $this->actingAs($this->treasurer)->post("/mgr/{$cycle->id}/ballot", ['member_ids' => [$this->treasurer->member->id, $this->chair->member->id]]);
    fillPot(1000000);

    $this->actingAs($this->treasurer)->post("/mgr/{$cycle->id}/release")->assertSessionHasNoErrors();
    $payout = Payout::query()->firstOrFail();
    $other = $payout->payee_member_id === $this->chair->member->id ? $this->treasurer : $this->chair;
    $payee = $payout->payee_member_id === $this->chair->member->id ? $this->chair : $this->treasurer;
    $secretary = $this->official('secretary');

    expect($payout->requested_by)->toBeNull();

    // It shows in the approvers' queues, not the payee's.
    $this->actingAs($other)->get('/payouts?status=mine')->assertInertia(fn ($p) => $p->has('payouts.data', 1));
    $this->actingAs($payee)->get('/payouts?status=mine')->assertInertia(fn ($p) => $p->has('payouts.data', 0));
    $this->actingAs($other)->get('/dashboard')->assertInertia(fn ($p) => $p->where('finance.awaiting_my_approval', 1));

    $this->actingAs($payee)->post("/payouts/{$payout->id}/decide", ['decision' => 'approved'])->assertSessionHasErrors('action');
    $this->actingAs($other)->post("/payouts/{$payout->id}/decide", ['decision' => 'approved'])->assertSessionHasNoErrors();
    $this->actingAs($secretary)->post("/payouts/{$payout->id}/decide", ['decision' => 'approved'])->assertSessionHasNoErrors();

    expect($payout->refresh()->status)->toBe('approved');
});

it('puts a turn back in line when its payout is rejected', function () {
    $cycle = balloted();
    fillPot(1000000);

    $this->actingAs($this->treasurer)->post("/mgr/{$cycle->id}/release");
    $payout = Payout::query()->firstOrFail();
    $secretary = $this->official('secretary');
    $this->actingAs($secretary)->post("/payouts/{$payout->id}/decide", ['decision' => 'rejected', 'comment' => 'Wait'])->assertSessionHasNoErrors();

    expect(MgrSlot::query()->where('status', 'upcoming')->count())->toBe(4);
});

it('swaps turns with the other member\'s consent and the chair\'s approval', function () {
    $cycle = balloted();
    $alice = $this->members[2];
    $bob = $this->members[3];
    $aliceSlot = $cycle->slots()->where('member_id', $alice->id)->first();
    $bobSlot = $cycle->slots()->where('member_id', $bob->id)->first();
    [$alicePosition, $bobPosition] = [$aliceSlot->position, $bobSlot->position];

    $this->actingAs($alice->user)->post("/mgr/{$cycle->id}/swaps", ['target_slot_id' => $bobSlot->id, 'reason' => 'School fees in January'])->assertSessionHasNoErrors();
    $swap = MgrSwap::query()->firstOrFail();

    $this->actingAs($this->treasurer)->post("/mgr/swaps/{$swap->id}", ['accept' => true])->assertForbidden();
    $this->actingAs($bob->user)->post("/mgr/swaps/{$swap->id}", ['accept' => true])->assertSessionHasNoErrors();
    expect($swap->refresh()->status)->toBe('pending_chair');

    $this->actingAs($bob->user)->post("/mgr/swaps/{$swap->id}", ['accept' => true])->assertForbidden();
    $this->actingAs($this->chair)->post("/mgr/swaps/{$swap->id}", ['accept' => true])->assertSessionHasNoErrors();

    expect($swap->refresh()->status)->toBe('approved')
        ->and($aliceSlot->refresh()->position)->toBe($bobPosition)
        ->and($bobSlot->refresh()->position)->toBe($alicePosition);
});

it('lets the other member decline', function () {
    $cycle = balloted();
    $alice = $this->members[2];
    $bob = $this->members[3];
    $bobSlot = $cycle->slots()->where('member_id', $bob->id)->first();
    $before = $cycle->slots()->pluck('position', 'member_id')->all();

    $this->actingAs($alice->user)->post("/mgr/{$cycle->id}/swaps", ['target_slot_id' => $bobSlot->id]);
    $swap = MgrSwap::query()->firstOrFail();
    $this->actingAs($bob->user)->post("/mgr/swaps/{$swap->id}", ['accept' => false]);

    expect($swap->refresh()->status)->toBe('declined')
        ->and($cycle->slots()->pluck('position', 'member_id')->all())->toBe($before);
});

it('keeps ordinary members from running the merry-go-round', function () {
    $member = $this->members[2];

    $this->actingAs($member->user)->post('/mgr', ['name' => 'X', 'starts_on' => '2027-01-01'])->assertForbidden();
    $this->actingAs($member->user)->get('/mgr')->assertOk();
});
