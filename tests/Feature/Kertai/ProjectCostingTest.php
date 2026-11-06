<?php

use App\Domain\Ledger\Ledger;
use App\Domain\Payouts\Models\Payout;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectCost;
use App\Domain\Projects\Models\ProjectPlot;
use App\Domain\Projects\Models\ProjectSeason;
use App\Domain\Projects\ProjectCosting;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->pc = $this->official('project-coordinator');
    $this->treasurer = $this->official('treasurer');
    $this->alice = $this->member();

    $this->actingAs($this->pc)->post('/projects', [
        'name' => 'Maize Kapsoit',
        'category' => 'crops',
        'starts_on' => '2026-03-01',
        'share_basis' => 'contribution',
    ])->assertRedirect();

    $this->project = Project::query()->firstOrFail();
    $this->url = "/projects/{$this->project->id}";

    $this->actingAs($this->treasurer)->post("{$this->url}/money", [
        'kind' => 'contribution', 'member_id' => $this->alice->id, 'amount' => '50000', 'channel' => 'cash', 'received_on' => now()->toDateString(),
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->pc)->post("{$this->url}/seasons", [
        'name' => 'Short rains', 'starts_on' => now()->subMonth()->toDateString(),
    ])->assertSessionHasNoErrors();
    $this->season = ProjectSeason::query()->firstOrFail();
});

function cost(array $data = []): array
{
    return [
        'category' => 'fertiliser',
        'description' => 'DAP, 50 kg bags',
        'quantity' => '7',
        'unit' => 'bags',
        'unit_cost' => '3200',
        'incurred_on' => now()->toDateString(),
        'supplier_name' => 'Kapsoit Agrovet',
        'paid_by' => 'fund',
        ...$data,
    ];
}

it('records land in acres or hectares and warns before a lease runs out', function () {
    $this->actingAs($this->pc)->post("{$this->url}/plots", [
        'name' => 'Plot A', 'size' => '2.5', 'size_unit' => 'acres', 'tenure' => 'leased',
        'owner_name' => 'Landlord', 'owner_phone' => '0722000111', 'lease_amount' => '15000', 'lease_per' => 'season',
        'lease_starts_on' => now()->subYear()->toDateString(), 'lease_ends_on' => now()->addMonth()->toDateString(),
    ])->assertSessionHasNoErrors();
    $this->actingAs($this->pc)->post("{$this->url}/plots", [
        'name' => 'Plot B', 'size' => '1', 'size_unit' => 'hectares', 'tenure' => 'member', 'member_id' => $this->alice->id,
    ])->assertSessionHasNoErrors();
    $this->actingAs($this->pc)->post("{$this->url}/plots", ['name' => 'Plot C', 'size' => '1', 'size_unit' => 'acres', 'tenure' => 'leased'])
        ->assertSessionHasErrors(['lease_amount', 'lease_per']);

    $a = ProjectPlot::query()->where('name', 'Plot A')->firstOrFail();

    expect($a->lease_amount)->toBe(1500000)
        ->and($a->owner_phone)->toBe('254722000111')
        ->and($a->leaseEndingSoon())->toBeTrue()
        ->and($this->project->acres())->toBe(4.97);
});

it('works out labour as workers times days times rate and raises the payout', function () {
    $this->actingAs($this->pc)->post("{$this->url}/costs", cost([
        'category' => 'labour', 'description' => 'Planting', 'labour_type' => 'casual',
        'workers' => 6, 'days' => '2.5', 'unit_cost' => '500', 'supplier_name' => 'Casual workers',
    ]))->assertSessionHasNoErrors();

    $cost = ProjectCost::query()->firstOrFail();
    $payout = Payout::query()->where('type', 'project_expense')->firstOrFail();

    expect($cost->quantity)->toBe('15.00')
        ->and($cost->unit)->toBe('man-days')
        ->and($cost->amount)->toBe(750000)
        ->and($cost->project_season_id)->toBe($this->season->id)
        ->and($cost->payout_id)->toBe($payout->id)
        ->and($cost->state())->toBe('awaiting')
        ->and($payout->amount)->toBe(750000)
        // Not spent yet: the money leaves only when the payout is paid.
        ->and($this->project->profitAndLoss()['expenses'])->toBe(0);

    $this->actingAs($this->official('chairperson'))->post("/payouts/{$payout->id}/decide", ['decision' => 'approved']);
    $this->actingAs($this->official('secretary'))->post("/payouts/{$payout->id}/decide", ['decision' => 'approved']);
    $this->actingAs($this->treasurer)->post("/payouts/{$payout->id}/pay", ['method' => 'cash', 'reference' => 'V1'])->assertSessionHasNoErrors();

    expect($cost->refresh()->state())->toBe('paid')
        ->and($this->project->profitAndLoss()['expenses'])->toBe(750000);

    // Paid costs stay in the register.
    $this->actingAs($this->pc)->delete("{$this->url}/costs/{$cost->id}")->assertSessionHasErrors('action');
});

