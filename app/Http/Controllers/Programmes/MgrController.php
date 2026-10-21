<?php

namespace App\Http\Controllers\Programmes;

use App\Domain\Access\Permission;
use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Ledger\Ledger;
use App\Domain\Members\MemberFinance;
use App\Domain\Mgr\MerryGoRound;
use App\Domain\Mgr\Models\MgrCycle;
use App\Domain\Mgr\Models\MgrSlot;
use App\Domain\Mgr\Models\MgrSwap;
use App\Http\Controllers\Controller;
use App\Support\Money;
use App\Support\RuleViolation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MgrController extends Controller
{
    public function index(Ledger $ledger, ChartOfAccounts $accounts): Response
    {
        return Inertia::render('mgr/index', [
            'cycles' => MgrCycle::query()->withCount('slots')->latest('starts_on')->get()->map(fn (MgrCycle $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'starts_on' => $c->starts_on->toDateString(),
                'status' => $c->status,
                'payout_amount' => $c->payout_amount,
                'slots' => $c->slots_count,
                'paid' => $c->slots()->where('status', 'paid')->count(),
            ]),
            'pot' => $ledger->balance($accounts->get($this->group(), ChartOfAccounts::MGR)),
            'mgrShare' => (int) $this->group()->settings()->get('mgr_share'),
            'members' => $this->memberOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize(Permission::MgrManage->value);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'starts_on' => ['required', 'date'],
            'payout_amount' => Money::rule(false, 0),
        ]);

        $cycle = MgrCycle::create([
            'name' => $data['name'],
            'starts_on' => $data['starts_on'],
            'payout_amount' => $this->cents($data['payout_amount'] ?? 0),
        ]);

        $this->toast('Cycle created. Choose the members and run the ballot.');

        return to_route('mgr.show', $cycle);
    }

    public function show(Request $request, MgrCycle $cycle, MemberFinance $finance): Response
    {
        $user = $request->user();
        $mySlot = $cycle->slots()->where('member_id', $user->member->id)->first();

        return Inertia::render('mgr/show', [
            'cycle' => [
                'id' => $cycle->id,
                'name' => $cycle->name,
                'starts_on' => $cycle->starts_on->toDateString(),
                'status' => $cycle->status,
                'payout_amount' => $cycle->payout_amount,
                'ballot_seed' => $cycle->ballot_seed,
                'balloted_at' => $cycle->balloted_at?->toIso8601String(),
            ],
            'slots' => $cycle->slots()->with('member', 'payout')->get()->map(fn (MgrSlot $s) => [
                'id' => $s->id,
                'position' => $s->position,
                'member_id' => $s->member_id,
                'member' => $s->member->name,
                'scheduled_on' => $s->scheduled_on->toDateString(),
                'status' => $s->status,
                'deferred_count' => $s->deferred_count,
                'payout_id' => $s->payout_id,
                'payout_status' => $s->payout?->status,
                'in_arrears' => $user->hasPermission(Permission::FinanceView->value) ? $finance->arrears($s->member) > 0 : null,
                'mine' => $s->member_id === $user->member->id,
            ]),
            'swaps' => $cycle->swaps()->with('requesterSlot.member', 'targetSlot.member')->get()->map(fn (MgrSwap $w) => [
                'id' => $w->id,
                'requester' => $w->requesterSlot->member->name,
                'requester_position' => $w->requesterSlot->position,
                'target' => $w->targetSlot->member->name,
                'target_position' => $w->targetSlot->position,
                'status' => $w->status,
                'reason' => $w->reason,
                'awaiting_me' => $w->status === 'pending_member' && $w->targetSlot->member_id === $user->member->id,
                'created_at' => $w->created_at->toIso8601String(),
            ]),
            'mySlotId' => $mySlot?->id,
            'members' => $this->memberOptions(),
        ]);
    }

    public function ballot(Request $request, MgrCycle $cycle, MerryGoRound $mgr): RedirectResponse
    {
        Gate::authorize(Permission::MgrManage->value);

        $data = $request->validate([
            'member_ids' => ['required', 'array', 'min:2'],
            'member_ids.*' => ['integer', Rule::exists('members', 'id')->where('group_id', $this->group()->id)],
        ]);

        $mgr->ballot($cycle, $data['member_ids'], $request->user());
        $this->toast('Ballot done. The order is final and the seed is recorded for anyone to check.');

        return back();
    }

    public function release(MgrCycle $cycle, MerryGoRound $mgr): RedirectResponse
    {
        Gate::authorize(Permission::MgrManage->value);

        $result = $mgr->releaseNext($cycle);
        $deferred = $result['deferred'] ? ' Moved to the back for arrears: '.implode(', ', $result['deferred']).'.' : '';

        if ($result['payout'] === null) {
            $this->toast('No more turns to pay in this cycle.'.$deferred, 'info');

            return back();
        }

        $this->toast("Payout #{$result['payout']->number} raised for {$result['payout']->payee_name}. It needs two approvals.".$deferred);

        return back();
    }

    public function requestSwap(Request $request, MgrCycle $cycle, MerryGoRound $mgr): RedirectResponse
    {
        $data = $request->validate([
            'target_slot_id' => ['required', 'integer'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $mine = $cycle->slots()->where('member_id', $request->user()->member->id)->first()
            ?? throw new RuleViolation('You are not in this cycle.');
        $theirs = $cycle->slots()->findOrFail((int) $data['target_slot_id']);

        $mgr->requestSwap($mine, $theirs, $data['reason'] ?? null);
        $this->toast("Swap requested. {$theirs->member->name} must agree, then the chair approves.");

        return back();
    }

    public function respondSwap(Request $request, MgrSwap $swap, MerryGoRound $mgr): RedirectResponse
    {
        $data = $request->validate(['accept' => ['required', 'boolean']]);
        $member = $request->user()->member;

        if ($swap->status === 'pending_member') {
            abort_unless($swap->targetSlot->member_id === $member->id, 403);
            $mgr->respondAsMember($swap, $data['accept']);
            $this->toast($data['accept'] ? 'You agreed. The chair will approve the swap.' : 'Swap declined.');

            return back();
        }

        Gate::authorize(Permission::MgrApproveSwaps->value);
        $mgr->respondAsChair($swap, $request->user(), $data['accept']);
        $this->toast($data['accept'] ? 'Swap approved. The turns have been exchanged.' : 'Swap declined.');

        return back();
    }
}
