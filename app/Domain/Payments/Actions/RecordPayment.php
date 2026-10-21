<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Audit\Audit;
use App\Domain\Groups\Models\Group;
use App\Domain\Members\Models\Member;
use App\Domain\Payments\Models\Payment;
use App\Support\Money;
use App\Support\Phone;
use App\Support\RuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Money received outside the gateway: cash at a meeting, a bank deposit or
 * a paybill payment entered by hand.
 */
final class RecordPayment
{
    public function __construct(private readonly AllocatePayment $allocate) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public function handle(
        Group $group,
        string $channel,
        int $amount,
        string $purpose,
        ?Member $member = null,
        ?Model $payable = null,
        ?string $reference = null,
        ?CarbonImmutable $receivedOn = null,
        ?string $payerName = null,
        ?string $phone = null,
        ?int $meetingId = null,
        ?string $assetAccount = null,
        array $meta = [],
    ): Payment {
        if ($amount <= 0) {
            throw new RuleViolation('Amount must be more than zero.');
        }

        if ($reference && Payment::withoutGlobalScopes()->where('group_id', $group->id)->where('channel', $channel)->where('external_id', $reference)->exists()) {
            throw new RuleViolation("A {$channel} payment with reference {$reference} is already recorded.");
        }

        $payment = Payment::withoutGlobalScopes()->create([
            'group_id' => $group->id,
            'member_id' => $member?->id,
            'channel' => $channel,
            'status' => 'succeeded',
            'purpose' => $purpose,
            'payable_type' => $payable?->getMorphClass(),
            'payable_id' => $payable?->getKey(),
            'amount' => $amount,
            'phone' => Phone::normalize($phone) ?? ($member ? Phone::normalize($member->phone) : null),
            'payer_name' => $payerName ?? $member?->name,
            'external_id' => $reference,
            'receipt_number' => $reference,
            'received_at' => ($receivedOn ?? CarbonImmutable::now())->toDateTimeString(),
            'meeting_id' => $meetingId,
            'recorded_by' => auth()->id(),
            'meta' => array_filter([
                'asset' => $channel === 'manual' ? ($assetAccount ?? 'bank') : null,
                ...$meta,
            ]) ?: null,
        ]);

        $this->allocate->handle($payment);

        Audit::log('payment.recorded', "Recorded {$channel} payment of ".Money::format($amount).' from '.($payment->payer_name ?? 'unknown'), $payment, [], $group->id);

        return $payment->refresh();
    }
}