it('keeps owed costs until the coordinator asks for the payout', function () {
    $this->actingAs($this->pc)->post("{$this->url}/costs", cost(['paid_by' => 'owed', 'quantity' => '2.5', 'unit' => 'acres', 'unit_cost' => '3500', 'category' => 'land_prep', 'description' => 'Ploughing']))
        ->assertSessionHasNoErrors();
    $cost = ProjectCost::query()->firstOrFail();

    expect($cost->amount)->toBe(875000)->and($cost->state())->toBe('owed')->and(Payout::query()->count())->toBe(0);

    // The amount of an owed cost can still be corrected.
    $this->actingAs($this->pc)->put("{$this->url}/costs/{$cost->id}", cost(['quantity' => '3', 'unit' => 'acres', 'unit_cost' => '3500', 'category' => 'land_prep', 'description' => 'Ploughing']))
        ->assertSessionHasNoErrors();
    expect($cost->refresh()->amount)->toBe(1050000);

    $this->actingAs($this->pc)->post("{$this->url}/costs/{$cost->id}/payout")->assertSessionHasNoErrors();
    expect($cost->refresh()->state())->toBe('awaiting')->and($cost->paid_by)->toBe('fund');

    // Once a payout exists the amount is fixed.
    $this->actingAs($this->pc)->put("{$this->url}/costs/{$cost->id}", cost(['quantity' => '4', 'unit' => 'acres', 'unit_cost' => '3500', 'category' => 'land_prep', 'description' => 'Ploughing']))
        ->assertSessionHasErrors('action');
});

it('leaves out a cost whose payout was turned down', function () {
    $this->actingAs($this->pc)->post("{$this->url}/costs", cost())->assertSessionHasNoErrors();
    $payout = Payout::query()->firstOrFail();
    $this->actingAs($this->official('chairperson'))->post("/payouts/{$payout->id}/decide", ['decision' => 'rejected', 'comment' => 'Too dear'])->assertSessionHasNoErrors();

    $cost = ProjectCost::query()->firstOrFail();
    $summary = app(ProjectCosting::class)->summary($this->project, $this->season, 0);

    expect($cost->state())->toBe('rejected')->and($summary['costs'])->toBe(0);

    $this->actingAs($this->pc)->delete("{$this->url}/costs/{$cost->id}")->assertSessionHasNoErrors();
    expect(ProjectCost::query()->count())->toBe(0);
});

it('counts a member-paid cost as their contribution and undoes it on delete', function () {
    $balance = app(Ledger::class)->balance($this->project->fundAccount());

    $this->actingAs($this->pc)->post("{$this->url}/costs", cost([
        'category' => 'chemicals', 'description' => 'Herbicide', 'quantity' => '5', 'unit' => 'litres', 'unit_cost' => '1200',
        'paid_by' => 'member', 'member_id' => $this->alice->id, 'supplier_name' => null,
    ]))->assertSessionHasNoErrors();

    $participant = $this->project->participants()->where('member_id', $this->alice->id)->firstOrFail();
    $pnl = $this->project->profitAndLoss();

    expect($participant->contributed)->toBe(5000000 + 600000)
        ->and($pnl['contributions'])->toBe(5600000)
        ->and($pnl['expenses'])->toBe(600000)
        ->and(Payout::query()->count())->toBe(0)
        // No cash moved, so the fund balance is unchanged.
        ->and(app(Ledger::class)->balance($this->project->fundAccount()))->toBe($balance);

    $cost = ProjectCost::query()->firstOrFail();
    $this->actingAs($this->pc)->delete("{$this->url}/costs/{$cost->id}")->assertSessionHasNoErrors();

    expect($participant->refresh()->contributed)->toBe(5000000)
        ->and($this->project->profitAndLoss()['expenses'])->toBe(0);
});

