<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Assets\Models\Hire;
use App\Domain\Dues\DueAllocator;
use App\Domain\Fundraisers\Models\Fundraiser;
use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Ledger\Ledger;
use App\Domain\Ledger\Models\Account;
use App\Domain\Members\Models\Member;
use App\Domain\Notifications\Notifier;
use App\Domain\Payments\Models\Payment;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectEntry;
use App\Domain\Projects\Models\ProjectParticipant;
use App\Domain\Projects\Models\Sale;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Posts a succeeded payment to the ledger and to whatever it pays for.
 * Runs once per payment; a contribution with no member stays unallocated
 * until the treasurer matches it.
 */
final class AllocatePayment
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly ChartOfAccounts $accounts,
        private readonly DueAllocator $dues,
        private readonly Notifier $notifier,
    ) {}

    public function handle(Payment $payment, bool $notify = true): bool
    {
        $allocated = DB::transaction(function () use ($payment) {
            $payment = Payment::withoutGlobalScopes()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->allocated_at !== null || $payment->status !== 'succeeded') {
                return null;
            }

            $member = $payment->member_id ? Member::withoutGlobalScopes()->find($payment->member_id) : null;
            $credits = $this->credits($payment, $member);

            if ($credits === null) {
                return null;
            }

            $assetChannel = $payment->channel === 'manual' ? ($payment->meta['asset'] ?? 'bank') : $payment->channel;
            $who = $member?->name ?? $payment->payer_name ?? $payment->phone ?? 'unknown payer';

            $entry = $this->ledger->post(
                "Payment from {$who} for {$payment->purposeLabel()}",
                [
                    [
                        'account' => $this->accounts->forChannel($payment->group_id, $assetChannel),
                        'debit' => $payment->amount,
                        'member_id' => $member?->id,
                        'memo' => $payment->receipt_number ?? $payment->external_id,
                    ],
                    ...$credits,
                ],
                $payment,
                CarbonImmutable::parse($payment->received_at ?? now()),
                $payment->receipt_number ?? $payment->reference,
            );

            $payment->forceFill(['allocated_at' => now(), 'journal_entry_id' => $entry->id])->save();

            return $payment;
        });

        if ($allocated === null) {
            return false;
        }

        if ($notify) {
            $this->sendReceipt($allocated);
        }

        return true;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function credits(Payment $payment, ?Member $member): ?array
    {
        $amount = $payment->amount;
        $line = fn (Account $account, string $memo) => [[
            'account' => $account,
            'credit' => $amount,
            'member_id' => $member?->id,
            'memo' => $memo,
        ]];

        switch ($payment->purpose) {
            case 'contribution':
                return $member ? $this->dues->settle($member, $amount) : null;

            case 'fundraiser':
                $fundraiser = Fundraiser::withoutGlobalScopes()->find($payment->payable_id);

                return $fundraiser ? $line($fundraiser->fundAccount(), $fundraiser->title) : null;

            case 'project':
                $project = Project::withoutGlobalScopes()->find($payment->payable_id);

                if (! $project) {
                    return null;
                }

                // Income (a harvest sold to a factory, a grant) is not a
                // member's stake, so it does not count towards profit shares.
                $isIncome = ($payment->meta['project_kind'] ?? null) === 'income';

                if ($member && ! $isIncome) {
                    $participant = ProjectParticipant::firstOrCreate(
                        ['project_id' => $project->id, 'member_id' => $member->id],
                        ['joined_on' => now()->toDateString()],
                    );
                    $participant->increment('contributed', $amount);
                }

                ProjectEntry::create([
                    'project_id' => $project->id,
                    'kind' => $isIncome ? 'income' : 'contribution',
                    'amount' => $amount,
                    'entry_on' => now()->toDateString(),
                    'description' => $isIncome
                        ? ($payment->meta['description'] ?? 'Income from '.($payment->payer_name ?? 'buyer'))
                        : 'Contribution from '.($member?->name ?? $payment->payer_name ?? 'non-member'),
                    'member_id' => $member?->id,
                    'payment_id' => $payment->id,
                ]);

                return $line($project->fundAccount(), $isIncome ? "{$project->name} income" : "{$project->name} contribution");

            case 'sale':
                $sale = Sale::find($payment->payable_id);

                if (! $sale) {
                    return null;
                }

                $sale->recordPayment($amount, $payment);

                return $line($sale->project->fundAccount(), "Sale: {$sale->item->name}");

            case 'hire':
                $hire = Hire::withoutGlobalScopes()->find($payment->payable_id);

                if (! $hire) {
                    return null;
                }

                $hire->recordPayment($amount);

                return $line($this->accounts->get($payment->group_id, ChartOfAccounts::ASSETS), "Hire: {$hire->asset->name}");

            default:
                return $line($this->accounts->get($payment->group_id, ChartOfAccounts::GROUP_FUND), 'Donation');
        }
    }

    private function sendReceipt(Payment $payment): void
    {
        $receipt = $payment->receipt_number ?? $payment->external_id ?? "#{$payment->id}";

        if ($payment->member_id) {
            $member = Member::withoutGlobalScopes()->find($payment->member_id);

            if ($member) {
                $this->notifier->toMember($member, 'payment_received', [
                    'amount' => Money::format($payment->amount),
                    'purpose' => $payment->purposeLabel(),
                    'receipt' => $receipt,
                ]);
            }

            return;
        }

        if ($payment->phone && $payment->purpose === 'fundraiser') {
            $fundraiser = Fundraiser::withoutGlobalScopes()->find($payment->payable_id);
            $group = $fundraiser?->group;

            if ($group) {
                $this->notifier->toNumber(
                    $group,
                    $payment->phone,
                    'Thank you'.($payment->payer_name ? ' '.explode(' ', $payment->payer_name)[0] : '').' for your contribution of '.Money::format($payment->amount)." to {$fundraiser->title}. Ref {$receipt}. {$group->name}",
                );
            }
        }
    }
}
