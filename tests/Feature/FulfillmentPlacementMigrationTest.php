<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Services\ExtensionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The status-placement mapping's move OUT of core (owner ruling, 2026-08-09).
 *
 * It began life as `order_statuses.fulfillment_placement`, a column on a core
 * table. It now lives in `courier_fulfillment_status_placements`, created by
 * the courier-fulfillment extension's install migration and dropped by its
 * uninstall rollback, so a Venta install without the module carries no trace
 * of it.
 *
 * RefreshDatabase already runs every core migration before each test, so these
 * tests roll back to exactly the migration under test, seed old-shape rows by
 * hand, then run forward to prove the carry-over actually happens - on the
 * same sqlite :memory: engine the rest of the suite runs on.
 */
class FulfillmentPlacementMigrationTest extends TestCase
{
    use RefreshDatabase;

    /** The core migration that removes the column from order_statuses. */
    private const DROP_MIGRATION = '2026_08_09_170000_drop_fulfillment_placement_from_order_statuses';

    private const TABLE = 'courier_fulfillment_status_placements';

    /**
     * Rolls back every migration from $migration onward (inclusive), rather
     * than assuming the target is the most recent file: a later migration
     * landing on top must not silently make --step=1 roll back the wrong one.
     */
    private function rollbackTo(string $migration): void
    {
        $batchId = DB::table('migrations')->where('migration', $migration)->value('id');
        $this->assertNotNull($batchId, "Migration {$migration} has not run; this test cannot roll back to it.");

        $steps = DB::table('migrations')->where('id', '>=', $batchId)->count();
        Artisan::call('migrate:rollback', ['--step' => $steps]);
    }

    // ------------------------------------------------------------------
    // Core sheds the column.
    // ------------------------------------------------------------------

    public function test_core_order_statuses_carries_no_fulfillment_column(): void
    {
        $this->assertFalse(Schema::hasColumn('order_statuses', 'fulfillment_placement'));
        $this->assertFalse(Schema::hasColumn('order_statuses', 'fulfillment_stage'));
    }

    /** The module's table is not part of core: a bare `migrate` never creates it. */
    public function test_a_core_only_install_has_no_placement_table(): void
    {
        $this->assertFalse(Schema::hasTable(self::TABLE));
    }

    // ------------------------------------------------------------------
    // The module owns the table's whole lifecycle.
    // ------------------------------------------------------------------

    public function test_installing_the_module_creates_the_mapping_table_and_uninstalling_drops_it(): void
    {
        $this->assertFalse(Schema::hasTable(self::TABLE));

        app(ExtensionManager::class)->install('courier-fulfillment');
        $this->assertTrue(Schema::hasTable(self::TABLE));

        // The shape the module reads: one placement per status.
        $this->assertTrue(Schema::hasColumn(self::TABLE, 'order_status_id'));
        $this->assertTrue(Schema::hasColumn(self::TABLE, 'placement'));

        app(ExtensionManager::class)->uninstall('courier-fulfillment');
        $this->assertFalse(Schema::hasTable(self::TABLE));

        // Uninstalling leaves core exactly as it found it.
        $this->assertFalse(Schema::hasColumn('order_statuses', 'fulfillment_placement'));
    }

    /** One row per status: a second mapping for the same status is impossible. */
    public function test_a_status_can_carry_only_one_placement(): void
    {
        app(ExtensionManager::class)->install('courier-fulfillment');
        Store::create(['id' => 1, 'name' => 'Test Store', 'code' => 'test']);

        $now = now();
        $id = DB::table('order_statuses')->insertGetId([
            'store_id' => 1, 'name' => 'Shipped', 'sort_order' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        DB::table(self::TABLE)->insert([
            'order_status_id' => $id, 'placement' => 'shipping',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table(self::TABLE)->insert([
            'order_status_id' => $id, 'placement' => 'delivered',
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    // ------------------------------------------------------------------
    // The carry-over: values that existed in the core column land in the
    // module's table intact, and only then does the column go.
    // ------------------------------------------------------------------

    public function test_existing_placements_migrate_out_of_the_core_column_intact(): void
    {
        // Back to the state an existing install is in: the column still
        // exists, carrying the placements the merchant set on it.
        $this->rollbackTo(self::DROP_MIGRATION);
        $this->assertTrue(Schema::hasColumn('order_statuses', 'fulfillment_placement'));

        Store::create(['id' => 1, 'name' => 'Test Store', 'code' => 'test']);

        $now = now();
        DB::table('order_statuses')->insert([
            ['store_id' => 1, 'name' => 'Unpaid Row', 'sort_order' => 1, 'fulfillment_placement' => 'unpaid', 'created_at' => $now, 'updated_at' => $now],
            ['store_id' => 1, 'name' => 'Handover Row', 'sort_order' => 2, 'fulfillment_placement' => 'to_handover', 'created_at' => $now, 'updated_at' => $now],
            ['store_id' => 1, 'name' => 'Shipping Row', 'sort_order' => 3, 'fulfillment_placement' => 'shipping', 'created_at' => $now, 'updated_at' => $now],
            ['store_id' => 1, 'name' => 'Lost Row', 'sort_order' => 4, 'fulfillment_placement' => 'lost_damaged', 'created_at' => $now, 'updated_at' => $now],
            ['store_id' => 1, 'name' => 'Delivered Row', 'sort_order' => 5, 'fulfillment_placement' => 'delivered', 'created_at' => $now, 'updated_at' => $now],
        ]);

        // Installing the module runs its own migrations, which is where the
        // carry-over lives (core never writes the module's table).
        app(ExtensionManager::class)->install('courier-fulfillment');

        $expected = [
            'Unpaid Row'    => 'unpaid',
            'Handover Row'  => 'to_handover',
            'Shipping Row'  => 'shipping',
            'Lost Row'      => 'lost_damaged',
            'Delivered Row' => 'delivered',
        ];

        foreach ($expected as $name => $placement) {
            $id = DB::table('order_statuses')->where('name', $name)->value('id');
            $this->assertSame(
                $placement,
                DB::table(self::TABLE)->where('order_status_id', $id)->value('placement'),
                "[{$name}] should have carried [{$placement}] across.",
            );
        }

        // Then core drops the column, and the mapping is untouched by it.
        Artisan::call('migrate');
        $this->assertFalse(Schema::hasColumn('order_statuses', 'fulfillment_placement'));
        $this->assertSame(5, DB::table(self::TABLE)->count());
    }

    /** A fresh install has nothing to carry over, and the migration says so quietly. */
    public function test_the_carry_over_is_a_no_op_on_a_fresh_install(): void
    {
        app(ExtensionManager::class)->install('courier-fulfillment');

        $this->assertTrue(Schema::hasTable(self::TABLE));
        $this->assertSame(0, DB::table(self::TABLE)->count());
    }
}
