<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Groups\Models\Group;
use App\Domain\Members\Models\Member;
use App\Domain\Payments\Gateway\GatewayException;
use App\Domain\Payments\Gateway\GatewayManager;
use App\Domain\Payments\Models\Payment;
use App\Support\Phone;
use App\Support\RuleViolation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Starts an M-Pesa STK push through the payment gateway. The payment stays
 * "processing" until the webhook or the reconciliation job settles it.
 */
final class InitiateStkPayment
{
    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly ApplyGatewayStatus $applyStatus,
    ) {}

    public function handle(
        Group $group,
        int $amount,
        string $phone,
        string $purpose,
        ?Member $member = null,
        ?Model $payable = null,
        ?string $payerName = null,
        bool $showName = false,
    ): Payment {
        $normalized = Phone::normalize($phone) ?? throw new RuleViolation('Enter a valid Safaricom number, e.g. 0712 345 678.');

        if ($amount < 100 || $amount % 100 !== 0) {
            throw new RuleViolation('M-Pesa amounts must be whole shillings of at least KES 1.');
        }

        $payment = Payment::withoutGlobalScopes()->create([
            'group_id' => $group->id,
            'member_id' => $member?->id,
            'channel' => 'simulinkpay',
            'status' => 'pending',
            'purpose' => $purpose,
            'payable_type' => $payable?->getMorphClass(),
            'payable_id' => $payable?->getKey(),
            'amount' => $amount,
            'phone' => $normalized,
            'payer_name' => $payerName ?? $member?->name,
            'idempotency_key' => (string) Str::uuid(),
            'show_name' => $showName,
            'recorded_by' => auth()->id(),
        ]);

        $payment->forceFill(['reference' => 'KRT-'.$payment->id])->save();

        try {
            $result = $this->gateways->for($group->id)->requestStkPush($amount, $normalized, $payment->reference, $payment->idempotency_key);
            $payment->forceFill(['external_id' => $result->externalId, 'status' => 'processing'])->save();
            $this->applyStatus->handle($payment, $result);
        } catch (GatewayException $e) {
            $payment->forceFill(['status' => 'failed', 'failure_reason' => mb_substr($e->getMessage(), 0, 255)])->save();
        }

        return $payment->refresh();
    }
}
