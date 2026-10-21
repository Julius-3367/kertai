<?php

namespace App\Http\Controllers;

use App\Domain\Fundraisers\Models\Fundraiser;
use App\Domain\Groups\CurrentGroup;
use App\Domain\Groups\Models\Group;
use App\Domain\Members\Models\Member;
use App\Domain\Payments\Actions\InitiateStkPayment;
use App\Domain\Payments\Actions\ReconcilePendingPayments;
use App\Domain\Payments\Models\Payment;
use App\Support\Phone;
use App\Support\RuleViolation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The page anyone can open from a shared link: progress, the names of
 * people who agreed to be listed, and an M-Pesa prompt to give.
 */
class PublicFundraiserController extends Controller
{
    public function show(string $slug): Response
    {
        $fundraiser = $this->find($slug);
        $payments = Payment::withoutGlobalScopes()
            ->where('payable_type', $fundraiser->getMorphClass())
            ->where('payable_id', $fundraiser->id)
            ->where('status', 'succeeded')
            ->latest('received_at')
            ->get();

        return Inertia::render('public/fundraiser', [
            'fundraiser' => [
                'slug' => $fundraiser->slug,
                'title' => $fundraiser->title,
                'summary' => $fundraiser->summary,
                'description' => $fundraiser->description,
                'beneficiary' => $fundraiser->beneficiary,
                'target' => $fundraiser->target,
                'raised' => (int) $payments->sum('amount'),
                'count' => $payments->count(),
                'ends_on' => $fundraiser->ends_on?->toDateString(),
                'open' => $fundraiser->isOpen(),
                'group' => $fundraiser->group->name,
            ],
            'contributors' => $fundraiser->show_contributors
                ? $payments->filter(fn (Payment $p) => $p->show_name)->take(30)->map(fn (Payment $p) => [
                    'name' => $this->publicName($p->payer_name),
                    'amount' => $p->amount,
                    'date' => $p->received_at?->toDateString(),
                ])->values()
                : [],
        ]);
    }

    public function pay(Request $request, string $slug, InitiateStkPayment $initiate): RedirectResponse
    {
        $fundraiser = $this->find($slug);

        if (! $fundraiser->isOpen()) {
            throw new RuleViolation('This fundraiser is not taking contributions right now.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'string', 'max:20'],
            'amount' => ['required', 'integer', 'min:10', 'max:150000'],
            'show_name' => ['boolean'],
        ]);

        $phone = Phone::normalize($data['phone']) ?? throw new RuleViolation('Enter a valid Safaricom number, e.g. 0712 345 678.');
        $member = Member::withoutGlobalScopes()->where('group_id', $fundraiser->group_id)->where('phone', $phone)->first();
        $group = Group::findOrFail($fundraiser->group_id);

        $payment = app(CurrentGroup::class)->runAs($group, fn () => $initiate->handle(
            $group,
            $data['amount'] * 100,
            $phone,
            'fundraiser',
            $member,
            $fundraiser,
            $data['name'],
            (bool) ($data['show_name'] ?? false),
        ));

        if ($payment->status === 'failed') {
            throw new RuleViolation('We could not send the M-Pesa prompt: '.$payment->failure_reason);
        }

        Inertia::flash('payment_key', $payment->idempotency_key);

        return back();
    }

    /**
     * Polled by the page after a prompt is sent. Asks the gateway directly
     * when we have not heard back yet, so a missed webhook does not leave the
     * donor waiting.
     */
    public function status(string $slug, string $key, ReconcilePendingPayments $reconcile): JsonResponse
    {
        $fundraiser = $this->find($slug);
        $payment = Payment::withoutGlobalScopes()
            ->where('idempotency_key', $key)
            ->where('payable_id', $fundraiser->id)
            ->where('payable_type', $fundraiser->getMorphClass())
            ->firstOrFail();

        if (! $payment->isFinal() && $payment->updated_at->lt(now()->subSeconds(8))) {
            $payment = app(CurrentGroup::class)->runAs($fundraiser->group, fn () => $reconcile->one($payment));
        }

        return response()->json([
            'status' => $payment->status,
            'receipt' => $payment->receipt_number,
            'message' => match ($payment->status) {
                'succeeded' => 'Thank you! Your contribution has been received.',
                'failed', 'expired', 'cancelled' => $payment->failure_reason ?? 'The payment did not go through.',
                default => 'Waiting for you to enter your M-Pesa PIN...',
            },
        ]);
    }

    private function find(string $slug): Fundraiser
    {
        $fundraiser = Fundraiser::withoutGlobalScopes()->with('group')->where('slug', $slug)->firstOrFail();
        abort_if($fundraiser->status === 'draft', 404);

        return $fundraiser;
    }

    private function publicName(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];

        if ($parts === [] || $parts[0] === '') {
            return 'Well-wisher';
        }

        return count($parts) > 1 ? $parts[0].' '.mb_substr(end($parts), 0, 1).'.' : $parts[0];
    }
}
