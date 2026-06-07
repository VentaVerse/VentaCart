<?php

namespace Extensions\CourierFulfillment;

use Extensions\CourierFulfillment\Contracts\CourierFulfillmentInterface;

/**
 * Registry + dispatcher for courier modules.
 *
 * Couriers register themselves (the extension's ServiceProvider calls
 * register() during boot). Nothing here knows about any specific courier —
 * adding a competitor courier is one register() call, no edits to this class
 * or the admin UI.
 */
class CourierManager
{
    /** @var array<string, class-string<CourierFulfillmentInterface>> */
    private static array $registered = [];

    /** @param class-string<CourierFulfillmentInterface> $courierClass */
    public static function register(string $courierClass): void
    {
        $key = (new $courierClass())->getKey();
        self::$registered[$key] = $courierClass;
    }

    /** All registered couriers, instantiated. @return CourierFulfillmentInterface[] */
    public static function all(): array
    {
        return array_map(fn ($cls) => new $cls(), array_values(self::$registered));
    }

    /** Only enabled (configured + turned on) couriers. @return CourierFulfillmentInterface[] */
    public static function enabled(): array
    {
        return array_values(array_filter(self::all(), fn ($c) => $c->isEnabled()));
    }

    /** Resolve one courier by key, or null. */
    public static function get(string $key): ?CourierFulfillmentInterface
    {
        $cls = self::$registered[$key] ?? null;
        return $cls ? new $cls() : null;
    }

    /** Any enabled courier at all? Drives whether the Fulfillment menu/page is useful. */
    public static function hasEnabled(): bool
    {
        return self::enabled() !== [];
    }
}
