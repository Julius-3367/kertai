<?php

namespace App\Domain\Payments\Gateway;

use App\Domain\Integrations\IntegrationSettings;

final class GatewayManager
{
    /** @var array<string, PaymentGateway> */
    private array $overrides = [];

    public function __construct(private readonly IntegrationSettings $settings) {}

    public function for(?int $groupId): PaymentGateway
    {
        $resolved = $this->settings->for($groupId, 'payments');

        if (isset($this->overrides[$resolved['driver']])) {
            return $this->overrides[$resolved['driver']];
        }

        return match ($resolved['driver']) {
            'simulinkpay' => new SimulinkPayGateway($resolved['credentials']),
            default => new FakeGateway,
        };
    }

    /**
     * Tests swap a driver for a double.
     */
    public function swap(string $driver, PaymentGateway $gateway): void
    {
        $this->overrides[$driver] = $gateway;
    }
}
