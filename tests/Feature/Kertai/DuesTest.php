<?php

use App\Domain\Dues\Actions\ApplyLateFines;
use App\Domain\Dues\Actions\GenerateMonthlyDues;
use App\Domain\Dues\Models\Due;
use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Ledger\Ledger;
use App\Domain\Members\MemberFinance;
use App\Domain\Payments\Actions\RecordPayment;
use Carbon\CarbonImmutable;

function balanceOf(string $code, ?int $memberId = null): int
{
    $group = test()->group();

    return app(Ledger::class)->balance(app(ChartOfAccounts::class)->get($group, $code), $memberId);
}

it('raises one contribution per active member per month', function () {
    $group = $this->group();
    $a = $this->member();
    $b = $this->member();
    $this->member(['status' => 'exited']);
    $this->member(['joined_on' => '2030-01-01']);

    $created = app(GenerateMonthlyDues::class)->handle($group, '2026-11');
    $again = app(GenerateMonthlyDues::class)->handle($group, '2026-11');

    $due = Due::query()->where('member_id', $a->id)->first();

    expect($created)->toBe(2)
        ->and($again)->toBe(0)
        ->and(Due::query()->where('period', '2026-11')->count())->toBe(2)
        ->and($due->amount)->toBe(100000)
        ->and($due->breakdown)->toBe(['mgr' => 50000, 'welfare' => 20000, 'savings' => 30000])
        ->and($due->due_on->toDateString())->toBe('2026-11-05')
        ->and($b->dues()->count())->toBe(1);
});

it('applies a partial payment to the merry-go-round first, then welfare, then savings', function () {
    $group = $this->group();
    $member = $this->member();
    app(GenerateMonthlyDues::class)->handle($group, '2026-11');

    app(RecordPayment::class)->handle($group, 'cash', 60000, 'contribution', $member);

    expect(balanceOf(ChartOfAccounts::MGR))->toBe(50000)
        ->and(balanceOf(ChartOfAccounts::WELFARE))->toBe(10000)
        ->and(balanceOf(ChartOfAccounts::SAVINGS, $member->id))->toBe(0)
        ->and(balanceOf(ChartOfAccounts::CASH))->toBe(60000);

    app(RecordPayment::class)->handle($group, 'cash', 40000, 'contribution', $member);

    expect(balanceOf(ChartOfAccounts::WELFARE))->toBe(20000)
        ->and(balanceOf(ChartOfAccounts::SAVINGS, $member->id))->toBe(30000)
        ->and($member->dues()->first()->status)->toBe('paid');
});

it('pays the oldest dues first and keeps any surplus for next month', function () {
    $group = $this->group();
    $member = $this->member();
    app(GenerateMonthlyDues::class)->handle($group, '2026-10');
    app(GenerateMonthlyDues::class)->handle($group, '2026-11');

    app(RecordPayment::class)->handle($group, 'paybill', 250000, 'contribution', $member, reference: 'RJK1ABCD12');

    expect($member->dues()->where('status', 'paid')->count())->toBe(2)
        ->and(balanceOf(ChartOfAccounts::PREPAYMENTS, $member->id))->toBe(50000);

    app(GenerateMonthlyDues::class)->handle($group, '2026-12');
    $december = $member->dues()->where('period', '2026-12')->first();

    expect($december->paid)->toBe(50000)
        ->and(balanceOf(ChartOfAccounts::PREPAYMENTS, $member->id))->toBe(0)
        ->and(balanceOf(ChartOfAccounts::MGR))->toBe(150000);
});

