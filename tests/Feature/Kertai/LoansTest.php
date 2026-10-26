<?php

use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Ledger\Ledger;
use App\Domain\Loans\LoanBook;
use App\Domain\Loans\Models\Loan;
use App\Domain\Loans\Models\LoanGuarantor;
use App\Domain\Members\Models\Member;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payouts\Models\Payout;
use Carbon\CarbonImmutable;

function books(string $code, ?int $memberId = null): int
{
    return app(Ledger::class)->balance(app(ChartOfAccounts::class)->get(test()->group(), $code), $memberId);
}

/** Opening savings for a member, held in the bank. */
function saved(Member $member, int $shillings): void
{
    $accounts = app(ChartOfAccounts::class);

    app(Ledger::class)->post('Opening savings', [
        ['account' => $accounts->get(test()->group(), ChartOfAccounts::BANK), 'debit' => $shillings * 100],
        ['account' => $accounts->get(test()->group(), ChartOfAccounts::SAVINGS), 'credit' => $shillings * 100, 'member_id' => $member->id],
    ]);
}

function groupFundAccount(): int
{
    return app(ChartOfAccounts::class)->get(test()->group(), ChartOfAccounts::GROUP_FUND)->id;
}

function oldLoan(Member $member, array $overrides = []): Loan
{
    test()->actingAs(test()->treasurer)->post('/loans', [
        'member_id' => $member->id,
        'principal' => '10000',
        'interest' => '0',
        'issued_on' => '2026-03-15',
        'interest_rate' => '10',
        'interest_method' => 'reducing',
        'interest_period' => 'month',
        'interest_paused' => false,
        'counted_in' => groupFundAccount(),
        'resolution_reference' => 'AGM 2026/04',
        ...$overrides,
    ])->assertSessionHasNoErrors();

    return Loan::query()->latest('id')->firstOrFail();
}

function owed(Loan $loan): array
{
    return app(LoanBook::class)->balance($loan);
}

function payLoan(Loan $loan, string $shillings): Payment
{
    test()->actingAs(test()->treasurer)->post('/payments', [
        'channel' => 'cash',
        'amount' => $shillings,
        'purpose' => 'loan',
        'target_id' => $loan->id,
        'received_on' => now()->toDateString(),
    ])->assertSessionHasNoErrors();

    return Payment::query()->latest('id')->firstOrFail();
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-12 09:00'));
    $this->treasurer = $this->official('treasurer');
    $this->chair = $this->official('chairperson');
    $this->secretary = $this->official('secretary');
    $this->borrower = $this->member(['name' => 'Cheruiyot Langat']);
});

it('records an old loan with principal and interest kept apart, for the treasurer only', function () {
    $this->actingAs($this->borrower->user)->post('/loans', [])->assertForbidden();
    $this->actingAs($this->chair)->post('/loans', [])->assertForbidden();

    $loan = oldLoan($this->borrower, ['principal' => '20000', 'interest' => '3000']);

    expect($loan->reference())->toBe('L-001')
        ->and($loan->status)->toBe('active')
        ->and($loan->source)->toBe('brought_forward')
        ->and(owed($loan))->toBe(['principal' => 2000000, 'interest' => 300000, 'total' => 2300000])
        ->and(books(ChartOfAccounts::LOANS, $this->borrower->id))->toBe(2300000)
        ->and(books(ChartOfAccounts::GROUP_FUND))->toBe(2300000)
        ->and($loan->next_interest_on->toDateString())->toBe('2026-11-15');

    $this->actingAs($this->treasurer)->get('/loans')->assertOk()
        ->assertInertia(fn ($p) => $p->component('loans/index')->where('summary.principal', 2000000)->where('summary.interest', 300000));
});

