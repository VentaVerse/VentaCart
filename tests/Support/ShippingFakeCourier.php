<?php

namespace Tests\Support;

use App\Models\Order;

/**
 * A courier that books successfully, so the integrated-booking write path
 * can be exercised without a live API. Extends FakeCourierFulfillment, whose
 * interface lives in the extension: reference this class only AFTER the
 * extension has booted (its PSR-4 autoloader registers on bootEnabled()), or
 * the parent fails to load. Register per test, unregister in tearDown.
 */
class ShippingFakeCourier extends FakeCourierFulfillment
{
    public function getKey(): string
    {
        return 'fakeship';
    }

    public function getName(): string
    {
        return 'Fake Ship';
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function createShipment(Order $order, array $opts): array
    {
        return ['success' => true, 'tracking_number' => 'FS-0001', 'status' => 'for_pickup', 'meta' => []];
    }

    public function cancelShipment(string $trackingNumber): array
    {
        return ['success' => true];
    }
}
