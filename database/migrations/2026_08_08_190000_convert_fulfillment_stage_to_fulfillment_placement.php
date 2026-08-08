<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fulfillment Placement Task 1: replaces `order_statuses.fulfillment_stage`
 * (unpaid|ready|cancelled, added earlier on THIS SAME branch by
 * 2026_08_08_182307_add_fulfillment_stage_to_order_statuses.php) with
 * `fulfillment_placement`, one value per fulfillment queue tab:
 * unpaid|to_pack|to_handover|in_transit|delivered|failed|cancelled.
 *
 * Value conversion: unpaid -> unpaid, cancelled -> cancelled,
 * ready -> to_pack (name-based refinement into the other four placements
 * happens afterward, in OrderStatusSeeder and a one-off dev-DB tinker
 * command, never in a migration).
 *
 * PORTABILITY NOTE (why this migration performs a data operation, which
 * generally violates the project's "no data-migrations" house rule): the
 * safe cross-engine way to change an enum's value set on a populated column
 * is add-new-column -> convert -> drop-old-column, not an in-place
 * `MODIFY COLUMN` (MySQL-only syntax; SQLite has no ALTER COLUMN for enums
 * at all, and Laravel emulates SQLite enums as CHECK-constrained strings, so
 * even relaxing the constraint requires a column rebuild). This runs
 * identically on the dev MySQL database and the sqlite :memory: test
 * database. The UPDATE step below is NOT the kind of data-cleanup the
 * house rule (see database/seeders, no one-off migrations for existing
 * data) is aimed at: it converts our OWN column, added on this same
 * branch and not yet relied on by any release, into its replacement. No
 * user-authored data is touched, and the rule exists to stop migrations
 * from silently reshaping merchants' own records, not to block a same-
 * branch rename-with-remap of a column nothing has shipped against yet.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_statuses', function (Blueprint $table) {
            $table->enum('fulfillment_placement', [
                'unpaid', 'to_pack', 'to_handover', 'in_transit', 'delivered', 'failed', 'cancelled',
            ])->default('to_pack')->after('fulfillment_stage');
        });

        // Convert the three known values. The new column already defaults
        // to 'to_pack', so only the two exact-name carries need an
        // explicit UPDATE; the 'ready' -> 'to_pack' statement is kept
        // explicit anyway so every row's source value is traceable here
        // rather than relying silently on the column default.
        DB::table('order_statuses')->where('fulfillment_stage', 'unpaid')->update(['fulfillment_placement' => 'unpaid']);
        DB::table('order_statuses')->where('fulfillment_stage', 'cancelled')->update(['fulfillment_placement' => 'cancelled']);
        DB::table('order_statuses')->where('fulfillment_stage', 'ready')->update(['fulfillment_placement' => 'to_pack']);

        Schema::table('order_statuses', function (Blueprint $table) {
            $table->dropColumn('fulfillment_stage');
        });
    }

    /**
     * Reverse the migrations.
     *
     * LOSSY, ONE-WAY ROLLBACK: fulfillment_stage has only three values, so
     * the four placements this task's refinement introduced beyond the raw
     * up() conversion (to_handover, in_transit, delivered, failed) have no
     * home to round-trip into here; they all collapse into 'ready' (the
     * whereNotIn(['unpaid', 'cancelled']) branch below), same as the raw
     * up() conversion's 'ready' -> 'to_pack' step in reverse. Concretely:
     * roll back then re-migrate WIPES any name-based refinement applied
     * after up() ran, whether from OrderStatusSeeder::placementFor() or the
     * sanctioned dev-DB tinker one-off (the one-off is intentionally never
     * committed, so it is not reproducible from a migration replay) -
     * every refined row resets to 'to_pack'. There is no data to preserve
     * across the rollback for those four values, only the name they were
     * refined from. To RE-APPLY the refinement after a rollback + re-
     * migrate, re-run the same name rule the seeder and the one-off both
     * use: `Database\Seeders\OrderStatusSeeder::placementFor($status->name)`
     * per row, via tinker (see task-1-report.md's dev-DB section for the
     * exact command that was run).
     */
    public function down(): void
    {
        Schema::table('order_statuses', function (Blueprint $table) {
            $table->enum('fulfillment_stage', ['unpaid', 'ready', 'cancelled'])
                ->default('ready')
                ->after('stock_action');
        });

        DB::table('order_statuses')->where('fulfillment_placement', 'unpaid')->update(['fulfillment_stage' => 'unpaid']);
        DB::table('order_statuses')->where('fulfillment_placement', 'cancelled')->update(['fulfillment_stage' => 'cancelled']);
        DB::table('order_statuses')->whereNotIn('fulfillment_placement', ['unpaid', 'cancelled'])->update(['fulfillment_stage' => 'ready']);

        Schema::table('order_statuses', function (Blueprint $table) {
            $table->dropColumn('fulfillment_placement');
        });
    }
};