it('charges monthly interest only while it is on, and never for the months it was off', function () {
    $group = $this->group(['loan_interest_on' => true]);
    $loan = oldLoan($this->borrower);
    $book = app(LoanBook::class);

    expect($book->accrue($group, CarbonImmutable::parse('2026-11-14')))->toBe(0)
        ->and($book->accrue($group, CarbonImmutable::parse('2026-11-15')))->toBe(1)
        ->and($book->accrue($group, CarbonImmutable::parse('2026-11-15')))->toBe(0)
        ->and(owed($loan)['interest'])->toBe(100000);

    // The group switches interest off: December is skipped for good.
    $group = $this->group(['loan_interest_on' => false]);
    expect($book->accrue($group, CarbonImmutable::parse('2026-12-15')))->toBe(0);

    $group = $this->group(['loan_interest_on' => true]);
    expect($book->accrue($group, CarbonImmutable::parse('2027-01-15')))->toBe(1)
        ->and(owed($loan)['interest'])->toBe(200000);

    // The chair stops interest on this one loan.
    $this->actingAs($this->treasurer)->post("/loans/{$loan->id}/interest", ['interest_rate' => 10, 'interest_method' => 'reducing', 'interest_period' => 'month', 'interest_paused' => true, 'reason' => 'x'])->assertForbidden();
    $this->actingAs($this->chair)->post("/loans/{$loan->id}/interest", [
        'interest_rate' => 10, 'interest_method' => 'reducing', 'interest_period' => 'month', 'interest_paused' => true, 'reason' => 'Principal only, AGM 2026/05',
    ])->assertSessionHasNoErrors();

    expect($book->accrue($group, CarbonImmutable::parse('2027-02-15')))->toBe(0)
        ->and(owed($loan)['interest'])->toBe(200000)
        ->and(books(ChartOfAccounts::LOANS, $this->borrower->id))->toBe(1200000);
});

it('works interest out on the reducing balance or flat on the amount lent', function () {
    $group = $this->group(['loan_interest_on' => true]);
    $reducing = oldLoan($this->borrower);
    $flat = oldLoan($this->member(), ['interest_method' => 'flat']);

    payLoan($reducing, '4000');
    payLoan($flat, '4000');
    app(LoanBook::class)->accrue($group, CarbonImmutable::parse('2026-11-15'));

    expect(owed($reducing)['interest'])->toBe(60000)
        ->and(owed($flat)['interest'])->toBe(100000);
});

it('applies repayments to principal first by default, or interest first when the rules say so', function () {
    $loan = oldLoan($this->borrower, ['principal' => '10000', 'interest' => '2000']);

    payLoan($loan, '5000');
    expect(owed($loan))->toBe(['principal' => 500000, 'interest' => 200000, 'total' => 700000]);

    $this->group(['loan_repayment_order' => 'interest_first']);
    payLoan($loan, '3000');
    expect(owed($loan))->toBe(['principal' => 400000, 'interest' => 0, 'total' => 400000]);

    payLoan($loan, '5000');

    expect($loan->refresh()->status)->toBe('cleared')
        ->and(owed($loan)['total'])->toBe(0)
        ->and(books(ChartOfAccounts::LOANS, $this->borrower->id))->toBe(0)
        ->and(books(ChartOfAccounts::PREPAYMENTS, $this->borrower->id))->toBe(100000)
        ->and(books(ChartOfAccounts::CASH))->toBe(1300000);
});

it('lets the borrower pay their loan by M-Pesa prompt', function () {
    $loan = oldLoan($this->borrower, ['principal' => '1000']);

    $this->actingAs($this->borrower->user)->post('/me/pay', ['amount' => 600, 'phone' => '0712345678', 'purpose' => 'loan', 'target_id' => $loan->id])
        ->assertSessionHasNoErrors();
    $payment = Payment::query()->latest('id')->firstOrFail();
    $this->actingAs($this->borrower->user)->post("/payments/{$payment->id}/check");

    expect($payment->refresh()->status)->toBe('succeeded')
        ->and(owed($loan)['principal'])->toBe(40000)
        ->and(books(ChartOfAccounts::MPESA))->toBe(60000);
});