it('charges the late fine once, after the grace period', function () {
    $group = $this->group(['late_fine_grace_days' => 2]);
    $member = $this->member();
    app(GenerateMonthlyDues::class)->handle($group, '2026-11');
    $fines = app(ApplyLateFines::class);

    expect($fines->handle($group, CarbonImmutable::parse('2026-11-06')))->toBe(0)
        ->and($fines->handle($group, CarbonImmutable::parse('2026-11-08')))->toBe(1)
        ->and($fines->handle($group, CarbonImmutable::parse('2026-11-20')))->toBe(0);

    $fine = $member->dues()->where('type', 'fine')->first();
    expect($fine->amount)->toBe(10000)->and($fine->breakdown)->toBe(['group' => 10000]);

    // Paying everything: the fine money lands in the group fund.
    app(RecordPayment::class)->handle($group, 'cash', 110000, 'contribution', $member);
    expect(balanceOf(ChartOfAccounts::GROUP_FUND))->toBe(10000)
        ->and(app(MemberFinance::class)->arrears($member))->toBe(0);
});

it('charges nothing before the start date and makes the first month due on it', function () {
    $group = $this->group(['dues_start_on' => '2026-11-12']);
    $member = $this->member();
    $dues = app(GenerateMonthlyDues::class);
    $fines = app(ApplyLateFines::class);

    expect($dues->handle($group, '2026-10'))->toBe(0)
        ->and($dues->handle($group, '2026-11'))->toBe(1)
        ->and($dues->handle($group, '2026-12'))->toBe(1);

    expect($member->dues()->where('period', '2026-11')->first()->due_on->toDateString())->toBe('2026-11-12')
        ->and($member->dues()->where('period', '2026-12')->first()->due_on->toDateString())->toBe('2026-12-05');

    expect($fines->handle($group, CarbonImmutable::parse('2026-11-12')))->toBe(0)
        ->and($fines->handle($group, CarbonImmutable::parse('2026-11-13')))->toBe(1);
});

it('waives dues raised before the start date and leaves later ones alone', function () {
    // Late fines are dated "today", so pin the clock before the start date.
    $this->travelTo(CarbonImmutable::parse('2026-10-10'));
    $group = $this->group();
    $member = $this->member();
    app(GenerateMonthlyDues::class)->handle($group, '2026-10');
    app(ApplyLateFines::class)->handle($group, CarbonImmutable::parse('2026-10-10'));

    $this->group(['dues_start_on' => '2026-11-12']);
    app(GenerateMonthlyDues::class)->handle($group->refresh(), '2026-11');

    $this->artisan('kertai:waive-before-start', ['--dry-run' => true])->assertSuccessful();
    expect($member->dues()->where('status', 'waived')->count())->toBe(0);

    $this->artisan('kertai:waive-before-start')->assertSuccessful();

    expect($member->dues()->where('period', '2026-10')->pluck('status')->unique()->all())->toBe(['waived'])
        ->and($member->dues()->where('period', '2026-11')->first()->status)->toBe('open')
        ->and(app(MemberFinance::class)->arrears($member, CarbonImmutable::parse('2026-11-01')))->toBe(0);
});

it('lets the treasurer raise dues, apply fines and fine a member, and the chair waive', function () {
    $treasurer = $this->official('treasurer');
    $chair = $this->official('chairperson');
    $member = $this->member();

    $this->actingAs($treasurer)->post('/dues/generate', ['period' => now()->format('Y-m')])->assertRedirect();
    expect(Due::query()->where('type', 'contribution')->count())->toBe(3);

    $this->actingAs($treasurer)->post('/dues/fines', ['member_id' => $member->id, 'amount' => '200', 'reason' => 'Absent without apology'])->assertSessionHasNoErrors();
    $fine = Due::query()->where('type', 'fine')->firstOrFail();

    $this->actingAs($treasurer)->post("/dues/{$fine->id}/waive", ['reason' => 'Was sick'])->assertForbidden();
    $this->actingAs($chair)->post("/dues/{$fine->id}/waive", ['reason' => 'Was sick'])->assertSessionHasNoErrors();
    expect($fine->refresh()->status)->toBe('waived');

    $this->actingAs($treasurer)->post('/dues/fines/apply')->assertRedirect();
    $this->actingAs($treasurer)->get('/dues?status=open')->assertOk()->assertInertia(fn ($p) => $p->component('dues/index')->has('summary'));
    $this->actingAs($member->user)->post('/dues/generate', ['period' => '2026-11'])->assertForbidden();
});
