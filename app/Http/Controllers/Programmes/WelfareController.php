<?php

namespace App\Http\Controllers\Programmes;

use App\Domain\Access\Permission;
use App\Domain\Audit\Audit;
use App\Domain\Dues\Models\Due;
use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Ledger\Ledger;
use App\Domain\Members\Models\Dependant;
use App\Domain\Members\Models\Member;
use App\Domain\Notifications\Notifier;
use App\Domain\Payouts\Actions\RequestPayout;
use App\Domain\Welfare\Models\WelfareCase;
use App\Http\Controllers\Controller;
use App\Support\Money;
use App\Support\RuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Welfare covers the member, spouse, children and parents. A case can be
 * paid from the welfare fund, topped up by a levy on every member, or both.
 */
class WelfareController extends Controller
{
    public function index(Ledger $ledger, ChartOfAccounts $accounts): Response
    {
        return Inertia::render('welfare/index', [
            'cases' => WelfareCase::query()->with('member:id,name')->latest('event_on')->get()->map(fn (WelfareCase $c) => [
                'id' => $c->id,
                'member' => $c->member->name,
                'beneficiary_name' => $c->beneficiary_name,
                'relationship' => $c->relationship,
                'event' => $c->event,
                'event_on' => $c->event_on->toDateString(),
                'benefit_amount' => $c->benefit_amount,
                'levy_per_member' => $c->levy_per_member,
                'status' => $c->status,
            ]),
            'fund' => $ledger->balance($accounts->get($this->group(), ChartOfAccounts::WELFARE)),
            'benefits' => $this->group()->settings()->get('welfare_benefits'),
            'members' => Member::query()->whereIn('status', ['active', 'exiting', 'deceased'])->with('dependants')->orderBy('name')->get()->map(fn (Member $m) => [
                'id' => $m->id,
                'label' => "{$m->name} ({$m->member_number})",
                'dependants' => $m->dependants->map(fn (Dependant $d) => ['id' => $d->id, 'name' => $d->name, 'relationship' => $d->relationship]),
            ]),
            'events' => WelfareCase::EVENTS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize(Permission::WelfareManage->value);

        $data = $request->validate([
            'member_id' => ['required', 'integer', Rule::exists('members', 'id')->where('group_id', $this->group()->id)],
            'dependant_id' => ['nullable', 'integer'],
            'event' => ['required', Rule::in(array_keys(WelfareCase::EVENTS))],
            'event_on' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:2000'],
            'benefit_amount' => Money::rule(false, 0),
        ]);

        $member = Member::findOrFail($data['member_id']);
        $dependant = isset($data['dependant_id']) ? $member->dependants()->find($data['dependant_id']) : null;

        if (isset($data['dependant_id']) && $dependant === null) {
            throw new RuleViolation('That beneficiary is not registered under this member.');
        }

        $relationship = $dependant?->relationship ?? 'self';
        $benefit = ($data['benefit_amount'] ?? null) !== null && $data['benefit_amount'] !== ''
            ? $this->cents($data['benefit_amount'])
            : $this->group()->settings()->welfareBenefit($data['event'], $relationship);

        $case = WelfareCase::create([
            'member_id' => $member->id,
            'dependant_id' => $dependant?->id,
            'beneficiary_name' => $dependant?->name ?? $member->name,
            'relationship' => $relationship,
            'event' => $data['event'],
            'event_on' => $data['event_on'],
            'description' => $data['description'] ?? null,
            'benefit_amount' => $benefit,
            'opened_by' => $request->user()->id,
        ]);

        Audit::log('welfare.opened', "Welfare case opened for {$case->beneficiary_name} ({$member->name})", $case);
        $this->toast('Welfare case opened.');

        return to_route('welfare.show', $case);
    }

    public function show(WelfareCase $case, Ledger $ledger, ChartOfAccounts $accounts): Response
    {
        $levies = Due::query()->where('source_type', $case->getMorphClass())->where('source_id', $case->id)->with('member:id,name')->get();

        return Inertia::render('welfare/show', [
            'case' => [
                'id' => $case->id,
                'member' => $case->member->name,
                'member_id' => $case->member_id,
                'beneficiary_name' => $case->beneficiary_name,
                'relationship' => $case->relationship,
                'event' => $case->event,
                'event_on' => $case->event_on->toDateString(),
                'description' => $case->description,
                'benefit_amount' => $case->benefit_amount,
                'levy_per_member' => $case->levy_per_member,
                'status' => $case->status,
                'payout_id' => $case->payout_id,
            ],
            'levies' => $levies->map(fn (Due $d) => [
                'id' => $d->id,
                'member' => $d->member->name,
                'amount' => $d->amount,
                'paid' => $d->paid,
                'status' => $d->status,
            ]),
            'levyTotals' => ['expected' => (int) $levies->sum('amount'), 'collected' => (int) $levies->sum('paid')],
            'fund' => $ledger->balance($accounts->get($this->group(), ChartOfAccounts::WELFARE)),
            'canManage' => request()->user()->hasPermission(Permission::WelfareManage->value),
        ]);
    }

    public function decide(Request $request, WelfareCase $case): RedirectResponse
    {
        Gate::authorize(Permission::WelfareManage->value);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'benefit_amount' => Money::rule(false, 0),
        ]);

        if ($case->status !== 'open') {
            throw new RuleViolation("This case is already {$case->status}.");
        }

        $case->update([
            'status' => $data['decision'],
            'benefit_amount' => isset($data['benefit_amount']) && $data['benefit_amount'] !== '' ? $this->cents($data['benefit_amount']) : $case->benefit_amount,
        ]);

        Audit::log("welfare.{$data['decision']}", "Welfare case for {$case->beneficiary_name} {$data['decision']}", $case);
        $this->toast($data['decision'] === 'approved' ? 'Case approved. Raise a levy or request the payout.' : 'Case rejected.');

        return back();
    }

