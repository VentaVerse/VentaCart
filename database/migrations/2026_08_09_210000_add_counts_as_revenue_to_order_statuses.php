<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revenue becomes a per-status property (owner ruling, 2026-08-09).
 *
 * Until now, "which orders are money" was a hardcoded list of status NAMES
 * inside the dashboard controller. A merchant who renamed Canceled to
 * "Cancelled by buyer" silently fell out of that set, and their revenue figure
 * changed without a word. Revenue is the platform's own business, so the
 * answer belongs on the status row where the merchant can see and set it.
 *
 * CORE, DELIBERATELY. Contrast `fulfillment_placement`, which was moved OUT of
 * core into a module-owned table on 2026-08-09: which queue tab a parcel sits
 * in is the courier module's business, and an install without that module must
 * not carry its column. Revenue is not like that. A store with no fulfillment
 * module still takes money, so this column stays in core beside
 * `deducts_stock` and `is_default`.
 *
 * ---------------------------------------------------------------------------
 * THE DERIVATION (sanctioned in this migration, owner-approved)
 * ---------------------------------------------------------------------------
 * Initial value = whatever the status already does to stock. The owner's model
 * is that a status which took goods out of inventory is a status where revenue
 * is real. So `counts_as_revenue` is seeded from `deducts_stock`, row for row,
 * consulting nothing else: not the name, not the sort order, not whether any
 * order sits on it. That makes the derivation identical on dev, on live and on
 * a merchant's own install, and it never guesses at a name it has not seen.
 *
 * The two properties are independent from that moment on, because they usually
 * agree but not always: a Lost or Damaged parcel deducted stock and earned
 * nothing, and the merchant unticks it on the Order Statuses page. Nothing
 * here tries to be clever about those cases; guessing would be a worse answer
 * than a visible starting point the owner can correct in ten seconds.
 *
 * IDEMPOTENT: it derives only when it creates the column. Re-running up() on a
 * database that already has it does nothing, so a merchant's later decision is
 * never re-derived over.
 *
 * ---------------------------------------------------------------------------
 * WHAT THIS MIGRATION TOUCHES
 * ---------------------------------------------------------------------------
 * The `order_statuses` table, and nothing else. It never writes to `orders`,
 * never to `products` or `product_variants`, never to any quantity, total or
 * timestamp outside the status rows themselves, and it triggers no recompute.
 * No historical figure is restated: the only thing that changes is WHICH
 * statuses are counted, which is the correction the feature exists to make.
 * tests/Feature/RevenueCountsAsRevenueMigrationTest.php pins that by
 * snapshotting every other table byte for byte around a real run of up().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('order_statuses', 'counts_as_revenue')) {
            return;
        }

        Schema::table('order_statuses', function (Blueprint $table) {
            $table->boolean('counts_as_revenue')->default(false)->after('deducts_stock');
        });

        // The derivation, in one statement over our own new column. Written as
        // an UPDATE ... WHERE deducts_stock rather than a column-to-column
        // copy so it behaves identically on MySQL (tinyint) and SQLite
        // (integer), and so the default already covers the false side.
        DB::table('order_statuses')
            ->where('deducts_stock', true)
            ->update(['counts_as_revenue' => true]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('order_statuses', 'counts_as_revenue')) {
            return;
        }

        Schema::table('order_statuses', function (Blueprint $table) {
            $table->dropColumn('counts_as_revenue');
        });
    }
};
