<?php

namespace Tests\Support;

use App\Models\Order;

/**
 * Minimal ASYNC courier: enabled, and createShipment() returns the
 * pending-creation shape (success, NO tracking number, status 'creating' —
 * see CourierFulfillmentInterface::resolvePendingCreation()'s contract).
 * The exact result SPX produces when its bounded create-poll window
 * elapses, without a test having to burn that window's real wall-clock
 * sleeps through the registry-constructed SpxFulfillment.
 *
 * Lives in its own file (not inline in a test file) deliberately: the
 * parent class implements the extension's interface, whose autoloader is
 * only registered at runtime by ExtensionManager — a class declared at the
 * top level of a test file would be loaded by PHPUnit at suite-load time,
 * before any test's setUp() has installed the extension. From here it
 * lazy-loads on first reference inside a test method instead.
 */
class StubAsyncCourierFulfillment extends FakeCourierFulfillment
{
    public function getKey(): string
    {
        return 'stubasync';
    }

    public function getName(): string
    {
        return 'Stub Async';
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function createShipment(Order $order, array $opts): array
    {
        return [
            'success'         => true,
            'tracking_number' => null,
            'status'          => 'creating',
            'meta'            => ['batch_no' => 4242, 'pending' => true],
        ];
    }

    public function statusPhase(?string $status): array
    {
        return ['phase' => 'booking', 'step' => 1, 'failed' => false];
    }
}
