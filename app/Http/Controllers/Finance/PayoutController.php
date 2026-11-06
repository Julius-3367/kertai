<?php

namespace App\Http\Controllers\Finance;

use App\Domain\Access\Permission;
use App\Domain\Ledger\Ledger;
use App\Domain\Ledger\Models\Account;
use App\Domain\Loans\LoanBook;
use App\Domain\Loans\Models\Loan;
use App\Domain\Members\Models\Member;
use App\Domain\Payouts\Actions\AdjustPayout;
use App\Domain\Payouts\Actions\CancelPayout;
use App\Domain\Payouts\Actions\DecidePayout;
use App\Domain\Payouts\Actions\MarkPayoutPaid;
use App\Domain\Payouts\Actions\RequestPayout;
use App\Domain\Payouts\Models\Payout;
use App\Domain\Payouts\Models\PayoutAdjustment;
use App\Domain\Payouts\Models\PayoutApproval;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Money;
use App\Support\Phone;
use App\Support\RuleViolation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PayoutController extends Controller
{
    public function index(Request $request, Ledger $ledger): Response
    {
        $user = $request->user();
        $status = $request->string('status')->toString();

        $payouts = Payout::query()
            ->with(['account:id,name', 'approvals', 'adjustments'])
            ->when($status === 'mine', fn ($q) => $q->awaitingDecisionBy($user))
            ->when($status !== '' && $status !== 'mine', fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Payout $p) => [
                ...$this->present($p),
                'can_decide' => $this->canDecide($p, $user),
            ]);

        return Inertia::render('payouts/index', [
            'payouts' => $payouts,
            'filters' => ['status' => $status],
            'funds' => Account::query()->where('kind', 'fund')->where('is_active', true)->orderBy('code')->get()
                ->map(fn (Account $a) => [
                    'id' => $a->id,
                    'label' => $a->name,
                    'purpose' => $a->purpose,
                    'per_member' => $a->isPerMember(),
                    'balance' => $a->isPerMember() ? null : $ledger->balance($a),
                ]),
            'members' => $this->memberOptions(false),
            'types' => Payout::TYPES,
        ]);
    }

    public function store(Request $request, RequestPayout $requestPayout): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Payout::TYPES))],
            'account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('group_id', $this->group()->id)->where('kind', 'fund')],
            'amount' => Money::rule(),
            'description' => ['required', 'string', 'max:200'],
            'payee_member_id' => ['nullable', 'integer', Rule::exists('members', 'id')->where('group_id', $this->group()->id)],
            'payee_name' => ['nullable', 'required_without:payee_member_id', 'string', 'max:120'],
            'payee_phone' => ['nullable', 'string', 'max:20'],
        ]);

        $payout = $requestPayout->handle(
            $this->group(),
            $data['type'],
            $this->cents($data['amount']),
            Account::findOrFail((int) $data['account_id']),
            $data['description'],
            isset($data['payee_member_id']) ? Member::find((int) $data['payee_member_id']) : null,
            $data['payee_name'] ?? null,
            $data['payee_phone'] ?? null,
        );

        $this->toast("Payout #{$payout->number} requested. Two officials must approve it.");

        return to_route('payouts.show', $payout);
    }

    public function show(Request $request, Payout $payout, LoanBook $loans): Response
    {
        $user = $request->user();
        $payout->load(['approvals.user', 'account', 'payee', 'requester', 'payer', 'adjustments.loan', 'adjustments.adder']);
        $canAdjust = in_array($payout->status, ['pending', 'approved'], true)
            && $payout->payee_member_id !== null
            && $payout->type !== 'loan'
            && $user->hasPermission(Permission::PayoutsPay->value);
        $payeeLoans = $canAdjust
            ? Loan::query()->where('member_id', $payout->payee_member_id)->active()->get()
            : collect();

        return Inertia::render('payouts/show', [
            'payout' => [
                ...$this->present($payout),
                'requested_by' => $payout->requester?->name,
                'paid_by' => $payout->payer?->name,
                'paid_method' => $payout->paid_method,
                'paid_reference' => $payout->paid_reference,
                'approvals' => $payout->approvals->map(fn (PayoutApproval $a) => [
                    'id' => $a->id,
                    'name' => $a->user->name,
                    'position' => $a->position_name,
                    'decision' => $a->decision,
                    'comment' => $a->comment,
                    'at' => $a->created_at->toIso8601String(),
                ]),
                'journal_entry_id' => $payout->journal_entry_id,
                'adjustments' => $payout->adjustments->map(fn (PayoutAdjustment $a) => [
                    'id' => $a->id,
                    'kind' => $a->kind,
                    'label' => $a->kind === 'loan' && $a->loan ? "Loan {$a->loan->reference()} repayment" : PayoutAdjustment::KINDS[$a->kind],
                    'amount' => $a->amount,
                    'effect' => $a->effect(),
                    'reason' => $a->reason,
                    'by' => $a->adder?->name,
                ]),
            ],
            'loans' => $payeeLoans->map(fn (Loan $l) => [
                'id' => $l->id,
                'label' => $l->reference().', '.Money::format($loans->balance($l)['total']).' owed',
                'owed' => $loans->balance($l)['total'],
            ])->values(),
            'adjustmentKinds' => PayoutAdjustment::KINDS,
            'can' => [
                'decide' => $this->canDecide($payout, $user),
                'pay' => $payout->status === 'approved' && $user->hasPermission(Permission::PayoutsPay->value),
                'adjust' => $canAdjust,
                'cancel' => in_array($payout->status, ['pending', 'approved'], true)
                    && ($payout->requested_by === $user->id || $user->hasPermission(Permission::PayoutsPay->value)),
            ],
            'approvalsRequired' => max(2, (int) $this->group()->settings()->get('payout_approvals_required')),
            'methods' => Payout::METHODS,
        ]);
    }

    public function decide(Request $request, Payout $payout, DecidePayout $decide): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'comment' => ['nullable', 'required_if:decision,rejected', 'string', 'max:255'],
        ]);

        $payout = $decide->handle($payout, $request->user(), $data['decision'], $data['comment'] ?? null);

        $this->toast(match ($payout->status) {
            'approved' => "Payout #{$payout->number} is fully approved and ready to pay.",
            'rejected' => "Payout #{$payout->number} rejected.",
            default => 'Approval recorded. One more official must approve.',
        });

        return back();
    }

    /**
     * Approve several pending payouts at once (share-out batches).
     */
    public function bulkApprove(Request $request, DecidePayout $decide): RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'max:200'], 'ids.*' => ['integer']]);
        $done = 0;
        $errors = [];

        foreach (Payout::query()->whereIn('id', $data['ids'])->get() as $payout) {
            try {
                $decide->handle($payout, $request->user(), 'approved');
                $done++;
            } catch (RuleViolation $e) {
                $errors[] = "#{$payout->number}: {$e->getMessage()}";
            } catch (Throwable $e) {
                report($e);
                $errors[] = "#{$payout->number}: could not be approved";
            }
        }

        $this->toast("Approved {$done} payouts.".($errors ? ' Skipped: '.implode('; ', array_slice($errors, 0, 3)) : ''), $errors ? 'warning' : 'success');

        return back();
    }

    public function pay(Request $request, Payout $payout, MarkPayoutPaid $pay): RedirectResponse
    {
        $data = $request->validate([
            'method' => ['required', Rule::in(array_keys(Payout::METHODS))],
            'reference' => ['required', 'string', 'max:100'],
        ]);

        $pay->handle($payout, $request->user(), $data['method'], $data['reference']);
        $this->toast("Payout #{$payout->number} marked as paid. The ledger has been updated.");

        return back();
    }

    public function addAdjustment(Request $request, Payout $payout, AdjustPayout $adjust): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(PayoutAdjustment::KINDS))],
            'amount' => Money::rule(),
            'loan_id' => ['nullable', 'required_if:kind,loan', 'integer'],
            'reason' => ['required', 'string', 'max:200'],
        ], ['loan_id.required_if' => 'Choose the loan.']);

        $adjust->add($payout, $request->user(), $data['kind'], $this->cents($data['amount']), $data['reason'], isset($data['loan_id']) ? (int) $data['loan_id'] : null);
        $this->toast('Saved. '.Money::format($payout->refresh()->load('adjustments')->net()).' will be sent. The payout needs two approvals for the new amount.');

        return back();
    }

    public function removeAdjustment(Request $request, Payout $payout, PayoutAdjustment $adjustment, AdjustPayout $adjust): RedirectResponse
    {
        $adjust->remove($payout, $adjustment, $request->user());
        $this->toast('Removed. The payout needs two approvals for the new amount.');

        return back();
    }

    public function cancel(Request $request, Payout $payout, CancelPayout $cancel): RedirectResponse
    {
        $user = $request->user();
        abort_unless($payout->requested_by === $user->id || $user->hasPermission(Permission::PayoutsPay->value), 403);

        $data = $request->validate(['reason' => ['required', 'string', 'max:200']]);
        $cancel->handle($payout, $user, $data['reason']);
        $this->toast("Payout #{$payout->number} cancelled.");

        return back();
    }

    private function canDecide(Payout $payout, User $user): bool
    {
        return $payout->status === 'pending'
            && $user->hasPermission(Permission::PayoutsApprove->value)
            && $payout->requested_by !== $user->id
            && ! $payout->approvals->contains('user_id', $user->id)
            && ! ($payout->payee_member_id !== null && $payout->payee_member_id === $user->member?->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Payout $p): array
    {
        return [
            'id' => $p->id,
            'number' => $p->number,
            'type' => $p->type,
            'type_label' => $p->typeLabel(),
            'status' => $p->status,
            'amount' => $p->amount,
            'net' => $p->net(),
            'fund' => $p->account->name,
            'payee_name' => $p->payee_name,
            'payee_member_id' => $p->payee_member_id,
            'payee_phone' => $p->payee_phone ? Phone::display($p->payee_phone) : null,
            'description' => $p->description,
            'approvals_count' => $p->approvals->where('decision', 'approved')->count(),
            'created_at' => $p->created_at->toIso8601String(),
            'paid_at' => $p->paid_at?->toIso8601String(),
        ];
    }
}
