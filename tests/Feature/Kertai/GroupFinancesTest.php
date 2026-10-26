<?php

use App\Domain\Dues\Actions\GenerateMonthlyDues;
use Carbon\CarbonImmutable;

it('keeps the group finances closed to ordinary members until the rule is on', function () {
    $member = $this->member();

    $this->actingAs($member->user)->get('/group-finances')->assertForbidden();
    $this->actingAs($member->user)->get('/dashboard')->assertInertia(fn ($p) => $p->where('books', null)->where('group.open_books', false));

    $this->actingAs($this->official('treasurer'))->get('/group-finances')->assertOk();
});

it('shows every member the totals, the grid and who owes, without phone numbers', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00'));
    $group = $this->group(['members_see_group_finances' => true]);
    $viewer = $this->member();
    $debtor = $this->member(['name' => 'Owing Member']);
    app(GenerateMonthlyDues::class)->handle($group, '2026-10');

    $this->actingAs($viewer->user)->get('/group-finances')->assertOk()
        ->assertInertia(fn ($p) => $p->component('reports/group')
            ->has('overview.cashflow', 12)
            ->has('grid.rows', 2)
            ->has('arrears', 2)
            ->where('arrears', fn ($rows) => collect($rows)->pluck('name')->contains($debtor->name))
            ->missing('arrears.0.phone')
            ->missing('arrears.1.phone')
            ->missing('members'));

    $this->actingAs($viewer->user)->get('/dashboard')
        ->assertInertia(fn ($p) => $p->where('group.open_books', true)->where('books.arrears', 200000)->where('books.in_arrears', 2));
});
