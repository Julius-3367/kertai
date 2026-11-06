<?php

use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Ledger\Ledger;
use App\Domain\Loans\LoanBook;
use App\Domain\Loans\Models\Loan;
use App\Domain\Loans\Models\LoanTransaction;
use App\Domain\Payouts\Actions\RequestPayout;
use App\Domain\Payouts\Models\Payout;
use Carbon\CarbonImmutable;

function adjBooks(string $code, ?int $memberId = null): int
{
    return app(Ledger::class)->balance(app(ChartOfAccounts::class)->get(test()->group(), $code), $memberId);
}

function adjFund(string $code, int $shillings): void
{
    $accounts = app(ChartOfAccounts::class);

    app(Ledger::class)->post('Opening', [
        ['account' => $accounts->get(test()->group(), ChartOfAccounts::CASH), 'debit' => $shillings * 100],
        ['account' => $accounts->get(test()->group(), $code), 'credit' => $shillings * 100],
    ]);
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-12 09:00'));
    $this->treasurer = $this->official('treasurer');
    $this->chair = $this->official('chairperson');
    $this->secretary = $this->official('secretary');
    $this->alice = $this->member(['name' => 'Alice Chebet']);

    adjFund(ChartOfAccounts::MGR, 10000);
    adjFund(ChartOfAccounts::GROUP_FUND, 5000);

    $this->actingAs($this->treasurer)->post('/loans', [
        'member_id' => $this->alice->id,
        'principal' => '3000',
        'interest' => '0',
        'issued_on' => '2026-03-15',
        'interest_rate' => '10',
        'interest_method' => 'reducing',
        'interest_period' => 'month',
        'interest_paused' => true,
        'counted_in' => app(ChartOfAccounts::class)->get($this->group(), ChartOfAccounts::GROUP_FUND)->id,
        'resolution_reference' => 'AGM 2026/04',
    ])->assertSessionHasNoErrors();
    $this->loan = Loan::query()->firstOrFail();

    $this->payout = app(RequestPayout::class)->handle(
        $this->group(), 'mgr', 1000000,
        app(ChartOfAccounts::class)->get($this->group(), ChartOfAccounts::MGR),
        'November turn', $this->alice, raisedByRules: true,
    );
    $this->url = "/payouts/{$this->payout->id}";
});

function adjust(array $data): void
{
    test()->actingAs(test()->treasurer)->post(test()->url.'/adjustments', $data)->assertSessionHasNoErrors();
}

it('pays the member the turn less an old loan and the group share, plus a refund', function () {
    $owedBefore = app(LoanBook::class)->balance($this->loan)['total'];
    $cash = adjBooks(ChartOfAccounts::CASH);
    $group = adjBooks(ChartOfAccounts::GROUP_FUND);
    $pot = adjBooks(ChartOfAccounts::MGR);

    adjust(['kind' => 'loan', 'loan_id' => $this->loan->id, 'amount' => '2000', 'reason' => 'Old loan from March']);
    adjust(['kind' => 'group', 'amount' => '1000', 'reason' => 'Group share']);
    adjust(['kind' => 'reimburse', 'amount' => '500', 'reason' => 'Bought tea for the meeting']);

    expect($this->payout->refresh()->load('adjustments')->net())->toBe(750000);

    $this->actingAs($this->chair)->post("{$this->url}/decide", ['decision' => 'approved']);
    $this->actingAs($this->secretary)->post("{$this->url}/decide", ['decision' => 'approved']);
    $this->actingAs($this->treasurer)->post("{$this->url}/pay", ['method' => 'cash', 'reference' => 'V7'])->assertSessionHasNoErrors();

    expect($this->payout->refresh()->status)->toBe('paid')
        // KES 10,000 leaves the pot; only KES 7,500 leaves the cash box.
        ->and(adjBooks(ChartOfAccounts::MGR))->toBe($pot - 1000000)
        ->and(adjBooks(ChartOfAccounts::CASH))->toBe($cash - 750000)
        // The group keeps 1,000 and pays out the 500 refund.
        ->and(adjBooks(ChartOfAccounts::GROUP_FUND))->toBe($group + 100000 - 50000)
        ->and(app(LoanBook::class)->balance($this->loan)['total'])->toBe($owedBefore - 200000)
        ->and(LoanTransaction::query()->where('loan_id', $this->loan->id)->where('type', 'repayment')->value('description'))->toBe("Deducted from payout #{$this->payout->number}");

    $this->actingAs($this->treasurer)->get($this->url)->assertInertia(fn ($p) => $p->where('payout.net', 750000)->has('payout.adjustments', 3)->where('can.adjust', false));
});

it('sends the payout back for approval when the deductions change', function () {
    $this->actingAs($this->chair)->post("{$this->url}/decide", ['decision' => 'approved']);
    $this->actingAs($this->secretary)->post("{$this->url}/decide", ['decision' => 'approved']);
    expect($this->payout->refresh()->status)->toBe('approved');

    adjust(['kind' => 'group', 'amount' => '1000', 'reason' => 'Group share']);

    expect($this->payout->refresh()->status)->toBe('pending')
        ->and($this->payout->approvals()->count())->toBe(0)
        ->and($this->payout->adjusted_by)->toBe($this->treasurer->id);

    $this->actingAs($this->treasurer)->post("{$this->url}/pay", ['method' => 'cash', 'reference' => 'V8'])->assertSessionHasErrors('action');

    $adjustment = $this->payout->adjustments()->firstOrFail();
    $this->actingAs($this->treasurer)->delete("{$this->url}/adjustments/{$adjustment->id}")->assertSessionHasNoErrors();
    expect($this->payout->adjustments()->count())->toBe(0);
});

it('refuses deductions bigger than the payout or the loan, and keeps others out', function () {
    $this->actingAs($this->treasurer)->post("{$this->url}/adjustments", ['kind' => 'group', 'amount' => '10001', 'reason' => 'Too much'])->assertSessionHasErrors('action');
    $this->actingAs($this->treasurer)->post("{$this->url}/adjustments", ['kind' => 'loan', 'loan_id' => $this->loan->id, 'amount' => '3001', 'reason' => 'Loan'])->assertSessionHasErrors('action');

    adjust(['kind' => 'loan', 'loan_id' => $this->loan->id, 'amount' => '2500', 'reason' => 'Loan']);
    // 500 is left on the loan once 2,500 is already set aside.
    $this->actingAs($this->treasurer)->post("{$this->url}/adjustments", ['kind' => 'loan', 'loan_id' => $this->loan->id, 'amount' => '600', 'reason' => 'Loan again'])->assertSessionHasErrors('action');

    $this->actingAs($this->chair)->post("{$this->url}/adjustments", ['kind' => 'group', 'amount' => '100', 'reason' => 'X'])->assertForbidden();
    $this->actingAs($this->alice->user)->post("{$this->url}/adjustments", ['kind' => 'group', 'amount' => '100', 'reason' => 'X'])->assertForbidden();
});

it('stops a refund the group fund cannot cover', function () {
    adjust(['kind' => 'reimburse', 'amount' => '9000', 'reason' => 'Tent repairs']);

    $this->actingAs($this->chair)->post("{$this->url}/decide", ['decision' => 'approved']);
    $this->actingAs($this->secretary)->post("{$this->url}/decide", ['decision' => 'approved']);
    $this->actingAs($this->treasurer)->post("{$this->url}/pay", ['method' => 'cash', 'reference' => 'V9'])->assertSessionHasErrors('action');

    expect(Payout::query()->find($this->payout->id)->status)->toBe('approved');
});
