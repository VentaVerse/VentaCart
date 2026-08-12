<?php

namespace Tests\Support;

use App\Models\Order;
use Extensions\CourierFulfillment\ServiceabilityService;
use Extensions\CourierFulfillment\Support\ServiceabilityVerdict;

/**
 * A courier that answers only AFTER the per-courier budget has elapsed.
 *
 * It proves the timeout is enforced by ServiceabilityService itself, not
 * merely handed to an HTTP client an adapter is free to ignore: the verdict
 * this double eventually produces is `serviceable`, and the service must
 * still report `unknown`, because by then the packing desk has waited longer
 * than it can afford.
 *
 * Its own file rather than a second class inside the test file: every class
 * in this hierarchy is linked against the EXTENSION's autoloader, which
 * ExtensionManager registers at runtime, so it must not be loaded while
 * PHPUnit is still parsing test files.
 */
class SlowCourierFulfillment extends FakeCourierFulfillment
{
    public function getKey(): string
    {
        return 'slowpoke';
    }

    public function getName(): string
    {
        return 'Slowpoke Express';
    }

    public function checkServiceability(Order $order): ServiceabilityVerdict
    {
        usleep((int) ((ServiceabilityService::TIMEOUT_SECONDS + 0.2) * 1_000_000));

        return ServiceabilityVerdict::serviceable(['box' => 100]);
    }
}
