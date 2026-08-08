<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fulfillment tab rework (owner rulings, 2026-08-09): reshapes
 * `order_statuses.fulfillment_placement`'s value set.
 *
 *  - `in_transit` is RENAMED to `shipping` (label, URL filter value, enum
 *    value and every code identifier move together - the owner's word for
 *    this tab is Shipping).
 *  - `lost_damaged` is ADDED: the new Lost and Damaged tab, the worklist for
 *    insurance claims. Neither courier exposes a claims API, so this is
 *    where a human chases the money.
 *
 * New value set, in tab order:
 * unpaid|to_pack|to_handover|shipping|failed|lost_damaged|delivered|cancelled.
 *
 * (`all` is deliberately NOT a value: the All tab is a VIEW over every
 * order, never a placement a status can be put into.)
 *
 * PORTABILITY NOTE - same reasoning, and the same shape, as this column's
 * previous migration (2026_08_08_190000_convert_fulfillment_stage_to_
 * fulfillment_placement.php): the safe cross-engine way to change an enum's
 * value set on a populated column is add-new-column -> convert -> drop-old,
 * not an in-place `MODIFY COLUMN` (MySQL-only syntax; SQLite has no ALTER
 * COLUMN for enums at all, and Laravel emulates SQLite enums as
 * CHECK-constrained strings, so even relaxing the constraint requires a
 * column rebuild). One step is added here that the predecessor did not need:
 * the column KEEPS its name this time, so the freshly built column is
 * renamed back into place after the old one is dropped. Both statements are
 * native on MySQL 8 and on SQLite 3.25+, so this runs identically on the dev
 * MySQL database and the sqlite :memory: test database.
 *
 * The UPDATE step is NOT the kind of data-cleanup the house rule (no
 * one-off data migrations) is aimed at: it converts our OWN column, added on
 * this same branch and not yet relied on by any release, into its
 * replacement. No merchant-authored data is touched.
 */
return new class extends Migration
{
    /** The new value set, in tab order. */
    private const VALUES = [
        'unpaid', 'to_pack', 'to_handover', 'shipping',
        'failed', 'lost_damaged', 'delivered', 'cancelled',
    ];

    /** The value set this migration replaces. */
    private const OLD_VALUES = [
        'unpaid', 'to_pack', 'to_handover', 'in_transit', 'delivered', 'failed', 'cancelled',
    ];

    /** Values carried across unchanged (everything except the rename). */
    private const CARRIED = ['unpaid', 'to_pack', 'to_handover', 'delivered', 'failed', 'cancelled'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_statuses', function (Blueprint $table) {
            $table->enum('fulfillment_placement_next', self::VALUES)
                ->default('to_pack')
                ->after('fulfillment_placement');
        });

        // Every value is carried explicitly (rather than relying silently on
        // the new column's default) so each row's source value is traceable
        // here, exactly as the predecessor migration does.
        foreach (self::CARRIED as $value) {
            DB::table('order_statuses')
                ->where('fulfillment_placement', $value)
                ->update(['fulfillment_placement_next' => $value]);
        }

        // The rename itself, the only value that changes meaning-for-meaning.
        DB::table('order_statuses')
            ->where('fulfillment_placement', 'in_transit')
            ->update(['fulfillment_placement_next' => 'shipping']);

        Schema::table('order_statuses', function (Blueprint $table) {
            $table->dropColumn('fulfillment_placement');
        });

        // Separate call on purpose: SQLite rebuilds the table for the drop
        // above, so the rename has to be issued against the rebuilt table.
        Schema::table('order_statuses', function (Blueprint $table) {
            $table->renameColumn('fulfillment_placement_next', 'fulfillment_placement');
        });
    }

    /**
     * Reverse the migrations.
     *
     * LOSSY for ONE value: the old set has no Lost and Damaged tab, so
     * `lost_damaged` rows collapse into `failed` (the nearest surviving
     * placement - both are "this parcel did not arrive"). `shipping` maps
     * back to `in_transit`, and every other value round-trips exactly.
     * Re-running up() after a rollback therefore restores everything except
     * which of the failed-placed statuses had been moved to Lost and
     * Damaged; that choice is the owner's, made in Sales, Order Statuses,
     * and is re-made there.
     */
    public function down(): void
    {
        Schema::table('order_statuses', function (Blueprint $table) {
            $table->enum('fulfillment_placement_prev', self::OLD_VALUES)
                ->default('to_pack')
                ->after('fulfillment_placement');
        });

        foreach (self::CARRIED as $value) {
            DB::table('order_statuses')
                ->where('fulfillment_placement', $value)
                ->update(['fulfillment_placement_prev' => $value]);
        }

        DB::table('order_statuses')
            ->where('fulfillment_placement', 'shipping')
            ->update(['fulfillment_placement_prev' => 'in_transit']);

        DB::table('order_statuses')
            ->where('fulfillment_placement', 'lost_damaged')
            ->update(['fulfillment_placement_prev' => 'failed']);

        Schema::table('order_statuses', function (Blueprint $table) {
            $table->dropColumn('fulfillment_placement');
        });

        Schema::table('order_statuses', function (Blueprint $table) {
            $table->renameColumn('fulfillment_placement_prev', 'fulfillment_placement');
        });
    }
};
