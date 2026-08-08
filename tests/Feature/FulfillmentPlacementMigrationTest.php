<?php

namespace Tests\Feature;

use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The `order_statuses.fulfillment_placement` column, across BOTH migrations
 * that have shaped it on this branch:
 *
 *  1. Task 1 replaced fulfillment_stage (unpaid|ready|cancelled) with
 *     fulfillment_placement (unpaid|to_pack|to_handover|in_transit|
 *     delivered|failed|cancelled).
 *  2. The tab rework (owner rulings, 2026-08-09) renamed `in_transit` to
 *     `shipping` and added `lost_damaged`.
 *
 * RefreshDatabase already runs every migration before each test, so old-shape
 * rows can't exist in the normal test flow: these tests roll back to exactly
 * the migration under test, seed old-shape rows by hand, then re-run forward
 * to prove the conversions actually happen, on the SAME sqlite :memory:
 * engine the rest of the suite runs on.
 */
class FulfillmentPlacementMigrationTest extends TestCase
{
    use RefreshDatabase;

    /** The migration that introduced the placement column. */
    private const PLACEMENT_MIGRATION = '2026_08_08_190000_convert_fulfillment_stage_to_fulfillment_placement';

    /** The migration that renamed in_transit to shipping and added lost_damaged. */
    private const SHIPPING_MIGRATION = '2026_08_09_120000_rename_in_transit_placement_to_shipping_and_add_lost_damaged';

    /** The value set the column carries today, in tab order. */
    private const PLACEMENT_VALUES = [
        'unpaid', 'to_pack', 'to_handover', 'shipping',
        'failed', 'lost_damaged', 'delivered', 'cancelled',
    ];

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

    public function test_migration_converts_the_three_known_stage_values(): void
    {
        $this->rollbackTo(self::PLACEMENT_MIGRATION);

        Store::create(['id' => 1, 'name' => 'Test Store', 'code' => 'test']);

        $now = now();
        DB::table('order_statuses')->insert([
            ['store_id' => 1, 'name' => 'Unpaid Row', 'sort_order' => 1, 'fulfillment_stage' => 'unpaid', 'created_at' => $now, 'updated_at' => $now],
            ['store_id' => 1, 'name' => 'Canceled Row', 'sort_order' => 2, 'fulfillment_stage' => 'cancelled', 'created_at' => $now, 'updated_at' => $now],
            ['store_id' => 1, 'name' => 'Processing Row', 'sort_order' => 3, 'fulfillment_stage' => 'ready', 'created_at' => $now, 'updated_at' => $now],
        ]);

        Artisan::call('migrate');

        $this->assertSame('unpaid', DB::table('order_statuses')->where('name', 'Unpaid Row')->value('fulfillment_placement'));
        $this->assertSame('cancelled', DB::table('order_statuses')->where('name', 'Canceled Row')->value('fulfillment_placement'));
        $this->assertSame('to_pack', DB::table('order_statuses')->where('name', 'Processing Row')->value('fulfillment_placement'));

        // The old column is gone.
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('order_statuses', 'fulfillment_stage'));
    }

    /**
     * The tab rework's own conversion: an `in_transit`-placed status becomes
     * `shipping`, and every other value is carried across untouched.
     */
    public function test_migration_renames_in_transit_placement_to_shipping(): void
    {
        $this->rollbackTo(self::SHIPPING_MIGRATION);

        Store::create(['id' => 1, 'name' => 'Test Store', 'code' => 'test']);

        $now = now();
        DB::table('order_statuses')->insert([
            ['store_id' => 1, 'name' => 'Shipped Row', 'sort_order' => 1, 'fulfillment_placement' => 'in_transit', 'created_at' => $now, 'updated_at' => $now],
            ['store_id' => 1, 'name' => 'Handover Row', 'sort_order' => 2, 'fulfillment_placement' => 'to_handover', 'created_at' => $now, 'updated_at' => $now],
            ['store_id' => 1, 'name' => 'Delivered Row', 'sort_order' => 3, 'fulfillment_placement' => 'delivered', 'created_at' => $now, 'updated_at' => $now],
            ['store_id' => 1, 'name' => 'Failed Row', 'sort_order' => 4, 'fulfillment_placement' => 'failed', 'created_at' => $now, 'updated_at' => $now],
        ]);

        Artisan::call('migrate');

        $this->assertSame('shipping', DB::table('order_statuses')->where('name', 'Shipped Row')->value('fulfillment_placement'));
        $this->assertSame('to_handover', DB::table('order_statuses')->where('name', 'Handover Row')->value('fulfillment_placement'));
        $this->assertSame('delivered', DB::table('order_statuses')->where('name', 'Delivered Row')->value('fulfillment_placement'));
        $this->assertSame('failed', DB::table('order_statuses')->where('name', 'Failed Row')->value('fulfillment_placement'));

        // The column kept its name, and nothing was left behind.
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('order_statuses', 'fulfillment_placement'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('order_statuses', 'fulfillment_placement_next'));
    }

    public function test_placement_column_is_the_eight_value_enum_defaulting_to_to_pack(): void
    {
        Store::create(['id' => 1, 'name' => 'Test Store', 'code' => 'test']);

        $id = DB::table('order_statuses')->insertGetId([
            'store_id' => 1, 'name' => 'No Override', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame('to_pack', DB::table('order_statuses')->where('id', $id)->value('fulfillment_placement'));

        foreach (self::PLACEMENT_VALUES as $value) {
            DB::table('order_statuses')->where('id', $id)->update(['fulfillment_placement' => $value]);
            $this->assertSame($value, DB::table('order_statuses')->where('id', $id)->value('fulfillment_placement'));
        }
    }

    /**
     * The retired value really is gone: the enum's own constraint refuses it
     * on the engine the suite runs on, so nothing in the codebase can quietly
     * keep writing the old spelling.
     */
    public function test_the_retired_in_transit_value_is_no_longer_accepted(): void
    {
        Store::create(['id' => 1, 'name' => 'Test Store', 'code' => 'test']);

        $id = DB::table('order_statuses')->insertGetId([
            'store_id' => 1, 'name' => 'Legacy Value', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('order_statuses')->where('id', $id)->update(['fulfillment_placement' => 'in_transit']);
    }
}
