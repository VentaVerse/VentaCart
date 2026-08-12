<?php

namespace Tests\Support;

use App\Models\Order;

/**
 * Minimal SYNCHRONOUS courier: enabled, and createShipment() returns a real
 * tracking number straight away — the shape QuadX produces, without a test
 * having to stand up QuadX's credentials or let its client near a network.
 *
 * The counterpart to StubAsyncCourierFulfillment, which returns the
 * pending-creation shape instead. Same reason for living in its own file
 * rather than inline in a test: the parent implements the extension's
 * interface, whose autoloader is only registered at runtime by
 * ExtensionManager, so a class declared at the top of a test file would be
 * loaded before any setUp() has installed the extension.
 */
class StubSyncCourierFulfillment extends FakeCourierFulfillment
{
    public function getKey(): string
    {
        return 'stubsync';
    }

    public function getName(): string
    {
        return 'Stub Sync';
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function createShipment(Order $order, array $opts): array
    {
        return [
            'success'         => true,
            'tracking_number' => 'STUB-' . $order->id,
            'status'          => 'pending',
            'meta'            => [],
        ];
    }

    public function statusPhase(?string $status): array
    {
        return ['phase' => 'to_handover', 'step' => 1, 'failed' => false];
    }
}
