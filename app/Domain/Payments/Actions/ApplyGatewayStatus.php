<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\Gateway\GatewayPayment;
use App\Domain\Payments\Models\Payment;
use Illuminate\Support\Facades\DB;

final class ApplyGatewayStatus
{
    public function __construct(private readonly AllocatePayment $allocate) {}

    public function handle(Payment $payment, GatewayPayment $result): Payment
    {
        $updated = DB::transaction(function () use ($payment, $result) {
            $payment = Payment::withoutGlobalScopes()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->isFinal()) {
                return null;
            }

            if ($result->status === 'succeeded') {
                $meta = $payment->meta ?? [];

                // The gateway amount is what actually arrived.
                if ($result->amount > 0 && $result->amount !== $payment->amount) {
                    $meta['requested_amount'] = $payment->amount;
                    $payment->amount = $result->amount;
                }

                $payment->forceFill([
                    'status' => 'succeeded',
                    'receipt_number' => $result->receiptNumber,
                    'received_at' => now(),
                    'meta' => $meta ?: null,
                ])->save();

                return $payment;
            }

            if (in_array($result->status, ['failed', 'expired', 'cancelled'], true)) {
                $payment->forceFill([
                    'status' => $result->status,
                    'failure_reason' => $result->failureReason,
                ])->save();
            } elseif ($payment->status !== $result->status) {
                $payment->forceFill(['status' => $result->status])->save();
            }

            return null;
        });

        if ($updated !== null) {
            $this->allocate->handle($updated);
        }

        return $payment->refresh();
    }
}
