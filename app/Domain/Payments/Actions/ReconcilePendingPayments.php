<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\Gateway\GatewayException;
use App\Domain\Payments\Gateway\GatewayManager;
use App\Domain\Payments\Models\Payment;

/**
 * Asks the gateway about STK pushes we have not heard back on. Covers lost
 * webhooks and SimulinkPay's "expired" status, which sends no webhook.
 */
final class ReconcilePendingPayments
{
    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly ApplyGatewayStatus $applyStatus,
    ) {}

    /**
     * @return array{checked: int, settled: int, expired: int}
     */
    public function handle(?int $groupId = null, int $olderThanSeconds = -1): array
    {
        $olderThanSeconds = $olderThanSeconds >= 0 ? $olderThanSeconds : (int) config('kertai.reconcile_after_seconds');
        $stats = ['checked' => 0, 'settled' => 0, 'expired' => 0];

        $pending = Payment::withoutGlobalScopes()
            ->where('channel', 'simulinkpay')
            ->whereIn('status', ['pending', 'processing'])
            ->whereNotNull('external_id')
            ->where('created_at', '<=', now()->subSeconds($olderThanSeconds))
            ->when($groupId, fn ($q) => $q->where('group_id', $groupId))
            ->orderBy('id')
            ->limit(200)
            ->get();

        foreach ($pending as $payment) {
            $stats['checked']++;
            $stats = $this->check($payment, $stats);
        }

        return $stats;
    }

    public function one(Payment $payment): Payment
    {
        if ($payment->channel === 'simulinkpay' && ! $payment->isFinal() && $payment->external_id) {
            $this->check($payment, ['checked' => 0, 'settled' => 0, 'expired' => 0]);
        }

        return $payment->refresh();
    }

    /**
     * @param  array{checked: int, settled: int, expired: int}  $stats
     * @return array{checked: int, settled: int, expired: int}
     */
    private function check(Payment $payment, array $stats): array
    {
        try {
            $result = $this->gateways->for($payment->group_id)->fetch((string) $payment->external_id);
            $updated = $this->applyStatus->handle($payment, $result);

            if ($updated->isFinal()) {
                $stats['settled']++;
            }
        } catch (GatewayException) {
            // Leave it for the next run unless it has been far too long.
        }

        $payment->refresh();

        if (! $payment->isFinal() && $payment->created_at->lt(now()->subMinutes((int) config('kertai.expire_after_minutes')))) {
            $payment->forceFill(['status' => 'expired', 'failure_reason' => 'No answer from M-Pesa'])->save();
            $stats['expired']++;
        }

        return $stats;
    }
}