    public function levy(Request $request, WelfareCase $case, Notifier $notifier): RedirectResponse
    {
        Gate::authorize(Permission::WelfareManage->value);

        $data = $request->validate([
            'amount' => Money::rule(),
            'due_on' => ['required', 'date', 'after_or_equal:today'],
        ]);

        if (! in_array($case->status, ['open', 'approved'], true)) {
            throw new RuleViolation('A levy can only be raised while the case is open or approved.');
        }

        if ($case->levy_per_member !== null) {
            throw new RuleViolation('A levy has already been raised for this case.');
        }

        $amount = $this->cents($data['amount']);
        $members = Member::query()->active()->where('id', '!=', $case->member_id)->get();

        DB::transaction(function () use ($case, $members, $amount, $data) {
            foreach ($members as $member) {
                Due::create([
                    'member_id' => $member->id,
                    'type' => 'levy',
                    'description' => "Welfare levy: {$case->beneficiary_name}",
                    'amount' => $amount,
                    'breakdown' => ['welfare' => $amount],
                    'due_on' => $data['due_on'],
                    'source_type' => $case->getMorphClass(),
                    'source_id' => $case->id,
                ]);
            }

            $case->update(['levy_per_member' => $amount]);
        });

        foreach ($members as $member) {
            $notifier->toMember($member, 'welfare_levy', [
                'amount' => Money::format($amount),
                'beneficiary' => $case->beneficiary_name,
                'due_date' => CarbonImmutable::parse($data['due_on'])->format('j M Y'),
            ]);
        }

        Audit::log('welfare.levy', 'Levy of '.Money::format($amount)." raised on {$members->count()} members for {$case->beneficiary_name}", $case);
        $this->toast("Levy raised on {$members->count()} members and they have been notified.");

        return back();
    }

    public function payout(WelfareCase $case, RequestPayout $requestPayout, ChartOfAccounts $accounts): RedirectResponse
    {
        Gate::authorize(Permission::PayoutsRequest->value);

        if ($case->status !== 'approved') {
            throw new RuleViolation('Approve the case before requesting the payout.');
        }

        $member = $case->member;
        $deceased = $case->relationship === 'self' && $case->event === 'death';

        $payout = DB::transaction(function () use ($case, $requestPayout, $accounts, $member, $deceased) {
            $payout = $requestPayout->handle(
                $this->group(),
                'welfare',
                $case->benefit_amount,
                $accounts->get($this->group(), ChartOfAccounts::WELFARE),
                'Welfare: '.WelfareCase::EVENTS[$case->event]." of {$case->beneficiary_name}",
                $deceased ? null : $member,
                $deceased ? ($member->nominee_name ?: "Family of {$member->name}") : $member->name,
                $deceased ? $member->nominee_phone : $member->phone,
                payable: $case,
            );

            $case->update(['status' => 'payout_requested', 'payout_id' => $payout->id]);

            return $payout;
        });

        $this->toast("Payout #{$payout->number} requested from the welfare fund.");

        return to_route('payouts.show', $payout);
    }
}
