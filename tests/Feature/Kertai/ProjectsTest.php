<?php

use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Ledger\Ledger;
use App\Domain\Payouts\Models\Payout;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectItem;
use App\Domain\Projects\Models\Sale;
use App\Domain\Projects\ProjectBook;

beforeEach(function () {
    $this->pc = $this->official('project-coordinator');
    $this->treasurer = $this->official('treasurer');
    $this->alice = $this->member();
    $this->bob = $this->member();

    $this->actingAs($this->pc)->post('/projects', [
        'name' => 'Coffee seedlings',
        'category' => 'crops',
        'starts_on' => '2026-03-01',
        'share_basis' => 'contribution',
        'coordinator_member_id' => $this->pc->member->id,
    ])->assertRedirect();

    $this->project = Project::query()->firstOrFail();
});

function projectMoney(array $data): void
{
    test()->actingAs(test()->treasurer)->post('/projects/'.test()->project->id.'/money', [
        'channel' => 'cash',
        'received_on' => now()->toDateString(),
        ...$data,
    ])->assertSessionHasNoErrors();
}

it('opens its own fund and takes contributions from participants', function () {
    expect($this->project->account_id)->not->toBeNull()->and($this->project->status)->toBe('active');

    $this->actingAs($this->pc)->post("/projects/{$this->project->id}/participants", ['member_ids' => [$this->alice->id, $this->bob->id]])->assertSessionHasNoErrors();

    projectMoney(['kind' => 'contribution', 'member_id' => $this->alice->id, 'amount' => '3000']);
    projectMoney(['kind' => 'contribution', 'member_id' => $this->bob->id, 'amount' => '1000']);
    projectMoney(['kind' => 'income', 'payer_name' => 'Factory', 'amount' => '500', 'description' => 'Cuttings sold']);

    $pnl = $this->project->profitAndLoss();

    expect($this->project->participants()->where('member_id', $this->alice->id)->value('contributed'))->toBe(300000)
        ->and($pnl['contributions'])->toBe(400000)
        ->and($pnl['income'])->toBe(50000)
        ->and(app(Ledger::class)->balance($this->project->fundAccount()))->toBe(450000);

    $participant = $this->project->participants()->where('member_id', $this->alice->id)->first();
    $this->actingAs($this->pc)->delete("/projects/{$this->project->id}/participants/{$participant->id}")->assertSessionHasErrors('action');
});

it('keeps stock, sells seedlings and refuses to oversell', function () {
    $this->actingAs($this->pc)->post("/projects/{$this->project->id}/items", ['name' => 'SL28 seedling', 'unit' => 'seedlings', 'unit_price' => '30', 'quantity' => 1000])->assertSessionHasNoErrors();
    $item = ProjectItem::query()->firstOrFail();

    $this->actingAs($this->pc)->post("/projects/{$this->project->id}/items/{$item->id}/stock", ['kind' => 'out', 'quantity' => 50, 'note' => 'Dried up'])->assertSessionHasNoErrors();
    expect($item->refresh()->quantity)->toBe(950);

    $sale = ['project_item_id' => $item->id, 'unit_price' => '30', 'buyer_name' => 'Kipsigis co-op', 'buyer_phone' => '0733000001', 'sold_on' => now()->toDateString()];

    $this->actingAs($this->pc)->post("/projects/{$this->project->id}/sales", [...$sale, 'quantity' => 2000, 'payment' => 'cash'])->assertSessionHasErrors('action');
    $this->actingAs($this->pc)->post("/projects/{$this->project->id}/sales", [...$sale, 'quantity' => 100, 'payment' => 'cash'])->assertSessionHasNoErrors();
    $this->actingAs($this->pc)->post("/projects/{$this->project->id}/sales", [...$sale, 'quantity' => 200, 'payment' => 'none'])->assertSessionHasNoErrors();

    $credit = Sale::query()->where('status', 'unpaid')->firstOrFail();
    $this->actingAs($this->pc)->post("/projects/{$this->project->id}/sales/{$credit->id}/payments", ['amount' => '9000', 'channel' => 'cash'])->assertSessionHasErrors('action');
    $this->actingAs($this->pc)->post("/projects/{$this->project->id}/sales/{$credit->id}/payments", ['amount' => '2000', 'channel' => 'cash'])->assertSessionHasNoErrors();

    expect($item->refresh()->quantity)->toBe(650)
        ->and(Sale::query()->where('status', 'paid')->count())->toBe(1)
        ->and($credit->refresh()->status)->toBe('partial')
        ->and($credit->balance())->toBe(400000)
        ->and($this->project->profitAndLoss()['income'])->toBe(500000);

    $this->actingAs($this->pc)->post("/projects/{$this->project->id}/sales", [...$sale, 'quantity' => 10, 'payment' => 'mpesa'])->assertSessionHasNoErrors();
    expect(Sale::query()->count())->toBe(3);
});

