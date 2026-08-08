<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stock model simplification (owner ruling, 2026-08-08): "It should always be
 * decrement. If not decrement, therefore move the stock back. It is just a
 * reversal of stock count. There is no need to tag an order status with
 * increment."
 *
 * So order_statuses.stock_action (decrement | increment | null) becomes
 * order_statuses.deducts_stock (boolean). A status either takes goods OUT of
 * inventory or it does not. Putting them back is no longer something a status
 * is tagged with: it is what StockService does automatically when an order
 * whose stock is currently out moves into a status that does not deduct.
 *
 * ---------------------------------------------------------------------------
 * DATA CONVERSION (sanctioned in this migration, owner-approved)
 * ---------------------------------------------------------------------------
 * The conversion is keyed on status NAME (case-insensitive) rather than on id,
 * so it lands correctly on live as well as on dev, and it is a deliberate
 * no-op for any name it does not recognise:
 *
 *   1. Baseline from the old column - 'decrement' becomes deducting; both
 *      'increment' and NULL become non-deducting. A merchant's custom status
 *      therefore converts to the closest safe equivalent with no name in the
 *      lists below, and an 'increment' status behaves exactly as it did
 *      before, because the reversal it used to be tagged with is now
 *      automatic.
 *   2. Then the name lists win over that baseline. Seven statuses that were
 *      untagged genuinely have the goods out of inventory (the parcel is in
 *      transit, lost, returning or damaged); leaving them non-deducting would
 *      put stock back on the shelf while a real parcel is still out. They
 *      become deducting. Quote Ready for Payment is a pre-order quote with
 *      nothing shipped, so it is pinned non-deducting.
 *
 * The name lists here are NOT the same as OrderStatusSeeder's fresh-install
 * defaults and must not be merged with them. This migration preserves the
 * behaviour of an EXISTING store (e.g. Pending is 'decrement' on NGD, so it
 * stays deducting here), while the seeder describes what a BRAND NEW store
 * should start with. Conflating the two would silently re-tag live statuses.
 *
 * ---------------------------------------------------------------------------
 * WHAT THIS MIGRATION TOUCHES - AND THE ZERO-INVENTORY GUARANTEE
 * ---------------------------------------------------------------------------
 * The order_statuses table, and nothing else. It never writes to `orders` (in
 * particular never to orders.stock_adjusted), never writes to `products`,
 * `product_variants` or any quantity column, and it triggers no recompute
 * pass. Deploying it moves ZERO inventory: every order keeps the adjusted
 * state it already had, and the next status change is what applies the new
 * rule. tests/Feature/StockDeductsStockMigrationTest.php pins that guarantee
 * by snapshotting every quantity and every stock_adjusted flag around a real
 * run of up().
 */
return new class extends Migration
{
    /**
     * Untagged today, deducting after this migration: in every one of these
     * states the goods are genuinely out of inventory.
     */
    private const DEDUCTING_NAMES = [
        'Arrived at NGD',
        'In Transit to Manila',
        'Delivery Failed',
        'Pickup Failed',
        'Returning',
        'Lost',
        'Damaged',
    ];

    /**
     * Pinned non-deducting: a pre-order quote, nothing has left the shelf.
     */
    private const NON_DEDUCTING_NAMES = [
        'Quote Ready for Payment',
    ];

    public function up(): void
    {
        if (Schema::hasColumn('order_statuses', 'deducts_stock')) {
            return;
        }

        Schema::table('order_statuses', function (Blueprint $table) {
            $table->boolean('deducts_stock')->default(false)->after('is_default');
        });

        // 1. Baseline from the retired column. Everything defaults to false
        //    already, so only the deducting side needs a write.
        if (Schema::hasColumn('order_statuses', 'stock_action')) {
            DB::table('order_statuses')->where('stock_action', 'decrement')->update(['deducts_stock' => true]);
        }

        // 2. Name lists win over the baseline.
        $this->applyByName(self::DEDUCTING_NAMES, true);
        $this->applyByName(self::NON_DEDUCTING_NAMES, false);

        if (Schema::hasColumn('order_statuses', 'stock_action')) {
            Schema::table('order_statuses', function (Blueprint $table) {
                $table->dropColumn('stock_action');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('order_statuses', 'stock_action')) {
            Schema::table('order_statuses', function (Blueprint $table) {
                $table->string('stock_action')->nullable()->after('is_default');
            });
        }

        // Lossy by nature: the old model's 'increment' tag no longer exists to
        // be restored, and under the new model it carried no information a
        // reversal does not derive on its own. Rolling back therefore yields
        // 'decrement' or NULL, which is behaviourally identical.
        DB::table('order_statuses')->where('deducts_stock', true)->update(['stock_action' => 'decrement']);

        if (Schema::hasColumn('order_statuses', 'deducts_stock')) {
            Schema::table('order_statuses', function (Blueprint $table) {
                $table->dropColumn('deducts_stock');
            });
        }
    }

    /**
     * Case-insensitive name match that behaves the same on MySQL (whose
     * default collation is already case-insensitive) and on SQLite (whose IN
     * is not), without a raw-string concat of user data: LOWER() exists in
     * both engines and every value stays a bound parameter.
     */
    private function applyByName(array $names, bool $deducts): void
    {
        if ($names === []) {
            return;
        }

        $lowered = array_map(fn (string $name) => mb_strtolower($name), $names);
        $placeholders = implode(',', array_fill(0, count($lowered), '?'));

        DB::table('order_statuses')
            ->whereRaw("LOWER(name) IN ({$placeholders})", $lowered)
            ->update(['deducts_stock' => $deducts]);
    }
};
