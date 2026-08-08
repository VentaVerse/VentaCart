<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The status-to-tab mapping the fulfillment queue reads, owned by the MODULE
 * (owner ruling, 2026-08-09: "Remove the mapping inside order status. This is
 * a coupling. Fulfilment is its own module, mapping should be inside the
 * fulfilment setting.").
 *
 * It used to be `order_statuses.fulfillment_placement`, a column on a CORE
 * table. A Venta install without this extension must carry no trace of it, so
 * the mapping now lives here: created by this extension's install migration,
 * dropped by its uninstall rollback, exactly like the orders-columns migration
 * beside it. Core keeps a plain CRUD Order Statuses page and no fulfillment
 * column at all.
 *
 * One row per PLACED status. A status with NO row is unplaced, which is a real
 * and expected state (a fresh install seeds nothing): unplaced statuses appear
 * in the queue's All tab and in no other tab, so nothing is ever hidden.
 *
 * No foreign key onto order_statuses on purpose: an extension table must not
 * add schema-level constraints to a core table it does not own (a core
 * order_statuses rebuild would then fail whenever this module happens to be
 * installed, which is precisely the coupling this move removes). A deleted
 * status can leave an orphan row behind; nothing reads it, since every lookup
 * starts from an order's live order_status_id.
 */
return new class extends Migration
{
    private const TABLE = 'courier_fulfillment_status_placements';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            Schema::create(self::TABLE, function (Blueprint $table) {
                // The order status this row places. Primary key, so a status
                // can carry exactly one placement and an upsert can never
                // create a duplicate.
                $table->unsignedBigInteger('order_status_id')->primary();
                $table->string('placement', 32);
                $table->timestamps();
                $table->index('placement');
            });
        }

        // One-time carry-over from the core column this table replaces. The
        // column was added on this same branch and has never shipped, so
        // moving OUR OWN values out of it is a rename-in-place rather than
        // the kind of merchant-data cleanup the house rule forbids in a
        // migration. Guarded on the column still existing, so it is a no-op
        // on a fresh install (where core's drop migration has already run)
        // and on any repeat run.
        if (!Schema::hasTable('order_statuses') || !Schema::hasColumn('order_statuses', 'fulfillment_placement')) {
            return;
        }

        $now = now();

        DB::table('order_statuses')
            ->whereNotNull('fulfillment_placement')
            ->orderBy('id')
            ->select('id', 'fulfillment_placement')
            ->chunk(500, function ($statuses) use ($now) {
                $rows = [];
                foreach ($statuses as $status) {
                    $rows[] = [
                        'order_status_id' => (int) $status->id,
                        'placement'       => (string) $status->fulfillment_placement,
                        'created_at'      => $now,
                        'updated_at'      => $now,
                    ];
                }

                if ($rows !== []) {
                    // insertOrIgnore, not insert: re-running this migration
                    // against a table that already holds the mapping must not
                    // explode on the primary key.
                    DB::table(self::TABLE)->insertOrIgnore($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }
};