it('raises expenses as payouts and shares profit into savings by contribution', function () {
    $this->actingAs($this->pc)->post("/projects/{$this->project->id}/participants", ['member_ids' => [$this->alice->id, $this->bob->id]]);
    projectMoney(['kind' => 'contribution', 'member_id' => $this->alice->id, 'amount' => '3000']);
    projectMoney(['kind' => 'contribution', 'member_id' => $this->bob->id, 'amount' => '1000']);
    projectMoney(['kind' => 'income', 'payer_name' => 'Buyer', 'amount' => '10000']);

    $this->actingAs($this->pc)->post("/projects/{$this->project->id}/expenses", ['amount' => '2000', 'description' => 'Polythene sleeves', 'payee_name' => 'Agrovet'])->assertSessionHasNoErrors();
    $payout = Payout::query()->where('type', 'project_expense')->firstOrFail();

    $chair = $this->official('chairperson');
    $secretary = $this->official('secretary');
    $this->actingAs($chair)->post("/payouts/{$payout->id}/decide", ['decision' => 'approved']);
    $this->actingAs($secretary)->post("/payouts/{$payout->id}/decide", ['decision' => 'approved']);
    $this->actingAs($this->treasurer)->post("/payouts/{$payout->id}/pay", ['method' => 'cash', 'reference' => 'V001']);

    expect($this->project->profitAndLoss()['expenses'])->toBe(200000)
        ->and($this->project->profitAndLoss()['profit'])->toBe(800000);

    $this->actingAs($this->pc)->post("/projects/{$this->project->id}/distribute", ['amount' => '9000'])->assertSessionHasErrors('action');
    $this->actingAs($this->pc)->post("/projects/{$this->project->id}/distribute", ['amount' => '8000'])->assertSessionHasNoErrors();

    $savings = app(ChartOfAccounts::class)->get($this->group(), ChartOfAccounts::SAVINGS);
    $ledger = app(Ledger::class);

    expect($ledger->balance($savings, $this->alice->id))->toBe(600000)
        ->and($ledger->balance($savings, $this->bob->id))->toBe(200000)
        ->and($this->project->profitAndLoss()['distributed'])->toBe(800000);

    $this->actingAs($this->pc)->get("/projects/{$this->project->id}")->assertOk()->assertInertia(fn ($p) => $p->where('pnl.undistributed', 0)->has('expenses', 1));
});

it('gives rounding leftovers to the largest holder, not the highest member number', function () {
    $book = app(ProjectBook::class);
    $this->project->participants()->create(['member_id' => $this->alice->id, 'contributed' => 200000, 'joined_on' => '2026-03-01']);
    $this->project->participants()->create(['member_id' => $this->bob->id, 'contributed' => 100000, 'joined_on' => '2026-03-01']);

    $shares = collect($book->shareOutPreview($this->project, 100)['shares'])->pluck('amount', 'member_id');

    // 100 cents split 2:1 is 66 and 33; the spare cent goes to Alice.
    expect($shares[$this->alice->id])->toBe(67)->and($shares[$this->bob->id])->toBe(33);
});

it('lets members with project access see projects but not change them', function () {
    $auditor = $this->official('auditor');

    $this->actingAs($auditor)->get('/projects')->assertOk();
    $this->actingAs($auditor)->get("/projects/{$this->project->id}")->assertOk()->assertInertia(fn ($p) => $p->where('canManage', false));
    $this->actingAs($auditor)->post('/projects', ['name' => 'X'])->assertForbidden();
    $this->actingAs($this->alice->user)->get('/projects')->assertForbidden();

    $this->actingAs($this->pc)->put("/projects/{$this->project->id}", [
        'name' => 'Coffee seedlings nursery', 'category' => 'crops', 'starts_on' => '2026-03-01', 'share_basis' => 'equal', 'status' => 'active',
    ])->assertSessionHasNoErrors();
    expect($this->project->refresh()->share_basis)->toBe('equal');
});
