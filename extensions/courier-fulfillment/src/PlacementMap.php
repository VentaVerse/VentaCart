<?php

namespace Extensions\CourierFulfillment;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The module-owned status-to-tab mapping (owner ruling, 2026-08-09), and the
 * single reader of `courier_fulfillment_status_placements`.
 *
 * Every fulfillment surface that used to read
 * `order_statuses.fulfillment_placement` reads this instead: tab membership
 * (FulfillmentService::tabBucket()), the booking gate, the booked/unbooked
 * status resolution, and the queue's tallies. SQL-side callers (the segment
 * bar's one grouped query, the listing's where clause) join or sub-select the
 * table directly so they can never disagree with this cache.
 *
 * UNPLACED IS A REAL STATE. A status with no row here is not "to_pack by
 * default" and not broken: the module seeds nothing, so a fresh install has
 * every status unplaced. Those orders appear in the queue's All tab (a VIEW
 * over every order) and in no other tab, and the Status placement page lists
 * them first so the gap is visible rather than silent.
 *
 * CACHING: one query per request builds the whole map, which is a handful of
 * rows (one per placed status), never proportional to orders. The memo lives
 * in the CONTAINER, not in a static: a static would survive between tests
 * (same PHP process, fresh application each time) and hand one test the
 * previous test's mapping. Every write through this class flushes it, so a
 * stale read is not reachable through normal use.
 */
class PlacementMap
{
    public const TABLE = 'courier_fulfillment_status_placements';

    /** Container key the per-application memo is bound under. */
    private const MEMO = 'courier-fulfillment.placement-map';

    public static function flush(): void
    {
        app()->forgetInstance(self::MEMO);
    }

    /**
     * The whole mapping, keyed by order status id.
     *
     * Answers an empty map when the table does not exist, which is the state
     * during core's own migrations and on an install where this module has
     * never been installed. Callers then see every status as unplaced, which
     * is the honest answer rather than an exception.
     *
     * @return array<int,string>
     */
    public static function all(): array
    {
        $app = app();

        if ($app->bound(self::MEMO)) {
            return $app->make(self::MEMO);
        }

        $map = [];

        if (Schema::hasTable(self::TABLE)) {
            foreach (DB::table(self::TABLE)->select('order_status_id', 'placement')->get() as $row) {
                $map[(int) $row->order_status_id] = (string) $row->placement;
            }
        }

        $app->instance(self::MEMO, $map);

        return $map;
    }

    /** The placement of a status, or null when it carries none. */
    public static function placementFor(?int $orderStatusId): ?string
    {
        if (!$orderStatusId) {
            return null;
        }

        return self::all()[$orderStatusId] ?? null;
    }

    /**
     * "Is this status mapped?" - the accessor the Status placement page sorts
     * and groups by, and the one honest way to ask whether a status has been
     * placed at all (as opposed to being placed somewhere uninteresting).
     */
    public static function isMapped(?int $orderStatusId): bool
    {
        return self::placementFor($orderStatusId) !== null;
    }

    /**
     * Every status id carrying a placement.
     *
     * @return array<int,int>
     */
    public static function statusIdsFor(string $placement): array
    {
        return array_values(array_keys(array_filter(
            self::all(),
            fn (string $value) => $value === $placement,
        )));
    }

    /** Place a status, or unplace it when $placement is null. */
    public static function put(int $orderStatusId, ?string $placement): void
    {
        if ($placement === null || $placement === '') {
            self::forget($orderStatusId);

            return;
        }

        $now = now();
        $existing = DB::table(self::TABLE)->where('order_status_id', $orderStatusId);

        // Not updateOrInsert(): its update payload would have to carry
        // created_at too, silently re-stamping when a mapping was first made.
        if ($existing->exists()) {
            $existing->update(['placement' => $placement, 'updated_at' => $now]);
        } else {
            DB::table(self::TABLE)->insert([
                'order_status_id' => $orderStatusId,
                'placement'       => $placement,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }

        self::flush();
    }

    /** Remove a status's placement, returning it to unplaced. */
    public static function forget(int $orderStatusId): void
    {
        DB::table(self::TABLE)->where('order_status_id', $orderStatusId)->delete();

        self::flush();
    }
}
