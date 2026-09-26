<?php

namespace Tests\Support;

use App\Services\OrderNotificationService;

/**
 * OrderNotificationService::$channels is a static registry with no
 * unregister call, so a channel a test registers would otherwise outlive
 * that test and leak into whichever other suite runs next in the same
 * process. Any test that calls OrderNotificationService::registerChannel()
 * uses this trait and wires it into its own tearDown(), so the registry is
 * wiped back to empty regardless of run order. This is the one place that
 * cleanup lives, so Tasks 5, 7 and 8 (which also register channels to test
 * this doorway) reuse it instead of copying the reflection.
 */
trait ResetsNotificationChannels
{
    protected function resetNotificationChannels(): void
    {
        $property = new \ReflectionProperty(OrderNotificationService::class, 'channels');
        $property->setAccessible(true);
        $property->setValue(null, []);
    }
}
