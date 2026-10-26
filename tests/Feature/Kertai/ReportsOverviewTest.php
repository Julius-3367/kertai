<?php

use App\Domain\Dues\Actions\GenerateMonthlyDues;
use App\Domain\Payments\Actions\RecordPayment;
use App\Domain\Reports\Reports;
use Carbon\CarbonImmutable;

it('builds the overview figures from payments, dues and fund balances', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00'));
    $group = $this->group();
    $member = $this->member();
    app(GenerateMonthlyDues::class)->handle($group, '2026-10');
    app(GenerateMonthlyDues::class)->handle($group, '2026-11');
    app(RecordPayment::class)->handle($group, 'cash', 150000, 'contribution', $member);

    $o = app(Reports::class)->overview(CarbonImmutable::today());

    expect($o['cashflow'])->toHaveCount(12)
        ->and($o['cashflow'][11])->toBe(['month' => '2026-11', 'in' => 150000, 'out' => 0])
        ->and($o['cashflow'][0]['month'])->toBe('2025-12')
        ->and($o['contributions'][9])->toBe(['month' => '2026-10', 'due' => 100000, 'paid' => 100000])
        ->and($o['contributions'][10])->toBe(['month' => '2026-11', 'due' => 100000, 'paid' => 50000])
        ->and($o['totals'])->toBe(['collected' => 150000, 'paid_out' => 0, 'due_so_far' => 200000, 'paid_so_far' => 150000])
        ->and(collect($o['funds'])->sum('balance'))->toBe(150000);
});

it('sends the overview to the reports page', function () {
    $treasurer = $this->official('treasurer');

    $this->actingAs($treasurer)->get('/reports')->assertOk()
        ->assertInertia(fn ($p) => $p->has('overview.cashflow', 12)->has('overview.contributions', 12)->has('overview.totals'));
});
