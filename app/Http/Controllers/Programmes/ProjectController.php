<?php

namespace App\Http\Controllers\Programmes;

use App\Domain\Access\Permission;
use App\Domain\Audit\Audit;
use App\Domain\Ledger\Ledger;
use App\Domain\Members\Models\Member;
use App\Domain\Payments\Actions\InitiateStkPayment;
use App\Domain\Payments\Actions\RecordPayment;
use App\Domain\Payouts\Actions\RequestPayout;
use App\Domain\Payouts\Models\Payout;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectEntry;
use App\Domain\Projects\Models\ProjectItem;
use App\Domain\Projects\Models\ProjectParticipant;
use App\Domain\Projects\Models\Sale;
use App\Domain\Projects\Models\StockMovement;
use App\Domain\Projects\ProjectBook;
use App\Http\Controllers\Controller;
use App\Support\Money;
use App\Support\Phone;
use App\Support\RuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Ledger $ledger): Response
    {
        return Inertia::render('projects/index', [
            'projects' => Project::query()->with('coordinator:id,name')->withCount('participants')->latest('starts_on')->get()->map(fn (Project $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'category' => $p->category,
                'status' => $p->status,
                'starts_on' => $p->starts_on->toDateString(),
                'coordinator' => $p->coordinator?->name,
                'participants' => $p->participants_count,
                'balance' => $ledger->balance($p->fundAccount()),
                ...$p->profitAndLoss(),
            ]),
            'categories' => Project::CATEGORIES,
            'members' => $this->memberOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize(Permission::ProjectsManage->value);

        $data = $this->validated($request);
        $data['status'] ??= 'active';
        $project = Project::create($data);
        $project->fundAccount();
        Audit::log('project.created', "Created project {$project->name}", $project);
        $this->toast('Project created. Add participants and record contributions.');

        return to_route('projects.show', $project);
    }

    public function show(Request $request, Project $project, Ledger $ledger, ProjectBook $book): Response
    {
        $pnl = $project->profitAndLoss();
        $undistributed = max(0, $pnl['profit'] - $pnl['distributed']);

        return Inertia::render('projects/show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'category' => $project->category,
                'description' => $project->description,
                'status' => $project->status,
                'starts_on' => $project->starts_on->toDateString(),
                'ends_on' => $project->ends_on?->toDateString(),
                'share_basis' => $project->share_basis,
                'coordinator_member_id' => $project->coordinator_member_id,
                'coordinator' => $project->coordinator?->name,
                'balance' => $ledger->balance($project->fundAccount()),
                'account_id' => $project->account_id,
            ],
            'pnl' => [...$pnl, 'undistributed' => $undistributed],
            'participants' => $project->participants()->with('member:id,name,member_number')->get()->map(fn (ProjectParticipant $p) => [
                'id' => $p->id,
                'member_id' => $p->member_id,
                'name' => $p->member->name,
                'member_number' => $p->member->member_number,
                'contributed' => $p->contributed,
                'joined_on' => $p->joined_on->toDateString(),
            ]),
            'entries' => $project->entries()->with('member:id,name')->limit(100)->get()->map(fn (ProjectEntry $e) => [
                'id' => $e->id,
                'kind' => $e->kind,
                'amount' => $e->amount,
                'entry_on' => $e->entry_on->toDateString(),
                'description' => $e->description,
                'member' => $e->member?->name,
            ]),
            'items' => $project->items()->orderBy('name')->get()->map(fn (ProjectItem $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'unit' => $i->unit,
                'unit_price' => $i->unit_price,
                'quantity' => $i->quantity,
                'movements' => $i->movements()->limit(10)->get()->map(fn (StockMovement $m) => [
                    'id' => $m->id,
                    'kind' => $m->kind,
                    'quantity' => $m->quantity,
                    'note' => $m->note,
                    'at' => $m->created_at->toDateString(),
                ]),
            ]),
            'sales' => $project->sales()->with('item:id,name,unit')->limit(100)->get()->map(fn (Sale $s) => [
                'id' => $s->id,
                'item' => $s->item->name,
                'unit' => $s->item->unit,
                'quantity' => $s->quantity,
                'unit_price' => $s->unit_price,
                'total' => $s->total,
                'paid' => $s->paid,
                'balance' => $s->balance(),
                'buyer_name' => $s->buyer_name,
                'buyer_phone' => $s->buyer_phone ? Phone::display($s->buyer_phone) : null,
                'status' => $s->status,
                'sold_on' => $s->sold_on->toDateString(),
            ]),
            'expenses' => Payout::query()->where('payable_type', $project->getMorphClass())->where('payable_id', $project->id)->latest('id')->get()->map(fn (Payout $p) => [
                'id' => $p->id,
                'number' => $p->number,
                'amount' => $p->amount,
                'description' => $p->description,
                'status' => $p->status,
            ]),
            'sharePreview' => $undistributed > 0 ? $book->shareOutPreview($project, $undistributed)['shares'] : [],
            'canManage' => $request->user()->hasPermission(Permission::ProjectsManage->value),
            'canRecordMoney' => $request->user()->hasPermission(Permission::PaymentsRecord->value)
                || $request->user()->hasPermission(Permission::ProjectsManage->value),
            'members' => $this->memberOptions(),
            'categories' => Project::CATEGORIES,
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize(Permission::ProjectsManage->value);

        $project->update($this->validated($request, true));
        Audit::log('project.updated', "Updated project {$project->name}", $project);
        $this->toast('Project saved.');

        return back();
    }

    public function addParticipant(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize(Permission::ProjectsManage->value);

        $data = $request->validate([
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer', Rule::exists('members', 'id')->where('group_id', $this->group()->id)],
        ]);

        $added = 0;
        foreach ($data['member_ids'] as $memberId) {
            $participant = ProjectParticipant::firstOrCreate(
                ['project_id' => $project->id, 'member_id' => $memberId],
                ['joined_on' => now()->toDateString()],
            );
            $added += $participant->wasRecentlyCreated ? 1 : 0;
        }

        $this->toast("Added {$added} participant(s).");

        return back();
    }

    public function removeParticipant(Project $project, ProjectParticipant $participant): RedirectResponse
    {
        Gate::authorize(Permission::ProjectsManage->value);
        abort_unless($participant->project_id === $project->id, 404);

        if ($participant->contributed > 0) {
            throw new RuleViolation('This member has contributed to the project, so they stay on the list for profit sharing.');
        }

        $participant->delete();
        $this->toast('Participant removed.');

        return back();
    }

    /**
     * Money into the project: a member's contribution or income such as a
     * harvest sold in bulk.
     */
    public function money(Request $request, Project $project, RecordPayment $record, InitiateStkPayment $stk): RedirectResponse
    {
        abort_unless($request->user()->hasPermission(Permission::PaymentsRecord->value)
            || $request->user()->hasPermission(Permission::ProjectsManage->value), 403);

        $data = $request->validate([
            'kind' => ['required', Rule::in(['contribution', 'income'])],
            'member_id' => ['nullable', 'required_if:kind,contribution', 'integer', Rule::exists('members', 'id')->where('group_id', $this->group()->id)],
            'payer_name' => ['nullable', 'string', 'max:120'],
            'amount' => Money::rule(),
            'channel' => ['required', Rule::in(['cash', 'paybill', 'manual', 'mpesa'])],
            'phone' => ['nullable', 'required_if:channel,mpesa', 'string', 'max:20'],
            'reference' => ['nullable', 'required_if:channel,paybill', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:200'],
            'received_on' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $member = isset($data['member_id']) ? Member::find((int) $data['member_id']) : null;
        $amount = $this->cents($data['amount']);

        if ($data['channel'] === 'mpesa') {
            if ($data['kind'] !== 'contribution') {
                throw new RuleViolation('Use cash, paybill or bank for project income.');
            }

            $payment = $stk->handle($this->group(), $amount, $data['phone'], 'project', $member, $project);

            if ($payment->status === 'failed') {
                throw new RuleViolation('M-Pesa prompt not sent: '.$payment->failure_reason);
            }

            $this->toast('M-Pesa prompt sent. The contribution shows once the PIN is entered.', 'info');

            return back();
        }

        $record->handle(
            $this->group(),
            $data['channel'],
            $amount,
            'project',
            $member,
            $project,
            $data['reference'] ?? null,
            CarbonImmutable::parse($data['received_on']),
            $data['payer_name'] ?? null,
            meta: array_filter([
                'project_kind' => $data['kind'],
                'description' => $data['description'] ?? null,
            ]),
        );

        $this->toast(($data['kind'] === 'income' ? 'Income' : 'Contribution').' of '.Money::format($amount).' recorded.');

        return back();
    }

    public function expense(Request $request, Project $project, RequestPayout $requestPayout): RedirectResponse
    {
        Gate::authorize(Permission::PayoutsRequest->value);

        $data = $request->validate([
            'amount' => Money::rule(),
            'description' => ['required', 'string', 'max:200'],
            'payee_name' => ['required', 'string', 'max:120'],
            'payee_phone' => ['nullable', 'string', 'max:20'],
        ]);

        $payout = $requestPayout->handle(
            $this->group(),
            'project_expense',
            $this->cents($data['amount']),
            $project->fundAccount(),
            "{$project->name}: {$data['description']}",
            null,
            $data['payee_name'],
            $data['payee_phone'] ?? null,
            payable: $project,
        );

        $this->toast("Expense raised as payout #{$payout->number}. Two officials must approve it.");

        return back();
    }

    public function storeItem(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize(Permission::ProjectsManage->value);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'unit' => ['required', 'string', 'max:20'],
            'unit_price' => Money::rule(false, 0),
            'quantity' => ['nullable', 'integer', 'min:0'],
        ]);

        $item = $project->items()->create([
            'name' => $data['name'],
            'unit' => $data['unit'],
            'unit_price' => $this->cents($data['unit_price'] ?? 0),
            'quantity' => 0,
        ]);

        if (($data['quantity'] ?? 0) > 0) {
            app(ProjectBook::class)->adjustStock($item, 'in', (int) $data['quantity'], null, 'Opening stock');
        }

        $this->toast("{$item->name} added to stock.");

        return back();
    }

    public function updateItem(Request $request, Project $project, ProjectItem $item): RedirectResponse
    {
        Gate::authorize(Permission::ProjectsManage->value);
        abort_unless($item->project_id === $project->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'unit' => ['required', 'string', 'max:20'],
            'unit_price' => Money::rule(false, 0),
        ]);

        $item->update([...$data, 'unit_price' => $this->cents($data['unit_price'] ?? 0)]);
        $this->toast('Item saved.');

        return back();
    }

    public function stock(Request $request, Project $project, ProjectItem $item, ProjectBook $book): RedirectResponse
    {
        Gate::authorize(Permission::ProjectsManage->value);
        abort_unless($item->project_id === $project->id, 404);

        $data = $request->validate([
            'kind' => ['required', Rule::in(['in', 'out', 'adjust'])],
            'quantity' => ['required', 'integer', 'not_in:0'],
            'unit_cost' => Money::rule(false, 0),
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $book->adjustStock($item, $data['kind'], (int) $data['quantity'], isset($data['unit_cost']) ? $this->cents($data['unit_cost']) : null, $data['note'] ?? null);
        $this->toast('Stock updated.');

        return back();
    }

    public function sell(Request $request, Project $project, ProjectBook $book, RecordPayment $record, InitiateStkPayment $stk): RedirectResponse
    {
        Gate::authorize(Permission::ProjectsManage->value);

        $data = $request->validate([
            'project_item_id' => ['required', 'integer', Rule::exists('project_items', 'id')->where('project_id', $project->id)],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_price' => Money::rule(),
            'buyer_name' => ['required', 'string', 'max:120'],
            'buyer_phone' => ['nullable', 'string', 'max:20'],
            'member_id' => ['nullable', 'integer', Rule::exists('members', 'id')->where('group_id', $this->group()->id)],
            'sold_on' => ['required', 'date', 'before_or_equal:today'],
            'payment' => ['required', Rule::in(['none', 'cash', 'mpesa'])],
        ]);

        $sale = $book->sell(ProjectItem::findOrFail((int) $data['project_item_id']), [
            'buyer_name' => (string) $data['buyer_name'],
            'buyer_phone' => $data['buyer_phone'] ?? null,
            'member_id' => isset($data['member_id']) ? (int) $data['member_id'] : null,
            'quantity' => (int) $data['quantity'],
            'unit_price' => $this->cents($data['unit_price']),
            'sold_on' => (string) $data['sold_on'],
        ]);

        $message = "Sale recorded: {$sale->quantity} {$sale->item->unit} for ".Money::format($sale->total).'.';

        if ($data['payment'] === 'cash') {
            $record->handle($this->group(), 'cash', $sale->total, 'sale', $sale->member, $sale, null, CarbonImmutable::parse($data['sold_on']), $sale->buyer_name, $sale->buyer_phone);
            $message .= ' Paid in cash.';
        }

        if ($data['payment'] === 'mpesa') {
            if (! $sale->buyer_phone) {
                throw new RuleViolation('Enter the buyer\'s phone to send an M-Pesa prompt.');
            }

            $payment = $stk->handle($this->group(), $sale->total, $sale->buyer_phone, 'sale', $sale->member, $sale, $sale->buyer_name);
            $message .= $payment->status === 'failed' ? " M-Pesa prompt failed: {$payment->failure_reason}" : ' M-Pesa prompt sent to the buyer.';
        }

        $this->toast($message);

        return back();
    }

    public function salePayment(Request $request, Project $project, Sale $sale, RecordPayment $record): RedirectResponse
    {
        Gate::authorize(Permission::ProjectsManage->value);
        abort_unless($sale->project_id === $project->id, 404);

        $data = $request->validate([
            'amount' => Money::rule(),
            'channel' => ['required', Rule::in(['cash', 'paybill', 'manual'])],
            'reference' => ['nullable', 'required_if:channel,paybill', 'string', 'max:100'],
        ]);

        $amount = $this->cents($data['amount']);

        if ($amount > $sale->balance()) {
            throw new RuleViolation('That is more than the '.Money::format($sale->balance()).' still owed.');
        }

        $record->handle($this->group(), $data['channel'], $amount, 'sale', $sale->member, $sale, $data['reference'] ?? null, payerName: $sale->buyer_name, phone: $sale->buyer_phone);
        $this->toast('Payment recorded against the sale.');

        return back();
    }

    public function distribute(Request $request, Project $project, ProjectBook $book): RedirectResponse
    {
        Gate::authorize(Permission::ProjectsManage->value);

        $data = $request->validate(['amount' => Money::rule()]);
        $count = $book->distribute($project, $this->cents($data['amount']));
        $this->toast("Profit shared to {$count} members' savings.");

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::in(array_keys(Project::CATEGORIES))],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'share_basis' => ['required', Rule::in(['contribution', 'equal'])],
            'coordinator_member_id' => ['nullable', 'integer', Rule::exists('members', 'id')->where('group_id', $this->group()->id)],
            'status' => [$updating ? 'required' : 'nullable', Rule::in(['planning', 'active', 'closed'])],
        ]);
    }
}
