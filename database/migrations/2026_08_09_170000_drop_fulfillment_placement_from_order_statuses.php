<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Core sheds the fulfillment column (owner ruling, 2026-08-09).
 *
 * `order_statuses.fulfillment_placement` named a fulfillment QUEUE TAB on a
 * core table, which made an optional module's vocabulary part of core's
 * schema. The mapping now lives in the courier-fulfillment extension's own
 * table (courier_fulfillment_status_placements), created and dropped with the
 * module. The values are carried across by that extension's migration, which
 * reads this column while it still exists.
 *
 * ORDERING on an existing install: run the extension's migrations FIRST
 * (`php artisan migrate --path=extensions/courier-fulfillment/database/migrations`,
 * which is also what ExtensionManager::install() runs), then core's. The
 * carry-over is guarded on the column existing, so the reverse order simply
 * leaves the mapping empty rather than erroring - every status reads as
 * unplaced and the merchant re-places them on the module's own Status
 * placement page.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('order_statuses') || !Schema::hasColumn('order_statuses', 'fulfillment_placement')) {
            return;
        }

        Schema::table('order_statuses', function (Blueprint $table) {
            $table->dropColumn('fulfillment_placement');
        });
    }

    /**
     * Re-adds the column in its final shape (the eight-value enum). Rolling
     * back does NOT restore any values: they live in the module's table from
     * here on, and re-running this migration forward drops the empty column
     * again without touching them.
     */
    public function down(): void
    {
        if (!Schema::hasTable('order_statuses') || Schema::hasColumn('order_statuses', 'fulfillment_placement')) {
            return;
        }

        Schema::table('order_statuses', function (Blueprint $table) {
            $table->enum('fulfillment_placement', [
                'unpaid', 'to_pack', 'to_handover', 'shipping',
                'failed', 'lost_damaged', 'delivered', 'cancelled',
            ])->default('to_pack')->after('stock_action');
        });
    }
};
