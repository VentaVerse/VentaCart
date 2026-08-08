<?php

namespace Tests\Feature;

use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fulfillment Placement Task 1: the migration that converts
 * order_statuses.fulfillment_stage (unpaid|ready|cancelled) into
 * fulfillment_placement (unpaid|to_pack|to_handover|in_transit|delivered|
 * failed|cancelled). RefreshDatabase already runs every migration
 * (including this one) before each test, so old-style rows can't exist in
 * the normal test flow — this test rolls back exactly the placement
 * migration, seeds old-shape rows by hand, then re-runs it to prove the
 * three known-value conversions (unpaid -> unpaid, cancelled -> cancelled,
 * ready -> to_pack) actually happen, on the SAME sqlite :memory: engine
 * the rest of the suite runs on.
 */
class FulfillmentPlacementMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_converts_the_three_known_stage_values(): void
    {
        Artisan::call('migrate:rollback', ['--step' => 1]);

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

    public function test_placement_column_is_the_seven_value_enum_defaulting_to_to_pack(): void
    {
        Store::create(['id' => 1, 'name' => 'Test Store', 'code' => 'test']);

        $id = DB::table('order_statuses')->insertGetId([
            'store_id' => 1, 'name' => 'No Override', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame('to_pack', DB::table('order_statuses')->where('id', $id)->value('fulfillment_placement'));

        foreach (['unpaid', 'to_pack', 'to_handover', 'in_transit', 'delivered', 'failed', 'cancelled'] as $value) {
            DB::table('order_statuses')->where('id', $id)->update(['fulfillment_placement' => $value]);
            $this->assertSame($value, DB::table('order_statuses')->where('id', $id)->value('fulfillment_placement'));
        }
    }
}