it('locks a closed season and reports profit, budget and cost per unit', function () {
    $this->actingAs($this->pc)->post("{$this->url}/plots", ['name' => 'Plot A', 'size' => '2', 'size_unit' => 'acres', 'tenure' => 'owned'])->assertSessionHasNoErrors();
    $this->actingAs($this->pc)->put("{$this->url}/seasons/{$this->season->id}/budget", ['budget' => ['fertiliser' => '20000', 'labour' => '10000', 'seeds' => '']])->assertSessionHasNoErrors();
    $this->actingAs($this->pc)->post("{$this->url}/costs", cost(['paid_by' => 'owed']))->assertSessionHasNoErrors();

    $this->actingAs($this->pc)->post("{$this->url}/items", ['name' => 'Dry maize', 'unit' => 'bags', 'unit_price' => '3600', 'quantity' => 20])->assertSessionHasNoErrors();
    $item = $this->project->items()->firstOrFail();
    $this->actingAs($this->pc)->post("{$this->url}/sales", [
        'project_item_id' => $item->id, 'quantity' => 10, 'unit_price' => '3600', 'buyer_name' => 'NCPB', 'sold_on' => now()->toDateString(), 'payment' => 'none',
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->pc)->put("{$this->url}/seasons/{$this->season->id}", [
        'name' => 'Short rains', 'starts_on' => $this->season->starts_on->toDateString(), 'yield_quantity' => '10', 'yield_unit' => 'bags',
    ])->assertSessionHasNoErrors();

    $summary = app(ProjectCosting::class)->summary($this->project, $this->season->refresh(), $this->project->acres());

    expect($summary['costs'])->toBe(2240000)
        ->and($summary['sales'])->toBe(3600000)
        ->and($summary['profit'])->toBe(1360000)
        ->and($summary['budget'])->toBe(['fertiliser' => 2000000, 'labour' => 1000000])
        ->and($summary['cost_per_acre'])->toBe(1120000)
        ->and($summary['cost_per_unit'])->toBe(224000)
        ->and($summary['owed'])->toBe(2240000);

    $this->actingAs($this->pc)->post("{$this->url}/seasons/{$this->season->id}/status", ['status' => 'closed'])->assertSessionHasNoErrors();
    $this->actingAs($this->pc)->post("{$this->url}/costs", cost(['project_season_id' => $this->season->id]))->assertSessionHasErrors('action');

    $cost = ProjectCost::query()->firstOrFail();
    $this->actingAs($this->pc)->delete("{$this->url}/costs/{$cost->id}")->assertSessionHasErrors('action');

    $this->actingAs($this->pc)->post("{$this->url}/seasons/{$this->season->id}/status", ['status' => 'open'])->assertSessionHasNoErrors();
    $this->actingAs($this->pc)->delete("{$this->url}/costs/{$cost->id}")->assertSessionHasNoErrors();
});

it('lets the named coordinator keep costs and every member read them without phone numbers', function () {
    $bob = $this->member();
    $this->actingAs($this->pc)->put($this->url, [
        'name' => 'Maize Kapsoit', 'category' => 'crops', 'starts_on' => '2026-03-01', 'share_basis' => 'contribution', 'status' => 'active',
        'coordinator_member_id' => $bob->id,
    ])->assertSessionHasNoErrors();

    $this->actingAs($bob->user)->post("{$this->url}/plots", [
        'name' => 'Plot A', 'size' => '1', 'size_unit' => 'acres', 'tenure' => 'leased', 'owner_phone' => '0722000111', 'lease_amount' => '5000', 'lease_per' => 'year',
    ])->assertSessionHasNoErrors();
    $this->actingAs($bob->user)->post("{$this->url}/costs", cost(['paid_by' => 'owed', 'supplier_phone' => '0733000222']))->assertSessionHasNoErrors();
    // Bob runs this project's costs, not the rest of the project.
    $this->actingAs($bob->user)->post("{$this->url}/distribute", ['amount' => '100'])->assertForbidden();

    $this->actingAs($this->alice->user)->post("{$this->url}/costs", cost())->assertForbidden();
    $this->actingAs($this->alice->user)->get($this->url)->assertOk()->assertInertia(fn ($p) => $p
        ->where('canManageCosts', false)
        ->has('costs', 1)
        ->where('costs.0.supplier_phone', null)
        ->where('plots.0.owner_phone', null));

    $this->actingAs($this->pc)->get($this->url)->assertInertia(fn ($p) => $p
        ->where('canManageCosts', true)
        ->where('costs.0.supplier_phone', '0733000222')
        ->where('plots.0.owner_phone', '0722000111'));

    $csv = $this->actingAs($this->alice->user)->get("{$this->url}/costs.csv")->assertOk()->streamedContent();
    expect($csv)->toContain('DAP, 50 kg bags')->toContain('Fertiliser and manure')->not->toContain('0733000222');
});

it('files the quick expense in the cost register', function () {
    $this->actingAs($this->pc)->post("{$this->url}/expenses", ['amount' => '2000', 'description' => 'Polythene sleeves', 'payee_name' => 'Agrovet'])->assertSessionHasNoErrors();

    $cost = ProjectCost::query()->firstOrFail();

    expect($cost->category)->toBe('other')
        ->and($cost->amount)->toBe(200000)
        ->and($cost->payout_id)->toBe(Payout::query()->value('id'));
});

it('dates project money on the day it was received, not the day it was typed in', function () {
    $this->actingAs($this->treasurer)->post("{$this->url}/money", [
        'kind' => 'income', 'payer_name' => 'Factory', 'amount' => '500', 'channel' => 'cash', 'received_on' => CarbonImmutable::today()->subDays(10)->toDateString(),
    ])->assertSessionHasNoErrors();

    expect($this->project->entries()->where('kind', 'income')->value('entry_on')->toDateString())->toBe(CarbonImmutable::today()->subDays(10)->toDateString());
});
