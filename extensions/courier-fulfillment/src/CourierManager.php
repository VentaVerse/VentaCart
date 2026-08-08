<?php

namespace Extensions\CourierFulfillment;

use App\Models\Setting;
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

    /**
     * Owner directive (2026-08-08, "unconfigured couriers visible with
     * configuration gate"): couriers whose ENABLED FLAG is on, regardless of
     * credential completeness — a strict SUPERSET of enabled() above (which
     * additionally requires isEnabled()'s own credential check). Backs the
     * booking modal's step 1: the owner turned SPX's toggle on but hadn't
     * filled in its App ID/Secret yet, and enabled() alone made it vanish
     * from the list entirely with no explanation — this is what lets step 1
     * still render a (disabled, clearly-labeled) card for it instead.
     *
     * Reads Setting(group=courier, key="{courier}_enabled") directly rather
     * than adding a new interface method every courier would have to
     * implement — the SAME generic "{key}_..." naming convention every
     * courier's settingsSchema() already declares its own toggle field
     * under, and that FulfillmentController::index() already relies on
     * generically for "{key}_mode". isEnabled() itself stays the ONLY gate
     * for any real operation (booking, tracking, etc.) — this method never
     * substitutes for it.
     *
     * @return CourierFulfillmentInterface[]
     */
    public static function flagged(): array
    {
        return array_values(array_filter(self::all(), fn ($c) => self::isFlagged($c->getKey())));
    }

    /**
     * Single-courier version of flagged() above's Setting check — backs the
     * booking gate's friendlier error message (FulfillmentService::book(),
     * FulfillmentController::estimate()): a courier that's registered and
     * flag-on but fails isEnabled() (missing credentials) gets a message
     * naming the courier and pointing at Settings; a courier that was never
     * flagged on at all keeps the generic "not available" message.
     */
    public static function isFlagged(string $key): bool
    {
        return (bool) Setting::where('group', 'courier')->where('key', $key . '_enabled')->value('value');
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
