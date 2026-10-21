<?php

use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Ledger\Ledger;
use App\Domain\Payouts\Models\Payout;

function fundGroupFund(int $cents): void
{
    $group = test()->group();
    $accounts = app(ChartOfAccounts::class);

    app(Ledger::class)->post('Opening', [
        ['account' => $accounts->get($group, ChartOfAccounts::BANK), 'debit' => $cents],
        ['account' => $accounts->get($group, ChartOfAccounts::GROUP_FUND), 'credit' => $cents],
    ]);
}

function groupFundId(): int
{
    return app(ChartOfAccounts::class)->get(test()->group(), ChartOfAccounts::GROUP_FUND)->id;
}

beforeEach(function () {
    $this->treasurer = $this->official('treasurer');
    $this->chair = $this->official('chairperson');
    $this->secretary = $this->official('secretary');
    fundGroupFund(1000000);
});

function requestPayout(array $overrides = []): Payout
{
    test()->actingAs(test()->treasurer)->post('/payouts', [
        'type' => 'expense',
        'account_id' => groupFundId(),
        'amount' => '2500',
        'description' => 'Hall hire for AGM',
        'payee_name' => 'Kertai Primary School',
        'payee_phone' => '0733000111',
        ...$overrides,
    ])->assertSessionHasNoErrors();

    return Payout::query()->latest('id')->firstOrFail();
}

it('needs two different officials, neither of them the requester', function () {
    $payout = requestPayout();

    expect($payout->status)->toBe('pending')->and($payout->number)->toBe(1);

    $this->actingAs($this->treasurer)->post("/payouts/{$payout->id}/decide", ['decision' => 'approved'])->assertSessionHasErrors('action');
    $this->actingAs($this->member()->user)->post("/payouts/{$payout->id}/decide", ['decision' => 'approved'])->assertForbidden();

    $this->actingAs($this->chair)->post("/payouts/{$payout->id}/decide", ['decision' => 'approved']);
    expect($payout->refresh()->status)->toBe('pending');

    $this->actingAs($this->chair)->post("/payouts/{$payout->id}/decide", ['decision' => 'approved'])->assertSessionHasErrors('action');

    $this->actingAs($this->secretary)->post("/payouts/{$payout->id}/decide", ['decision' => 'approved']);
    expect($payout->refresh()->status)->toBe('approved')->and($payout->approvals()->count())->toBe(2);
});

it('cannot be paid before approval, and moves the books when paid', function () {
    $payout = requestPayout();

    $this->actingAs($this->treasurer)->post("/payouts/{$payout->id}/pay", ['method' => 'mpesa', 'reference' => 'RKX1'])->assertSessionHasErrors('action');

    $this->actingAs($this->chair)->post("/payouts/{$payout->id}/decide", ['decision' => 'approved']);
    $this->actingAs($this->secretary)->post("/payouts/{$payout->id}/decide", ['decision' => 'approved']);
    $this->actingAs($this->chair)->post("/payouts/{$payout->id}/pay", ['method' => 'mpesa', 'reference' => 'RKX1'])->assertForbidden();
    $this->actingAs($this->treasurer)->post("/payouts/{$payout->id}/pay", ['method' => 'bank', 'reference' => 'EQ-0091'])->assertSessionHasNoErrors();

    $accounts = app(ChartOfAccounts::class);
    $ledger = app(Ledger::class);

    expect($payout->refresh()->status)->toBe('paid')
        ->and($payout->journal_entry_id)->not->toBeNull()
        ->and($ledger->balance($accounts->get($this->group(), ChartOfAccounts::GROUP_FUND)))->toBe(750000)
        ->and($ledger->balance($accounts->get($this->group(), ChartOfAccounts::BANK)))->toBe(750000);
});

it('is rejected outright by any one official', function () {
    $payout = requestPayout();

    $this->actingAs($this->chair)->post("/payouts/{$payout->id}/decide", ['decision' => 'rejected'])->assertSessionHasErrors('comment');
    $this->actingAs($this->chair)->post("/payouts/{$payout->id}/decide", ['decision' => 'rejected', 'comment' => 'Too dear']);

    expect($payout->refresh()->status)->toBe('rejected');
});

it('refuses more than the fund holds', function () {
    $this->actingAs($this->treasurer)->post('/payouts', [
        'type' => 'expense',
        'account_id' => groupFundId(),
        'amount' => '20000',
        'description' => 'Too much',
        'payee_name' => 'Someone',
    ])->assertSessionHasErrors('action');

    expect(Payout::query()->count())->toBe(0);
});

it('stops an official approving a payout to themself', function () {
    $accounts = app(ChartOfAccounts::class);
    $chairMember = $this->chair->member;
    app(Ledger::class)->post('Savings', [
        ['account' => $accounts->get($this->group(), ChartOfAccounts::BANK), 'debit' => 50000, 'member_id' => $chairMember->id],
        ['account' => $accounts->get($this->group(), ChartOfAccounts::SAVINGS), 'credit' => 50000, 'member_id' => $chairMember->id],
    ]);

    $payout = requestPayout([
        'type' => 'other',
        'account_id' => $accounts->get($this->group(), ChartOfAccounts::SAVINGS)->id,
        'amount' => '300',
        'payee_member_id' => $chairMember->id,
        'payee_name' => '',
    ]);

    $this->actingAs($this->chair)->post("/payouts/{$payout->id}/decide", ['decision' => 'approved'])->assertSessionHasErrors('action');
});

it('can be cancelled by the requester and approved in bulk', function () {
    $a = requestPayout();
    $b = requestPayout(['amount' => '100']);
    $c = requestPayout(['amount' => '200']);

    $this->actingAs($this->treasurer)->post("/payouts/{$a->id}/cancel", ['reason' => 'Duplicate'])->assertSessionHasNoErrors();
    expect($a->refresh()->status)->toBe('cancelled');

    $this->actingAs($this->chair)->post('/payouts/approve-many', ['ids' => [$b->id, $c->id]]);
    $this->actingAs($this->secretary)->post('/payouts/approve-many', ['ids' => [$b->id, $c->id]]);

    expect($b->refresh()->status)->toBe('approved')->and($c->refresh()->status)->toBe('approved');
});

it('honours a three-approval rule', function () {
    $this->group(['payout_approvals_required' => 3]);
    $secondChair = $this->official('chairperson');
    $payout = requestPayout();

    $this->actingAs($this->chair)->post("/payouts/{$payout->id}/decide", ['decision' => 'approved']);
    $this->actingAs($this->secretary)->post("/payouts/{$payout->id}/decide", ['decision' => 'approved']);
    expect($payout->refresh()->status)->toBe('pending');

    $this->actingAs($secondChair)->post("/payouts/{$payout->id}/decide", ['decision' => 'approved']);
    expect($payout->refresh()->status)->toBe('approved');
});

it('shows the payout pages', function () {
    $payout = requestPayout();

    $this->actingAs($this->chair)->get('/payouts?status=mine')->assertOk()->assertInertia(fn ($p) => $p->component('payouts/index')->where('payouts.data.0.can_decide', true));
    $this->actingAs($this->chair)->get("/payouts/{$payout->id}")->assertOk()->assertInertia(fn ($p) => $p->where('can.decide', true)->where('can.pay', false));
    $this->actingAs($this->treasurer)->get("/payouts/{$payout->id}")->assertInertia(fn ($p) => $p->where('can.decide', false)->where('can.cancel', true));
});