it('lets only the chair waive interest or write a loan off, at the group fund\'s cost', function () {
    $loan = oldLoan($this->borrower, ['principal' => '10000', 'interest' => '2000']);
    LoanGuarantor::create(['loan_id' => $loan->id, 'member_id' => $this->member()->id, 'amount' => 500000, 'status' => 'accepted']);

    $this->actingAs($this->treasurer)->post("/loans/{$loan->id}/waive", ['part' => 'interest', 'reason' => 'x'])->assertForbidden();

    $this->actingAs($this->chair)->post("/loans/{$loan->id}/waive", ['part' => 'interest', 'reason' => 'Principal only, AGM 2026/05'])->assertSessionHasNoErrors();
    expect(owed($loan))->toBe(['principal' => 1000000, 'interest' => 0, 'total' => 1000000])
        ->and(books(ChartOfAccounts::GROUP_FUND))->toBe(1000000);

    $this->actingAs($this->chair)->post("/loans/{$loan->id}/waive", ['part' => 'all', 'reason' => 'Member cannot pay, AGM 2026/06'])->assertSessionHasNoErrors();
    expect($loan->refresh()->status)->toBe('written_off')
        ->and(books(ChartOfAccounts::GROUP_FUND))->toBe(0)
        ->and($loan->guarantors()->first()->status)->toBe('released');
});

it('recovers from a guarantor\'s savings only up to what they guaranteed', function () {
    $guarantor = $this->member(['name' => 'Cosmas Kiprono']);
    saved($guarantor, 10000);
    $loan = oldLoan($this->borrower, ['principal' => '8000']);
    LoanGuarantor::create(['loan_id' => $loan->id, 'member_id' => $guarantor->id, 'amount' => 300000, 'status' => 'accepted']);
    $stranger = $this->member();
    saved($stranger, 10000);

    $this->actingAs($this->chair)->post("/loans/{$loan->id}/recover", ['member_id' => $stranger->id, 'amount' => '100', 'reason' => 'x'])->assertSessionHasErrors('action');
    $this->actingAs($this->chair)->post("/loans/{$loan->id}/recover", ['member_id' => $guarantor->id, 'amount' => '4000', 'reason' => 'Default'])->assertSessionHasErrors('action');
    $this->actingAs($this->chair)->post("/loans/{$loan->id}/recover", ['member_id' => $guarantor->id, 'amount' => '3000', 'reason' => 'Default'])->assertSessionHasNoErrors();

    expect(books(ChartOfAccounts::SAVINGS, $guarantor->id))->toBe(700000)
        ->and(owed($loan)['principal'])->toBe(500000)
        ->and(app(LoanBook::class)->guaranteed($guarantor))->toBe(0);

    $this->actingAs($this->chair)->post("/loans/{$loan->id}/recover", ['member_id' => $guarantor->id, 'amount' => '1', 'reason' => 'Default'])->assertSessionHasErrors('action');
});

it('checks the lending rules one by one before a member can apply', function () {
    $this->borrower->forceFill(['joined_on' => '2026-09-01'])->save();
    saved($this->borrower, 2000);

    $this->actingAs($this->borrower->user)->get('/me/loans/apply')->assertOk()
        ->assertInertia(fn ($p) => $p->component('loans/apply')->where('rules.0.ok', false));

    $this->group(['loans_enabled' => true, 'loan_min_savings' => 500000]);
    $failing = fn () => collect($this->actingAs($this->borrower->user->fresh())->get('/me/loans/apply')->viewData('page')['props']['rules'])
        ->reject(fn ($r) => $r['ok'])->pluck('key')->all();

    expect($failing())->toBe(['membership', 'savings']);

    $this->borrower->forceFill(['joined_on' => '2025-01-01'])->save();
    saved($this->borrower, 3000);
    expect($failing())->toBe([]);

    $this->actingAs($this->borrower->user)->post('/me/loans', ['amount' => '16000', 'term_months' => 6, 'purpose' => 'Dairy cow'])
        ->assertSessionHasErrors('action');
});

