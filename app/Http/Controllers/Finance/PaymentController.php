<?php

namespace App\Http\Controllers\Finance;

use App\Domain\Access\Permission;
use App\Domain\Assets\Models\Hire;
use App\Domain\Audit\Audit;
use App\Domain\Fundraisers\Models\Fundraiser;
use App\Domain\Members\Models\Member;
use App\Domain\Payments\Actions\AllocatePayment;
use App\Domain\Payments\Actions\InitiateStkPayment;
use App\Domain\Payments\Actions\ReconcilePendingPayments;
use App\Domain\Payments\Actions\RecordPayment;
use App\Domain\Payments\Models\Payment;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\Sale;
use App\Http\Controllers\Controller;
use App\Support\Money;
use App\Support\Phone;
use App\Support\RuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $payments = Payment::query()
            ->with('member:id,name,member_number')
            ->when($request->string('channel')->toString(), fn ($q, $c) => $q->where('channel', $c))
            ->when($request->string('status')->toString(), function ($q, $s) {
                return $s === 'unmatched' ? $q->unallocated() : $q->where('status', $s);
            })
            ->when($request->string('purpose')->toString(), fn ($q, $p) => $q->where('purpose', $p))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(fn ($w) => $w
                ->whereLike('receipt_number', "%{$term}%")
                ->orWhereLike('payer_name', "%{$term}%")
                ->orWhereLike('phone', '%'.(Phone::normalize($term) ?? $term).'%')))
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Payment $p) => $this->present($p));

        return Inertia::render('payments/index', [
            'payments' => $payments,
            'filters' => $request->only('channel', 'status', 'purpose', 'q'),
            'members' => $this->memberOptions(false),
            'targets' => $this->targets(),
            'counts' => [
                'unmatched' => Payment::query()->unallocated()->count(),
                'pending' => Payment::query()->whereIn('status', ['pending', 'processing'])->count(),
            ],
        ]);
    }

    /**
     * Cash at a meeting, a bank deposit, or a paybill line typed in by hand.
     */
    public function store(Request $request, RecordPayment $record): RedirectResponse
    {
        $data = $request->validate([
            'channel' => ['required', Rule::in(['cash', 'paybill', 'manual'])],
            'asset' => ['nullable', Rule::in(['bank', 'mpesa', 'cash'])],
            'amount' => Money::rule(),
            'purpose' => ['required', Rule::in(Payment::PURPOSES)],
            'target_id' => ['nullable', 'integer'],
            'member_id' => ['nullable', 'integer', Rule::exists('members', 'id')->where('group_id', $this->group()->id)],
            'payer_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'reference' => [Rule::requiredIf($request->input('channel') === 'paybill'), 'nullable', 'string', 'max:100'],
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'meeting_id' => ['nullable', 'integer'],
        ]);

        if ($data['purpose'] === 'contribution' && empty($data['member_id'])) {
            throw new RuleViolation('Choose the member this contribution is from.');
        }

        $member = isset($data['member_id']) ? Member::find($data['member_id']) : null;

        $payment = $record->handle(
            $this->group(),
            $data['channel'],
            $this->cents($data['amount']),
            $data['purpose'],
            $member,
            $this->target($data['purpose'], $data['target_id'] ?? null),
            $data['reference'] ?? null,
            CarbonImmutable::parse($data['received_on']),
            $data['payer_name'] ?? null,
            $data['phone'] ?? null,
            $data['meeting_id'] ?? null,
            $data['asset'] ?? null,
        );

        $this->toast('Payment of '.Money::format($payment->amount).' recorded'.($payment->allocated_at ? ' and allocated.' : '.'));

        return back();
    }

    /**
     * Send an M-Pesa prompt to a member's phone (or to the signed-in
     * member's own phone from the dashboard).
     */
    public function stk(Request $request, InitiateStkPayment $initiate): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:150000'],
            'phone' => ['required', 'string', 'max:20'],
            'purpose' => ['required', Rule::in(Payment::PURPOSES)],
            'target_id' => ['nullable', 'integer'],
            'member_id' => ['nullable', 'integer', Rule::exists('members', 'id')->where('group_id', $this->group()->id)],
        ]);

        $forOther = isset($data['member_id']) && (int) $data['member_id'] !== $user->member->id;

        if ($forOther) {
            Gate::authorize(Permission::PaymentsRecord->value);
        }

        $member = $forOther ? Member::findOrFail($data['member_id']) : $user->member;

        $payment = $initiate->handle(
            $this->group(),
            $data['amount'] * 100,
            $data['phone'],
            $data['purpose'],
            $member,
            $this->target($data['purpose'], $data['target_id'] ?? null),
        );

        if ($payment->status === 'failed') {
            throw new RuleViolation('M-Pesa prompt not sent: '.$payment->failure_reason);
        }

        $this->toast('M-Pesa prompt sent to '.Phone::display($payment->phone).'. Enter the PIN to complete.', 'info');

        return back();
    }

    public function check(Payment $payment, ReconcilePendingPayments $reconcile): RedirectResponse
    {
        $this->authorizeView($payment);
        $payment = $reconcile->one($payment);
        $this->toast("Payment #{$payment->id} is {$payment->status}.", $payment->status === 'succeeded' ? 'success' : 'info');

        return back();
    }

    public function reconcile(ReconcilePendingPayments $reconcile): RedirectResponse
    {
        $stats = $reconcile->handle($this->group()->id, 0);
        $this->toast("Checked {$stats['checked']} pending M-Pesa payments: {$stats['settled']} settled, {$stats['expired']} expired.");

        return back();
    }

    /**
     * Match an unallocated payment (usually a paybill line) to a member.
     */
    public function assign(Request $request, Payment $payment, AllocatePayment $allocate): RedirectResponse
    {
        $data = $request->validate([
            'member_id' => ['nullable', 'integer', Rule::exists('members', 'id')->where('group_id', $this->group()->id)],
            'purpose' => ['required', Rule::in(Payment::PURPOSES)],
            'target_id' => ['nullable', 'integer'],
        ]);

        if ($payment->allocated_at !== null || $payment->status !== 'succeeded') {
            throw new RuleViolation('Only received payments that are not yet allocated can be matched.');
        }

        if ($data['purpose'] === 'contribution' && empty($data['member_id'])) {
            throw new RuleViolation('Choose the member this contribution is from.');
        }

        $target = $this->target($data['purpose'], $data['target_id'] ?? null);

        $payment->forceFill([
            'member_id' => $data['member_id'] ?? null,
            'purpose' => $data['purpose'],
            'payable_type' => $target?->getMorphClass(),
            'payable_id' => $target?->getKey(),
        ])->save();

        $allocate->handle($payment);
        Audit::log('payment.matched', "Payment #{$payment->id} matched to ".($payment->member?->name ?? $data['purpose']), $payment);
        $this->toast('Payment matched and allocated.');

        return back();
    }

    private function authorizeView(Payment $payment): void
    {
        $user = request()->user();

        abort_unless(
            $payment->member_id === $user->member?->id || $user->hasPermission(Permission::FinanceView->value),
            403,
        );
    }

    private function target(string $purpose, ?int $id): ?Model
    {
        $model = match ($purpose) {
            'fundraiser' => Fundraiser::class,
            'project' => Project::class,
            'hire' => Hire::class,
            'sale' => Sale::class,
            default => null,
        };

        if ($model === null) {
            return null;
        }

        if ($id === null) {
            throw new RuleViolation('Choose which '.str_replace('_', ' ', $purpose).' this payment is for.');
        }

        $target = $model === Sale::class
            ? Sale::query()->whereHas('project')->find($id)
            : $model::query()->find($id);

        return $target ?? throw new RuleViolation('That '.$purpose.' was not found.');
    }

    /**
     * Options for the "what is it for" pickers.
     *
     * @return array<string, list<array{id: int, label: string}>>
     */
    private function targets(): array
    {
        return [
            'fundraiser' => Fundraiser::query()->whereIn('status', ['active', 'draft'])->get()->map(fn ($f) => ['id' => $f->id, 'label' => $f->title])->all(),
            'project' => Project::query()->where('status', '!=', 'closed')->get()->map(fn ($p) => ['id' => $p->id, 'label' => $p->name])->all(),
            'hire' => Hire::query()->with('asset')->whereIn('status', ['booked', 'out', 'returned'])->whereColumn('paid', '<', 'total')->get()
                ->map(fn ($h) => ['id' => $h->id, 'label' => "{$h->hirer_name}: {$h->quantity} {$h->asset->name} (".Money::format($h->balance()).' due)'])->all(),
            'sale' => Sale::query()->with('item')->whereHas('project')->where('status', '!=', 'paid')->get()
                ->map(fn ($s) => ['id' => $s->id, 'label' => "{$s->buyer_name}: {$s->quantity} {$s->item->name} (".Money::format($s->balance()).' due)'])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Payment $p): array
    {
        return [
            'id' => $p->id,
            'member' => $p->member ? ['id' => $p->member->id, 'name' => $p->member->name, 'number' => $p->member->member_number] : null,
            'payer_name' => $p->payer_name,
            'phone' => $p->phone ? Phone::display($p->phone) : null,
            'channel' => $p->channel,
            'status' => $p->status,
            'purpose' => $p->purpose,
            'amount' => $p->amount,
            'receipt' => $p->receipt_number,
            'reference' => $p->reference,
            'failure_reason' => $p->failure_reason,
            'allocated' => $p->allocated_at !== null,
            'narrative' => $p->meta['narrative'] ?? null,
            'date' => ($p->received_at ?? $p->created_at)->toIso8601String(),
        ];
    }
}
