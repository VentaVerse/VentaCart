<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderStatus;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;

/**
 * The one place that answers "what is revenue".
 *
 * Before this class, the answer was a hardcoded list of status NAMES inside
 * the dashboard controller: rename Canceled and your revenue figure moved
 * without warning. Revenue is now a declared property of each order status
 * (`order_statuses.counts_as_revenue`, editable on the Order Statuses page),
 * and this is its single reader. Every revenue figure in Venta comes through
 * here; a second copy of this rule is the defect this class exists to remove,
 * and RevenueResolverTest fails the build if one appears.
 *
 * TWO RULES, AND ONE OF THEM IS NOT NEGOTIABLE
 *
 *   1. A status counts as revenue when it says it does, and never because of
 *      what it is called.
 *   2. An order with NO status NEVER counts, whatever any flag says. Those
 *      rows are ghosts of abandoned checkouts, not orders: a cart that reached
 *      the orders table and stopped. OpenCart counted them, which is exactly
 *      what Venta refuses to do. The rule lives in the SQL below rather than
 *      in a setting, so no configuration can turn it off, and it matches what
 *      the fulfillment queue and the Orders page already do.
 *
 * EMPTY MEANS EMPTY. With no status declaring itself revenue, revenue is zero
 * rather than everything. The old name-list model fell back the other way (it
 * recognised no cancelled names, so it counted the lot); under a declared flag
 * that fallback would be a lie, and an obviously wrong zero sends the merchant
 * to the Order Statuses page, which is where the answer is.
 *
 * CACHING: one query per request builds the id set, which is a handful of rows
 * and never proportional to orders. The memo lives in the CONTAINER, not in a
 * static: a static would survive between tests (same PHP process, fresh
 * application each time) and hand one test the previous test's answer. The
 * OrderStatus model flushes it on every save and delete, so a stale read is
 * not reachable through normal use. Pattern matches PlacementMap.
 */
class Revenue
{
    /**
     * Container key the per-application memo is bound under. ONE key holding a
     * store-id => ids map, rather than a key per store, so flushing is a
     * single forgetInstance that cannot miss a store it never heard of.
     */
    private const MEMO = 'core.revenue.status-ids';

    public static function flush(): void
    {
        app()->forgetInstance(self::MEMO);
    }

    /**
     * Every order status id that declares itself revenue, for one store.
     *
     * @return array<int,int>
     */
    public static function statusIds(?int $storeId = null): array
    {
        $storeId = $storeId ?? self::defaultStoreId();
        $app = app();

        $memo = $app->bound(self::MEMO) ? $app->make(self::MEMO) : [];

        if (array_key_exists($storeId, $memo)) {
            return $memo[$storeId];
        }

        $memo[$storeId] = OrderStatus::query()
            ->where('store_id', $storeId)
            ->where('counts_as_revenue', true)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $app->instance(self::MEMO, $memo);

        return $memo[$storeId];
    }

    /**
     * The SQL predicate that says "this row is money", for the aggregate-shaped
     * callers that must express revenue inside a larger single query (the day
     * sheet totals nine figures in one statement and is not going to become
     * nine statements to use this class).
     *
     * Safe to interpolate: the output is built from integers cast with intval
     * and from a table name supplied by the caller's own code, never from
     * request input.
     *
     * The IS NOT NULL clause is redundant in SQL - `NULL IN (1,2)` is already
     * not true - and it is written anyway. It is the hard rule made visible at
     * the exact point it is enforced, so a future edit to the shape of this
     * predicate cannot quietly readmit ghost orders.
     */
    public static function condition(?int $storeId = null, string $table = 'orders'): string
    {
        $ids = self::statusIds($storeId);

        if ($ids === []) {
            return '0';
        }

        $column = $table . '.order_status_id';

        return '(' . $column . ' IS NOT NULL AND ' . $column . ' IN ('
            . implode(',', array_map('intval', $ids)) . '))';
    }

    /**
     * The Eloquent form of the same rule, for callers building a query rather
     * than an aggregate expression.
     *
     * @template TBuilder of EloquentBuilder|QueryBuilder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public static function applyTo($query, ?int $storeId = null, string $table = 'orders')
    {
        $ids = self::statusIds($storeId);

        if ($ids === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query
            ->whereNotNull($table . '.order_status_id')
            ->whereIn($table . '.order_status_id', $ids);
    }

    /**
     * Revenue taken by one store over one period, [$from, $to) - half open, so
     * two adjacent periods can never double count the order that lands on the
     * boundary.
     */
    public static function sum(int $storeId, Carbon $from, Carbon $to): float
    {
        return (float) self::applyTo(
            Order::query()
                ->where('store_id', $storeId)
                ->where('created_at', '>=', $from)
                ->where('created_at', '<', $to),
            $storeId
        )->sum('total');
    }

    private static function defaultStoreId(): int
    {
        return (int) config('app.store_id', 1);
    }
}