it('runs an application through its guarantors and two payout approvals before any money leaves', function () {
    $this->group([
        'loans_enabled' => true, 'loan_interest_on' => true, 'loan_interest_period' => 'month',
        'loan_min_savings' => 100000, 'loan_guarantors_required' => 2,
    ]);
    $g1 = $this->member(['name' => 'Cosmas Kiprono']);
    $g2 = $this->member(['name' => 'Victor Kirui']);
    $outsider = $this->member();
    saved($this->borrower, 10000);
    saved($g1, 10000);
    saved($g2, 10000);

    // Guarantors must cover what the borrower's own savings do not.
    $this->actingAs($this->borrower->user)->post('/me/loans', [
        'amount' => '20000', 'term_months' => 10, 'purpose' => 'School fees',
        'guarantors' => [['member_id' => $g1->id, 'amount' => '4000'], ['member_id' => $g2->id, 'amount' => '4000']],
    ])->assertSessionHasErrors('action');

    $this->actingAs($this->borrower->user)->post('/me/loans', [
        'amount' => '20000', 'term_months' => 10, 'purpose' => 'School fees',
        'guarantors' => [['member_id' => $g1->id, 'amount' => '6000'], ['member_id' => $g2->id, 'amount' => '6000']],
    ])->assertSessionHasNoErrors();

    $loan = Loan::query()->latest('id')->firstOrFail();
    expect($loan->status)->toBe('pending')->and($loan->instalment)->toBe(200000)->and($loan->rate())->toBe(10.0);

    $this->actingAs($outsider->user)->get("/loans/{$loan->id}")->assertForbidden();
    $this->actingAs($g1->user)->get("/loans/{$loan->id}")->assertOk()->assertInertia(fn ($p) => $p->where('myGuarantee.status', 'pending'));

    $this->actingAs($g1->user)->post("/loans/{$loan->id}/guarantee", ['accept' => true])->assertSessionHasNoErrors();
    $this->actingAs($this->treasurer)->post("/loans/{$loan->id}/submit")->assertSessionHasErrors('action');
    $this->actingAs($g2->user)->post("/loans/{$loan->id}/guarantee", ['accept' => true])->assertSessionHasNoErrors();
    $this->actingAs($this->treasurer)->post("/loans/{$loan->id}/submit")->assertSessionHasNoErrors();

    $payout = Payout::query()->where('type', 'loan')->firstOrFail();
    expect($loan->refresh()->status)->toBe('submitted')->and($payout->amount)->toBe(2000000);

    $this->actingAs($this->treasurer)->post("/payouts/{$payout->id}/pay", ['method' => 'bank', 'reference' => 'X'])->assertSessionHasErrors('action');
    $this->actingAs($this->chair)->post("/payouts/{$payout->id}/decide", ['decision' => 'approved']);
    $this->actingAs($this->secretary)->post("/payouts/{$payout->id}/decide", ['decision' => 'approved']);
    $this->actingAs($this->treasurer)->post("/payouts/{$payout->id}/pay", ['method' => 'bank', 'reference' => 'EQ123'])->assertSessionHasNoErrors();

    $loan->refresh();
    expect($loan->status)->toBe('active')
        ->and($loan->issued_on->toDateString())->toBe('2026-11-12')
        ->and($loan->due_on->toDateString())->toBe('2027-09-12')
        ->and(owed($loan)['principal'])->toBe(2000000)
        ->and(books(ChartOfAccounts::LOANS, $this->borrower->id))->toBe(2000000)
        ->and(books(ChartOfAccounts::BANK))->toBe(1000000)
        ->and(app(LoanBook::class)->guaranteed($g1))->toBe(600000)
        ->and(app(LoanBook::class)->lendable($this->group()))->toBe(1000000);
});

