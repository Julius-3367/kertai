<?php

namespace App\Http\Controllers\Finance;

use App\Domain\Access\Permission;
use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Ledger\Ledger;
use App\Domain\Ledger\Models\Account;
use App\Domain\Loans\LoanApplications;
use App\Domain\Loans\LoanBook;
use App\Domain\Loans\LoanEligibility;
use App\Domain\Loans\Models\Loan;
use App\Domain\Loans\Models\LoanGuarantor;
use App\Domain\Loans\Models\LoanTransaction;
use App\Domain\Members\MemberFinance;
use App\Domain\Members\Models\Member;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Money;
use App\Support\RuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LoanController extends Controller
{
    public function __construct(private readonly LoanBook $book) {}

    /**
     * The loans register for officials.
     */
    public function index(Request $request, Ledger $ledger, ChartOfAccounts $accounts): Response
    {
        $loans = Loan::query()->with('member:id,name,member_number')->latest('number')->get();
        $balances = $this->book->balances($loans->modelKeys());
        $rows = $loans->map(fn (Loan $l) => $this->row($l, $balances[$l->id]));
        $active = $rows->where('status', 'active');
        $settings = $this->group()->settings();
        $yearStart = CarbonImmutable::today()->startOfYear()->toDateString();

        $interestThisYear = LoanTransaction::query()
            ->whereIn('loan_id', $loans->modelKeys())
            ->whereDate('occurred_on', '>=', $yearStart);

        return Inertia::render('loans/index', [
            'loans' => $rows->values(),
            'summary' => [
                'principal' => (int) $active->sum('owed.principal'),
                'interest' => (int) $active->sum('owed.interest'),
                'active' => $active->count(),
                'overdue' => $active->where('overdue', true)->count(),
                'overdue_amount' => (int) $active->where('overdue', true)->sum('owed.total'),
                'lendable' => $this->book->lendable($this->group()),
                'interest_charged' => (int) (clone $interestThisYear)->where('type', 'interest')->sum('interest'),
                'interest_collected' => -(int) (clone $interestThisYear)->whereIn('type', ['repayment', 'recovery', 'reversal'])->sum('interest'),
            ],
            'rules' => [
                'loans_enabled' => $settings->loansEnabled(),
                'interest_on' => $settings->loanInterestOn(),
                ...$settings->loanTerms(),
                'repayment_order' => $settings->get('loan_repayment_order'),
            ],
            'members' => $this->memberOptions(),
            // Where an old loan's principal sits in the books today: not yet
            // recorded (add it to the group fund), or already counted in an
            // opening bank or cash balance (move it out of there).
            'countedIn' => Account::query()
                ->whereIn('purpose', ['group', 'bank', 'mpesa', 'cash'])
                ->orderByDesc('kind')
                ->orderBy('code')
                ->get()
                ->map(fn (Account $a) => [
                    'id' => $a->id,
                    'label' => $a->kind === 'asset' ? "Already counted in {$a->name}" : 'Not yet in the books (add to the group fund)',
                ]),
            'can' => [
                'manage' => $request->user()->hasPermission(Permission::LoansManage->value),
            ],
        ]);
    }

    /**
     * A member's own loans and the guarantees they have been asked for.
     */
    public function mine(Request $request, LoanEligibility $eligibility, MemberFinance $finance): Response
    {
        $member = $this->member() ?? abort(403);
        $loans = Loan::query()->where('member_id', $member->id)->latest('number')->get();
        $balances = $this->book->balances($loans->modelKeys());

        return Inertia::render('loans/mine', [
            'loans' => $loans->map(fn (Loan $l) => $this->row($l, $balances[$l->id]))->values(),
            'guarantees' => LoanGuarantor::query()
                ->with(['loan' => fn ($q) => $q->with('member:id,name')])
                ->where('member_id', $member->id)
                ->latest('id')
                ->get()
                ->map(fn (LoanGuarantor $g) => [
                    'id' => $g->id,
                    'loan_id' => $g->loan_id,
                    'reference' => $g->loan->reference(),
                    'borrower' => $g->loan->member->name,
                    'amount' => $g->amount,
                    'status' => $g->status,
                    'loan_status' => $g->loan->status,
                ]),
            'lending' => [
                'enabled' => $this->group()->settings()->loansEnabled(),
                'eligible' => LoanEligibility::passes($eligibility->check($member)),
                'max_amount' => $eligibility->maxAmount($member),
                'savings' => $finance->savings($member),
                'pledged' => $this->book->guaranteed($member),
            ],
        ]);
    }

    public function create(LoanEligibility $eligibility): Response
    {
        $member = $this->member() ?? abort(403);
        $settings = $this->group()->settings();

        return Inertia::render('loans/apply', [
            'rules' => $eligibility->check($member),
            'limits' => [
                'max_amount' => $eligibility->maxAmount($member),
                'max_term' => (int) $settings->get('loan_max_term_months'),
                'guarantors_required' => $eligibility->guarantorsRequired($member),
                'free_savings' => $eligibility->freeSavings($member),
                ...$settings->loanTerms(),
                'interest_on' => $settings->loanInterestOn(),
            ],
            'members' => collect($this->memberOptions())->reject(fn ($m) => $m['id'] === $member->id)->values(),
        ]);
    }

    public function store(Request $request, LoanApplications $applications): RedirectResponse
    {
        $member = $this->member() ?? abort(403);
        $data = $request->validate([
            'amount' => Money::rule(),
            'term_months' => ['required', 'integer', 'min:1', 'max:60'],
            'purpose' => ['required', 'string', 'max:200'],
            'guarantors' => ['array', 'max:10'],
            'guarantors.*.member_id' => ['required', 'integer', 'distinct', Rule::exists('members', 'id')->where('group_id', $this->group()->id)],
            'guarantors.*.amount' => Money::rule(),
        ], [
            'guarantors.*.member_id.distinct' => 'Each guarantor can be named once.',
        ]);

        /** @var array<int, array{member_id: int|string, amount: string}> $named */
        $named = $data['guarantors'] ?? [];
        $guarantors = [];

        foreach ($named as $g) {
            $guarantors[(int) $g['member_id']] = $this->cents($g['amount']);
        }

        $loan = $applications->apply($member, $this->cents($data['amount']), (int) $data['term_months'], $data['purpose'], $guarantors);
        $this->toast("Application {$loan->reference()} sent. Your guarantors have been asked to accept.");

        return to_route('loans.show', $loan);
    }

    public function show(Request $request, Loan $loan, LoanApplications $applications, LoanEligibility $eligibility, Ledger $ledger, ChartOfAccounts $accounts): Response
    {
        $user = $request->user();
        $me = $user->member;
        $myGuarantee = $loan->guarantors()->where('member_id', $me?->id)->first();
        $isBorrower = $loan->member_id === $me?->id;

        abort_unless($isBorrower || $myGuarantee || $user->hasPermission(Permission::FinanceView->value), 403);

        $loan->load(['member:id,name,member_number,phone', 'guarantors.member:id,name,member_number', 'payout:id,number,status']);
        $owed = $this->book->balance($loan);
        $official = $user->hasPermission(Permission::FinanceView->value);
        $savings = $accounts->get($loan->group_id, ChartOfAccounts::SAVINGS);

        $running = ['principal' => 0, 'interest' => 0];
        $history = LoanTransaction::query()->where('loan_id', $loan->id)->orderBy('occurred_on')->orderBy('id')->get()
            ->map(function (LoanTransaction $t) use (&$running) {
                $running['principal'] += $t->principal;
                $running['interest'] += $t->interest;

                return [
                    'id' => $t->id,
                    'date' => $t->occurred_on->toDateString(),
                    'type' => $t->type,
                    'label' => $t->label(),
                    'description' => $t->description,
                    'principal' => $t->principal,
                    'interest' => $t->interest,
                    'balance' => $running['principal'] + $running['interest'],
                ];
            })->reverse()->values();

        return Inertia::render('loans/show', [
            'loan' => [
                ...$this->row($loan, $owed),
                'purpose' => $loan->purpose,
                'term_months' => $loan->term_months,
                'instalment' => $loan->instalment,
                'interest_note' => $loan->interest_note,
                'next_interest_on' => $loan->next_interest_on?->toDateString(),
                'closed_on' => $loan->closed_on?->toDateString(),
                'resolution_reference' => $loan->resolution_reference,
                'notes' => $official ? $loan->notes : null,
                'payout' => $loan->payout ? ['id' => $loan->payout->id, 'number' => $loan->payout->number, 'status' => $loan->payout->status] : null,
                'charging_interest' => $loan->status === 'active' && $loan->interest_period === 'month' && $this->book->chargesInterest($loan),
            ],
            'history' => $history,
            'guarantors' => $loan->guarantors->map(fn (LoanGuarantor $g) => [
                'id' => $g->id,
                'member_id' => $g->member_id,
                'name' => $g->member->name,
                'amount' => $g->amount,
                'status' => $g->status,
                'recovered' => $official ? $this->book->recoveredFrom($loan, $g->member) : null,
            ]),
            'rules' => $loan->status === 'pending' && ($official || $isBorrower)
                ? $eligibility->check($loan->member, $loan->principal, $loan->term_months, $loan)
                : [],
            'recoverFrom' => $user->hasPermission(Permission::LoansAdjust->value) && $loan->status === 'active'
                ? collect([$loan->member])
                    ->merge($loan->guarantors->where('status', 'accepted')->pluck('member'))
                    ->map(fn (Member $m) => [
                        'id' => $m->id,
                        'label' => $m->id === $loan->member_id ? "{$m->name} (borrower)" : "{$m->name} (guarantor)",
                        'savings' => $ledger->balance($savings, $m->id),
                    ])->values()
                : [],
            'groupInterestOn' => $this->group()->settings()->loanInterestOn(),
            'myGuarantee' => $myGuarantee ? ['id' => $myGuarantee->id, 'amount' => $myGuarantee->amount, 'status' => $myGuarantee->status] : null,
            'can' => [
                'submit' => $loan->status === 'pending' && $user->hasPermission(Permission::LoansManage->value) && $applications->ready($loan),
                'reject' => $loan->status === 'pending' && $user->hasPermission(Permission::LoansManage->value),
                'withdraw' => $loan->status === 'pending' && $isBorrower,
                'adjust' => $loan->status === 'active' && $user->hasPermission(Permission::LoansAdjust->value),
                'record' => $loan->status === 'active' && $user->hasPermission(Permission::PaymentsRecord->value),
                'pay' => $loan->status === 'active' && $owed['total'] > 0 && ($isBorrower || $user->hasPermission(Permission::PaymentsRecord->value)),
            ],
            'phone' => $isBorrower ? $me->phone : $loan->member->phone,
        ]);
    }

    /**
     * Record a loan given before the group moved to Kertai.
     */
    public function bringForward(Request $request): RedirectResponse
    {
        $groupId = $this->group()->id;
        $data = $request->validate([
            'member_id' => ['required', 'integer', Rule::exists('members', 'id')->where('group_id', $groupId)],
            'principal' => Money::rule(true, 0),
            'interest' => Money::rule(false, 0),
            'issued_on' => ['required', 'date', 'before_or_equal:today'],
            'due_on' => ['nullable', 'date', 'after_or_equal:issued_on'],
            'instalment' => Money::rule(false, 0),
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'interest_method' => ['required', Rule::in(['flat', 'reducing'])],
            'interest_period' => ['required', Rule::in(['month', 'once'])],
            'interest_paused' => ['boolean'],
            'counted_in' => ['required', 'integer', Rule::exists('accounts', 'id')->where('group_id', $groupId)],
            'purpose' => ['nullable', 'string', 'max:200'],
            'resolution_reference' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $instalment = $this->cents($data['instalment'] ?? null);

        $loan = $this->book->bringForward(Member::findOrFail((int) $data['member_id']), [
            'principal' => $this->cents($data['principal']),
            'interest' => $this->cents($data['interest'] ?? null),
            'issued_on' => CarbonImmutable::parse($data['issued_on']),
            'due_on' => isset($data['due_on']) ? CarbonImmutable::parse($data['due_on']) : null,
            'instalment' => $instalment > 0 ? $instalment : null,
            'interest_rate' => (float) $data['interest_rate'],
            'interest_method' => $data['interest_method'],
            'interest_period' => $data['interest_period'],
            'interest_paused' => (bool) ($data['interest_paused'] ?? false),
            'purpose' => $data['purpose'] ?? null,
            'resolution_reference' => $data['resolution_reference'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], Account::findOrFail((int) $data['counted_in']), $request->user());

        $this->toast("Loan {$loan->reference()} recorded for {$loan->member->name}.");

        return to_route('loans.show', $loan);
    }

    public function respond(Request $request, Loan $loan, LoanApplications $applications): RedirectResponse
    {
        $data = $request->validate(['accept' => ['required', 'boolean']]);
        $member = $this->member() ?? abort(403);
        $guarantee = $loan->guarantors()->where('member_id', $member->id)->firstOrFail();

        $applications->respond($guarantee, $member, (bool) $data['accept']);
        $this->toast($data['accept'] ? 'Thank you. You are now a guarantor on this loan.' : 'You declined to guarantee this loan.');

        return back();
    }

    public function withdraw(Loan $loan, LoanApplications $applications): RedirectResponse
    {
        $applications->cancel($loan, $this->member() ?? abort(403));
        $this->toast('Application withdrawn.');

        return to_route('loans.mine');
    }

    public function submit(Loan $loan, LoanApplications $applications): RedirectResponse
    {
        $payout = $applications->submit($loan);
        $this->toast("Loan sent for approval as payout #{$payout->number}. Two officials must approve it before it is paid.");

        return back();
    }

    public function reject(Request $request, Loan $loan, LoanApplications $applications): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']]);
        $applications->reject($loan, $data['reason'], $request->user());
        $this->toast('Application turned down.');

        return back();
    }

    public function interest(Request $request, Loan $loan): RedirectResponse
    {
        $data = $request->validate([
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'interest_method' => ['required', Rule::in(['flat', 'reducing'])],
            'interest_period' => ['required', Rule::in(['month', 'once'])],
            'interest_paused' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'max:200'],
        ]);

        $this->book->setInterest($loan, (float) $data['interest_rate'], $data['interest_method'], $data['interest_period'], (bool) $data['interest_paused'], $data['reason']);
        $this->toast($data['interest_paused'] ? 'Interest stopped on this loan.' : 'Interest terms saved.');

        return back();
    }

    public function waive(Request $request, Loan $loan): RedirectResponse
    {
        $data = $request->validate([
            'part' => ['required', Rule::in(['interest', 'principal', 'all'])],
            'amount' => Money::rule(false, 0),
            'reason' => ['required', 'string', 'max:200'],
        ]);

        $amount = $this->cents($data['amount'] ?? null);
        $line = $this->book->waive($loan, $data['part'], $amount > 0 ? $amount : null, $data['reason'], $this->user($request));
        $this->toast('Waived '.Money::format(-($line->principal + $line->interest)).'.');

        return back();
    }

    public function recover(Request $request, Loan $loan): RedirectResponse
    {
        $data = $request->validate([
            'member_id' => ['required', 'integer', Rule::exists('members', 'id')->where('group_id', $this->group()->id)],
            'amount' => Money::rule(),
            'reason' => ['required', 'string', 'max:200'],
        ]);

        $from = Member::findOrFail((int) $data['member_id']);
        $this->book->recover($loan, $from, $this->cents($data['amount']), $data['reason'], $this->user($request));
        $this->toast('Recovered '.Money::format($this->cents($data['amount']))." from {$from->name}'s savings.");

        return back();
    }

    /**
     * @param  array{principal: int, interest: int, total: int}  $owed
     * @return array<string, mixed>
     */
    private function row(Loan $loan, array $owed): array
    {
        return [
            'id' => $loan->id,
            'reference' => $loan->reference(),
            'member' => ['id' => $loan->member->id, 'name' => $loan->member->name, 'number' => $loan->member->member_number],
            'source' => $loan->source,
            'status' => $loan->status,
            'principal' => $loan->principal,
            'owed' => $owed,
            'issued_on' => $loan->issued_on?->toDateString(),
            'due_on' => $loan->due_on?->toDateString(),
            'overdue' => $loan->isOverdue(),
            'behind' => $this->book->behind($loan, $owed['principal']),
            'interest' => [
                'rate' => $loan->rate(),
                'method' => $loan->interest_method,
                'period' => $loan->interest_period,
                'paused' => $loan->interest_paused,
            ],
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new RuleViolation('Sign in to do this.');
        }

        return $user;
    }
}
