<?php

namespace App\Http\Controllers\Webhooks;

use App\Domain\Groups\CurrentGroup;
use App\Domain\Groups\Models\Group;
use App\Domain\Payments\Actions\ApplyGatewayStatus;
use App\Domain\Payments\Gateway\GatewayException;
use App\Domain\Payments\Gateway\GatewayManager;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\WebhookEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives payment.pending / payment.succeeded / payment.failed from
 * SimulinkPay. The signature proves the sender; the payment status is then
 * read back from the API rather than trusted from the body.
 */
class SimulinkPayWebhookController extends Controller
{
    public function __invoke(Request $request, GatewayManager $gateways, ApplyGatewayStatus $apply, CurrentGroup $current): JsonResponse
    {
        $raw = $request->getContent();
        $payload = json_decode($raw, true);
        $externalId = isset($payload['id']) ? (string) $payload['id'] : null;
        $type = (string) ($payload['type'] ?? $request->header('X-SimulinkPay-Event', 'unknown'));

        $payment = $externalId === null ? null : Payment::withoutGlobalScopes()
            ->where('channel', 'simulinkpay')
            ->where('external_id', $externalId)
            ->first();

        $gateway = $gateways->for($payment?->group_id);
        $valid = $gateway->verifyWebhookSignature($raw, $request->header('X-SimulinkPay-Signature'));

        $event = WebhookEvent::create([
            'provider' => 'simulinkpay',
            'event_type' => mb_substr($type, 0, 50),
            'external_id' => $externalId,
            'signature_valid' => $valid,
            'payload' => is_array($payload) ? $payload : ['raw' => mb_substr($raw, 0, 2000)],
        ]);

        if (! $valid) {
            $event->update(['error' => 'Invalid signature']);

            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        if ($payment === null) {
            $event->update(['error' => 'Unknown payment', 'processed_at' => now()]);

            return response()->json(['message' => 'Ignored: unknown payment.']);
        }

        try {
            $result = $gateway->fetch($externalId);
        } catch (GatewayException $e) {
            $event->update(['error' => mb_substr($e->getMessage(), 0, 255)]);

            // Ask SimulinkPay to retry; the reconcile job is the backstop.
            return response()->json(['message' => 'Could not confirm with the API.'], 503);
        }

        $current->runAs(Group::findOrFail($payment->group_id), fn () => $apply->handle($payment, $result));
        $event->update(['processed_at' => now()]);

        return response()->json(['message' => 'ok']);
    }
}