it('ends the application when its payout is rejected, and reopens it when cancelled', function () {
    $this->group(['loans_enabled' => true, 'loan_guarantors_required' => 0, 'loan_min_membership_months' => 0]);
    saved($this->borrower, 10000);

    $apply = function () {
        $this->actingAs($this->borrower->user)->post('/me/loans', ['amount' => '5000', 'term_months' => 5, 'purpose' => 'Seeds'])->assertSessionHasNoErrors();
        $loan = Loan::query()->latest('id')->firstOrFail();
        $this->actingAs($this->treasurer)->post("/loans/{$loan->id}/submit")->assertSessionHasNoErrors();

        return $loan->refresh();
    };

    $loan = $apply();
    $this->actingAs($this->treasurer)->post("/payouts/{$loan->payout_id}/cancel", ['reason' => 'Wrong amount']);
    expect($loan->refresh()->status)->toBe('pending')->and($loan->payout_id)->toBeNull();

    $this->actingAs($this->treasurer)->post("/loans/{$loan->id}/submit")->assertSessionHasNoErrors();
    $this->actingAs($this->chair)->post("/payouts/{$loan->refresh()->payout_id}/decide", ['decision' => 'rejected', 'comment' => 'Not now']);
    expect($loan->refresh()->status)->toBe('rejected');
});

it('reverses a loan repayment and reopens a loan it had cleared', function () {
    $loan = oldLoan($this->borrower, ['principal' => '1000']);
    $payment = payLoan($loan, '1000');
    expect($loan->refresh()->status)->toBe('cleared');

    $this->actingAs($this->treasurer)->post("/payments/{$payment->id}/reverse", ['reason' => 'Recorded twice'])->assertSessionHasNoErrors();

    expect($loan->refresh()->status)->toBe('active')
        ->and(owed($loan)['principal'])->toBe(100000)
        ->and(books(ChartOfAccounts::LOANS, $this->borrower->id))->toBe(100000);
});

it('moves a payment matched as contributions onto the member\'s loan', function () {
    $loan = oldLoan($this->borrower, ['principal' => '5000']);

    $this->actingAs($this->treasurer)->post('/payments', [
        'channel' => 'paybill', 'amount' => '2000', 'purpose' => 'contribution', 'member_id' => $this->borrower->id,
        'reference' => 'TKA123', 'received_on' => now()->toDateString(),
    ])->assertSessionHasNoErrors();
    $payment = Payment::query()->latest('id')->firstOrFail();
    expect(books(ChartOfAccounts::PREPAYMENTS, $this->borrower->id))->toBe(200000);

    $this->actingAs($this->treasurer)->post("/payments/{$payment->id}/reallocate", ['purpose' => 'loan', 'target_id' => $loan->id, 'reason' => 'Member says it was for the loan'])
        ->assertSessionHasNoErrors();

    expect($payment->refresh()->purpose)->toBe('loan')
        ->and($payment->status)->toBe('succeeded')
        ->and(owed($loan)['principal'])->toBe(300000)
        ->and(books(ChartOfAccounts::PREPAYMENTS, $this->borrower->id))->toBe(0)
        ->and(books(ChartOfAccounts::BANK))->toBe(200000);
});

it('takes a leaving member\'s loan out of their savings first, and keeps guarantors from leaving', function () {
    saved($this->borrower, 5000);
    $loan = oldLoan($this->borrower, ['principal' => '2000']);
    $this->borrower->forceFill(['status' => 'exiting', 'exit_notice_on' => '2026-09-01'])->save();

    $this->actingAs($this->treasurer)->post("/members/{$this->borrower->id}/exit")->assertSessionHasNoErrors();

    expect($loan->refresh()->status)->toBe('cleared')
        ->and(Payout::query()->where('type', 'exit_refund')->value('amount'))->toBe(300000);

    $guarantor = $this->member();
    saved($guarantor, 5000);
    $other = oldLoan($this->member(), ['principal' => '2000']);
    LoanGuarantor::create(['loan_id' => $other->id, 'member_id' => $guarantor->id, 'amount' => 100000, 'status' => 'accepted']);
    $guarantor->forceFill(['status' => 'exiting', 'exit_notice_on' => '2026-09-01'])->save();

    $this->actingAs($this->treasurer)->post("/members/{$guarantor->id}/exit")->assertSessionHasErrors('action');
});

