<?php

namespace App\Http\Controllers\Members;

use App\Domain\Access\Permission;
use App\Domain\Dues\Models\Due;
use App\Domain\Members\Actions\DeleteMember;
use App\Domain\Members\Actions\MemberExit;
use App\Domain\Members\Actions\SaveMember;
use App\Domain\Members\MemberFinance;
use App\Domain\Members\Models\Dependant;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Models\MemberChangeRequest;
use App\Domain\Payments\Models\Payment;
use App\Http\Controllers\Controller;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function index(Request $request, MemberFinance $finance): Response
    {
        $showMoney = $request->user()->hasPermission(Permission::FinanceView->value);

        $members = Member::query()
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(fn ($w) => $w
                ->whereLike('name', "%{$term}%")
                ->orWhereLike('member_number', "%{$term}%")
                ->orWhereLike('phone', '%'.(Phone::normalize($term) ?? $term).'%')))
            ->orderBy('member_number')
            ->get()
            ->map(fn (Member $m) => [
                'id' => $m->id,
                'member_number' => $m->member_number,
                'name' => $m->name,
                'phone' => Phone::display($m->phone),
                'email' => $m->email,
                'status' => $m->status,
                'joined_on' => $m->joined_on->toDateString(),
                'has_login' => $m->user_id !== null,
                'positions' => $m->positionNames(),
                'savings' => $showMoney ? $finance->savings($m) : null,
                'arrears' => $showMoney ? $finance->arrears($m) : null,
            ]);

        return Inertia::render('members/index', [
            'members' => $members,
            'filters' => $request->only('q', 'status'),
            'showMoney' => $showMoney,
            'pendingChanges' => $request->user()->hasPermission(Permission::MembersManage->value)
                ? MemberChangeRequest::query()->with('member')->where('status', 'pending')->oldest()->get()->map(fn (MemberChangeRequest $c) => [
                    'member_id' => $c->member_id,
                    'name' => $c->member->name,
                    'fields' => implode(', ', array_map(fn ($f) => MemberChangeRequest::FIELDS[$f] ?? $f, array_keys($c->changes))),
                    'at' => $c->created_at->toIso8601String(),
                ])
                : [],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('members/form', ['member' => null]);
    }

    public function store(Request $request, SaveMember $save): RedirectResponse
    {
        $member = $save->handle($this->group(), $this->validated($request));
        $this->toast("{$member->name} added as {$member->member_number}.");

        return to_route('members.show', $member);
    }

    public function show(Request $request, Member $member, MemberFinance $finance, MemberExit $exit, DeleteMember $delete): Response
    {
        $user = $request->user();
        $isSelf = $user->member?->id === $member->id;
        abort_unless($isSelf || $user->hasPermission(Permission::MembersView->value), 403);
        $canMoney = $isSelf || $user->hasPermission(Permission::FinanceView->value);

        return Inertia::render('members/show', [
            'member' => [
                ...$this->present($member),
                'has_login' => $member->user_id !== null,
                'last_login_at' => $member->user?->last_login_at?->toIso8601String(),
                'positions' => $member->terms()->with('position')->latest('starts_on')->get()->map(fn ($t) => [
                    'id' => $t->id,
                    'position' => $t->position->name,
                    'starts_on' => $t->starts_on->toDateString(),
                    'ends_on' => $t->ends_on?->toDateString(),
                    'is_acting' => $t->is_acting,
                    'current' => $t->isCurrent(),
                ]),
                'dependants' => $member->dependants->map(fn (Dependant $d) => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'relationship' => $d->relationship,
                    'date_of_birth' => $d->date_of_birth?->toDateString(),
                    'phone' => $d->phone,
                ]),
                'notice_ends_on' => $exit->noticeEndsOn($member)?->toDateString(),
            ],
            'finance' => $canMoney ? [
                ...$finance->summary($member),
                'exit_refund' => $finance->exitRefund($member),
                'dues' => Due::query()->where('member_id', $member->id)->latest('due_on')->limit(24)->get()->map(fn (Due $d) => [
                    'id' => $d->id,
                    'type' => $d->type,
                    'description' => $d->description,
                    'amount' => $d->amount,
                    'paid' => $d->paid,
                    'due_on' => $d->due_on->toDateString(),
                    'status' => $d->status,
                ]),
                'payments' => Payment::query()->where('member_id', $member->id)->latest()->limit(20)->get()->map(fn (Payment $p) => [
                    'id' => $p->id,
                    'amount' => $p->amount,
                    'channel' => $p->channel,
                    'purpose' => $p->purpose,
                    'status' => $p->status,
                    'receipt' => $p->receipt_number,
                    'date' => ($p->received_at ?? $p->created_at)->toDateString(),
                ]),
            ] : null,
            'isSelf' => $isSelf,
            'changeRequest' => $this->pendingChange($member),
            'changeFields' => MemberChangeRequest::FIELDS,
            'canDecideChanges' => ! $isSelf && $user->hasPermission(Permission::MembersManage->value),
            'deleteBlockers' => $user->hasPermission(Permission::MembersManage->value) && ! $isSelf ? $delete->blockers($member) : null,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pendingChange(Member $member): ?array
    {
        $change = MemberChangeRequest::query()->where('member_id', $member->id)->where('status', 'pending')->latest('id')->first();

        if ($change === null) {
            return null;
        }

        $show = fn (string $field, ?string $value) => $value !== null && str_ends_with($field, 'phone') ? Phone::display($value) : $value;

        return [
            'id' => $change->id,
            'at' => $change->created_at->toIso8601String(),
            'rows' => collect($change->changes)->map(fn ($new, $field) => [
                'field' => MemberChangeRequest::FIELDS[$field] ?? $field,
                'from' => $show($field, $change->previous[$field] ?? null),
                'to' => $show($field, $new),
            ])->values(),
        ];
    }

    public function destroy(Request $request, Member $member, DeleteMember $delete): RedirectResponse
    {
        $name = "{$member->member_number} {$member->name}";
        $delete->handle($member, $request->user());
        $this->toast("{$name} deleted. The next new member gets the next free number.");

        return to_route('members.index');
    }

    public function edit(Member $member): Response
    {
        return Inertia::render('members/form', ['member' => $this->present($member)]);
    }

    public function update(Request $request, Member $member, SaveMember $save): RedirectResponse
    {
        $save->handle($this->group(), $this->validated($request, $member), $member);
        $this->toast('Member details saved.');

        return to_route('members.show', $member);
    }

    /**
     * Members change their own notification and privacy choices.
     */
    public function preferences(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'notify_sms' => ['required', 'boolean'],
            'notify_email' => ['required', 'boolean'],
            'reminders_opt_out' => ['required', 'boolean'],
            'show_name_publicly' => ['required', 'boolean'],
            'locale' => ['required', Rule::in(['en', 'sw'])],
        ]);

        $request->user()->member->forceFill($data)->save();
        $request->user()->forceFill(['locale' => $data['locale']])->save();
        $this->toast('Preferences saved.');

        return back();
    }

    public function storeDependant(Request $request, Member $member): RedirectResponse
    {
        Gate::authorize(Permission::MembersManage->value);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'relationship' => ['required', Rule::in(Dependant::RELATIONSHIPS)],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $member->dependants()->create($data);
        $this->toast("{$data['name']} added as a welfare beneficiary.");

        return back();
    }

    public function destroyDependant(Member $member, Dependant $dependant): RedirectResponse
    {
        Gate::authorize(Permission::MembersManage->value);
        abort_unless($dependant->member_id === $member->id, 404);

        $dependant->delete();
        $this->toast('Beneficiary removed.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Member $member = null): array
    {
        $groupId = $this->group()->id;

        $request->merge(['phone' => Phone::normalize($request->input('phone')) ?? $request->input('phone')]);

        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'regex:/^254[17]\d{8}$/', Rule::unique('members', 'phone')->where('group_id', $groupId)->ignore($member?->id)],
            'email' => ['nullable', 'email', 'max:120'],
            'id_number' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', Rule::in(['female', 'male'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'joined_on' => ['required', 'date'],
            'member_number' => ['nullable', 'string', 'max:20', Rule::unique('members', 'member_number')->where('group_id', $groupId)->ignore($member?->id)],
            'status' => [$member ? 'required' : 'nullable', Rule::in(['active', 'suspended'])],
            'nominee_name' => ['nullable', 'string', 'max:120'],
            'nominee_phone' => ['nullable', 'string', 'max:20'],
            'nominee_relationship' => ['nullable', 'string', 'max:40'],
            'nominee_id_number' => ['nullable', 'string', 'max:20'],
            'notify_sms' => ['boolean'],
            'notify_email' => ['boolean'],
            'locale' => ['required', Rule::in(['en', 'sw'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['phone.regex' => 'Enter a valid Kenyan mobile number, e.g. 0712 345 678.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Member $member): array
    {
        return [
            'id' => $member->id,
            'member_number' => $member->member_number,
            'name' => $member->name,
            'phone' => Phone::display($member->phone),
            'email' => $member->email,
            'id_number' => $member->id_number,
            'gender' => $member->gender,
            'date_of_birth' => $member->date_of_birth?->toDateString(),
            'joined_on' => $member->joined_on->toDateString(),
            'status' => $member->status,
            'nominee_name' => $member->nominee_name,
            'nominee_phone' => $member->nominee_phone ? Phone::display($member->nominee_phone) : null,
            'nominee_relationship' => $member->nominee_relationship,
            'nominee_id_number' => $member->nominee_id_number,
            'notify_sms' => $member->notify_sms,
            'notify_email' => $member->notify_email,
            'reminders_opt_out' => $member->reminders_opt_out,
            'show_name_publicly' => $member->show_name_publicly,
            'locale' => $member->locale,
            'exit_notice_on' => $member->exit_notice_on?->toDateString(),
            'exited_on' => $member->exited_on?->toDateString(),
            'exit_reason' => $member->exit_reason,
            'notes' => $member->notes,
        ];
    }
}
