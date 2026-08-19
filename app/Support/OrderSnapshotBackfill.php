<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Fills the snapshot columns on orders and order histories from the foreign
 * keys they already carry.
 *
 * One home, two callers: the migration that adds the columns runs it so a
 * store with existing history is correct the moment it deploys, and
 * `orders:backfill-snapshots` runs it as a repair tool. They were briefly two
 * copies of the same SQL, which is a drift waiting to happen on the one piece
 * of code that decides whether a shop's orders are visible.
 *
 * Only NULLs are written, so running it any number of times is a no-op after
 * the first, and a snapshot never follows a later rename: an order's history
 * is the order's own record, not a live lookup.
 *
 * Chunked by id because a real store has tens of thousands of rows and a
 * single UPDATE would hold a long lock.
 */
class OrderSnapshotBackfill
{
    /** @return array<int, array{0:string,1:string,2:string}> table, column, derivation */
    public static function targets(): array
    {
        $p = DB::getTablePrefix();

        return [
            ['orders', 'order_status_name',
                "(SELECT s.name FROM {$p}order_statuses s WHERE s.id = {$p}orders.order_status_id)"],
            ['orders', 'customer_group_name',
                "(SELECT g.name FROM {$p}customers c INNER JOIN {$p}customer_groups g ON g.id = c.customer_group_id WHERE c.id = {$p}orders.customer_id)"],
            ['orders', 'shipping_courier_name',
                "(SELECT sc.name FROM {$p}shipping_couriers sc WHERE sc.id = {$p}orders.shipping_courier_id)"],
            ['orders', 'created_by_name',
                "(SELECT u.name FROM {$p}users u WHERE u.id = {$p}orders.created_by)"],
            ['order_histories', 'order_status_name',
                "(SELECT s.name FROM {$p}order_statuses s WHERE s.id = {$p}order_histories.order_status_id)"],
            ['order_histories', 'created_by_name',
                "(SELECT u.name FROM {$p}users u WHERE u.id = {$p}order_histories.created_by)"],
            ['order_products', 'image',
                "(SELECT pr.image FROM {$p}products pr WHERE pr.id = {$p}order_products.product_id)"],
        ];
    }

    /**
     * @return array<string, int> "table.column" => rows written
     */
    public static function run(int $chunk = 2000, bool $dryRun = false): array
    {
        $written = [];

        foreach (self::targets() as [$table, $column, $subquery]) {
            $written["{$table}.{$column}"] = self::fill($table, $column, $subquery, $chunk, $dryRun);
        }

        return $written;
    }

    private static function fill(string $table, string $column, string $subquery, int $chunk, bool $dryRun): int
    {
        $bounds = DB::table($table)->selectRaw('MIN(id) AS lo, MAX(id) AS hi')->first();

        if (! $bounds || $bounds->lo === null) {
            return 0; // fresh install: nothing to carry forward
        }

        $total = 0;

        for ($lo = (int) $bounds->lo; $lo <= (int) $bounds->hi; $lo += $chunk) {
            $pending = fn () => DB::table($table)
                ->whereBetween('id', [$lo, $lo + $chunk - 1])
                ->whereNull($column);

            $total += $dryRun
                ? $pending()->count()
                : $pending()->update([$column => DB::raw($subquery)]);
        }

        return $total;
    }
}