it('shows running loans to every member when the books are open, without phone numbers', function () {
    oldLoan($this->borrower, ['principal' => '3000', 'due_on' => '2026-10-31']);
    $this->group(['members_see_group_finances' => true]);
    $viewer = $this->member();

    $this->actingAs($viewer->user)->get('/group-finances')->assertOk()
        ->assertInertia(fn ($p) => $p->has('loans', 1)
            ->where('loans.0.name', 'Cheruiyot Langat')
            ->where('loans.0.owed', 300000)
            ->where('loans.0.overdue', true)
            ->missing('loans.0.phone'));

    $this->actingAs($viewer->user)->get('/loans')->assertForbidden();
});

it('charges interest from the daily job on each loan\'s monthly date', function () {
    $this->group(['loan_interest_on' => true]);
    $loan = oldLoan($this->borrower, ['principal' => '5000']);

    $this->artisan('kertai:daily', ['--date' => '2026-11-15'])->assertSuccessful();
    $this->artisan('kertai:daily', ['--date' => '2026-11-15'])->assertSuccessful();

    expect(owed($loan)['interest'])->toBe(50000)
        ->and($loan->refresh()->next_interest_on->toDateString())->toBe('2026-12-15');
});

it('charges one-off interest when an application is paid out', function () {
    $this->group([
        'loans_enabled' => true, 'loan_interest_on' => true, 'loan_interest_period' => 'once',
        'loan_guarantors_required' => 0, 'loan_min_membership_months' => 0,
    ]);
    saved($this->borrower, 10000);

    $this->actingAs($this->borrower->user)->post('/me/loans', ['amount' => '6000', 'term_months' => 3, 'purpose' => 'Fertiliser'])->assertSessionHasNoErrors();
    $loan = Loan::query()->latest('id')->firstOrFail();
    $this->actingAs($this->treasurer)->post("/loans/{$loan->id}/submit");
    $payout = $loan->refresh()->payout;
    $this->actingAs($this->chair)->post("/payouts/{$payout->id}/decide", ['decision' => 'approved']);
    $this->actingAs($this->secretary)->post("/payouts/{$payout->id}/decide", ['decision' => 'approved']);
    $this->actingAs($this->treasurer)->post("/payouts/{$payout->id}/pay", ['method' => 'mpesa', 'reference' => 'QX1'])->assertSessionHasNoErrors();

    expect(owed($loan))->toBe(['principal' => 600000, 'interest' => 60000, 'total' => 660000])
        ->and(app(LoanBook::class)->accrue($this->group(), CarbonImmutable::parse('2026-12-12')))->toBe(0);
});

it('shows a member their loans and guarantee requests on the dashboard', function () {
    $this->group(['loans_enabled' => true, 'loan_guarantors_required' => 1, 'loan_min_membership_months' => 0]);
    $guarantor = $this->member();
    saved($this->borrower, 1000);
    saved($guarantor, 10000);

    $this->actingAs($this->borrower->user)->post('/me/loans', [
        'amount' => '3000', 'term_months' => 3, 'purpose' => 'Seeds',
        'guarantors' => [['member_id' => $guarantor->id, 'amount' => '2000']],
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->borrower->user)->get('/dashboard')
        ->assertInertia(fn ($p) => $p->has('loans.mine', 1)->where('loans.mine.0.status', 'pending')->where('auth.member.has_loans', true));
    $this->actingAs($guarantor->user)->get('/dashboard')
        ->assertInertia(fn ($p) => $p->has('loans.asked', 1)->where('loans.asked.0.borrower', 'Cheruiyot Langat')->where('loans.asked.0.amount', 200000));
});
