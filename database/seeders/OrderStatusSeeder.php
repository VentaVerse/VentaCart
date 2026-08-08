<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The store's baseline order statuses: OpenCart's own fourteen defaults, plus
 * Delivered and Unpaid (owner ruling, 2026-08-09 - "mirror Opencart's initial
 * order status. Complete and Delivered should be in the seeder. Put Unpaid
 * also. Everything else, remove.").
 *
 * Sixteen rows, nothing else. Sort orders match OpenCart's own status ids for
 * the fourteen it contributes, so a migrated store's list reads in the order
 * its operators already know.
 *
 * NO PLACEMENT DATA. Which fulfillment queue tab a status belongs to is data
 * owned by the courier-fulfillment module (its own table, its own Status
 * placement page), and that module deliberately seeds nothing: a merchant
 * configures it at setup. A fresh install therefore has every status unplaced,
 * which the module treats as "All tab only" rather than as an error.
 *
 * STOCK, on the other hand, IS core data and is seeded here. A status either
 * deducts stock or it does not (owner ruling, 2026-08-08); there is no
 * "increment" counterpart, because returning stock is the automatic reversal
 * StockService performs when an order whose stock is out reaches a status that
 * does not deduct. Only the five statuses below where goods have actually left
 * the shelf are seeded deducting - every terminal money-back status (Canceled,
 * Refunded, Chargeback and the rest) is left non-deducting precisely so that
 * reaching it hands the units back.
 *
 * These fresh-install defaults are NOT the same lists as the conversion in
 * 2026_08_09_180000_convert_stock_action_to_deducts_stock, which preserves what
 * an EXISTING store already had (NGD's Pending, for instance, deducts on live
 * and stays that way) rather than describing what a new store should start
 * with. The two must not be merged.
 */
class OrderStatusSeeder extends Seeder
{
    /**
     * Status names seeded as deducting: at each of these the goods are out of
     * inventory. Everything else in the list below is non-deducting.
     */
    private const DEDUCTS_STOCK = ['Processing', 'Processed', 'Shipped', 'Complete', 'Delivered'];

    public function run(): void
    {
        $storeId = 1;
        $now = now();

        $statuses = [
            ['name' => 'Pending', 'is_default' => true, 'sort_order' => 1],
            ['name' => 'Processing', 'is_default' => false, 'sort_order' => 2],
            ['name' => 'Shipped', 'is_default' => false, 'sort_order' => 3],
            ['name' => 'Complete', 'is_default' => false, 'sort_order' => 5],
            ['name' => 'Canceled', 'is_default' => false, 'sort_order' => 7],
            ['name' => 'Denied', 'is_default' => false, 'sort_order' => 8],
            ['name' => 'Canceled Reversal', 'is_default' => false, 'sort_order' => 9],
            ['name' => 'Failed', 'is_default' => false, 'sort_order' => 10],
            ['name' => 'Refunded', 'is_default' => false, 'sort_order' => 11],
            ['name' => 'Reversed', 'is_default' => false, 'sort_order' => 12],
            ['name' => 'Chargeback', 'is_default' => false, 'sort_order' => 13],
            ['name' => 'Expired', 'is_default' => false, 'sort_order' => 14],
            ['name' => 'Processed', 'is_default' => false, 'sort_order' => 15],
            ['name' => 'Voided', 'is_default' => false, 'sort_order' => 16],
            ['name' => 'Delivered', 'is_default' => false, 'sort_order' => 26],
            ['name' => 'Unpaid', 'is_default' => false, 'sort_order' => 24],
        ];

        foreach ($statuses as $status) {
            DB::table('order_statuses')->insertOrIgnore([
                'store_id' => $storeId,
                'name' => $status['name'],
                'is_default' => $status['is_default'],
                'deducts_stock' => in_array($status['name'], self::DEDUCTS_STOCK, true),
                'sort_order' => $status['sort_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
