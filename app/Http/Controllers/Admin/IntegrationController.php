<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Audit;
use App\Domain\Integrations\IntegrationSettings;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Notifications\Email\EmailSender;
use App\Domain\Notifications\Sms\SmsManager;
use App\Domain\Payments\Gateway\GatewayManager;
use App\Http\Controllers\Controller;
use App\Support\Phone;
use App\Support\RuleViolation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * SMS, email and payment provider keys. Secrets are encrypted at rest and
 * never sent back to the browser; only "set" or "not set" is shown.
 */
class IntegrationController extends Controller
{
    public function index(IntegrationSettings $settings): Response
    {
        $groupId = $this->group()->id;
        $current = [];

        foreach (array_keys(IntegrationSettings::DRIVERS) as $kind) {
            $saved = Integration::query()->where('kind', $kind)->first();
            $resolved = $settings->for($groupId, $kind);
            $definition = IntegrationSettings::DRIVERS[$kind][$resolved['driver']] ?? ['fields' => []];

            $values = [];
            foreach ($definition['fields'] as $field) {
                $value = $saved?->credentials[$field['key']] ?? null;
                $values[$field['key']] = ($field['secret'] ?? false) ? null : $value;
                $values[$field['key'].'__set'] = $value !== null && $value !== '';
            }

            $current[$kind] = [
                'driver' => $resolved['driver'],
                'source' => $resolved['source'],
                'is_active' => $saved?->is_active ?? false,
                'values' => $values,
            ];
        }

        return Inertia::render('admin/integrations', [
            'drivers' => IntegrationSettings::DRIVERS,
            'current' => $current,
            'webhooks' => [
                'simulinkpay' => url('/webhooks/simulinkpay'),
                'inbound_sms' => config('kertai.inbound_sms_token') ? url('/webhooks/sms/'.config('kertai.inbound_sms_token')) : null,
            ],
        ]);
    }

    public function update(Request $request, string $kind): RedirectResponse
    {
        abort_unless(array_key_exists($kind, IntegrationSettings::DRIVERS), 404);

        $data = $request->validate([
            'driver' => ['required', Rule::in(array_keys(IntegrationSettings::DRIVERS[$kind]))],
            'is_active' => ['boolean'],
            'values' => ['array'],
            'values.*' => ['nullable', 'string', 'max:2000'],
        ]);

        $integration = Integration::query()->firstOrNew(['kind' => $kind]);
        $existing = $integration->driver === $data['driver'] ? ($integration->credentials ?? []) : [];
        $credentials = [];

        foreach (IntegrationSettings::DRIVERS[$kind][$data['driver']]['fields'] as $field) {
            $value = $data['values'][$field['key']] ?? null;

            // Blank secret = keep what is stored.
            if (($field['secret'] ?? false) && ($value === null || $value === '')) {
                $value = $existing[$field['key']] ?? null;
            }

            $credentials[$field['key']] = $value;
        }

        $integration->fill([
            'driver' => $data['driver'],
            'credentials' => $credentials,
            'is_active' => $data['is_active'] ?? true,
        ])->save();

        Audit::log('integration.updated', "Saved {$kind} provider settings ({$data['driver']})", $integration, ['fields' => array_keys(array_filter($credentials))]);
        $this->toast(ucfirst($kind).' settings saved.');

        return back();
    }

    public function test(Request $request, string $kind, SmsManager $sms, EmailSender $email, GatewayManager $gateways): RedirectResponse
    {
        $data = $request->validate(['to' => ['required', 'string', 'max:120']]);
        $group = $this->group();

        try {
            if ($kind === 'sms') {
                $phone = Phone::normalize($data['to']) ?? throw new RuleViolation('Enter a valid phone number.');
                $driver = $sms->driver($group);
                $result = $driver->send($phone, "Test message from {$group->name}. SMS is working.");
                $this->toast("Sent through {$driver->name()}".($result->messageId ? " (id {$result->messageId})" : '').'.');
            } elseif ($kind === 'email') {
                $mailer = $email->send($group, $data['to'], "Test email from {$group->name}", 'Email delivery is working.');
                $this->toast("Test email handed to the {$mailer} mailer.");
            } elseif ($kind === 'payments') {
                $gateway = $gateways->for($group->id);
                $gateway->fetch($data['to']);
                $this->toast("{$gateway->name()} answered. The API token works.");
            }
        } catch (RuleViolation $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RuleViolation('Test failed: '.$e->getMessage());
        }

        return back();
    }
}
