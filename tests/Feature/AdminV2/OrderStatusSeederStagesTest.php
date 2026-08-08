<?php

namespace Tests\Feature\AdminV2;

use App\Models\OrderStatus;
use App\Models\Store;
use Database\Seeders\OrderStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The baseline order statuses (owner ruling, 2026-08-09): OpenCart's own
 * fourteen defaults plus Delivered and Unpaid, and NOTHING else - no
 * fulfillment-shaped statuses, and no placement data of any kind, because the
 * mapping is owned by the courier-fulfillment module now.
 */
class OrderStatusSeederStagesTest extends TestCase
{
    use RefreshDatabase;

    /** Exactly what a fresh install gets. */
    private const SEEDED = [
        'Pending', 'Processing', 'Shipped', 'Complete', 'Canceled', 'Denied',
        'Canceled Reversal', 'Failed', 'Refunded', 'Reversed', 'Chargeback',
        'Expired', 'Processed', 'Voided', 'Delivered', 'Unpaid',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Store::create(['id' => 1, 'name' => 'Test Store', 'code' => 'test']);
    }

    public function test_seeder_creates_exactly_the_sixteen_baseline_statuses(): void
    {
        (new OrderStatusSeeder())->run();

        $names = OrderStatus::where('store_id', 1)->pluck('name')->sort()->values()->all();
        $expected = collect(self::SEEDED)->sort()->values()->all();

        $this->assertSame($expected, $names);
        $this->assertCount(16, $names);
    }

    /** Pending is the one default, exactly as OpenCart ships it. */
    public function test_pending_is_the_only_default(): void
    {
        (new OrderStatusSeeder())->run();

        $this->assertSame(
            ['Pending'],
            OrderStatus::where('store_id', 1)->where('is_default', true)->pluck('name')->all(),
        );
    }

    /**
     * "Payment Received" was a Venta invention, not an OpenCart default; the
     * owner removed it. The fulfillment-shaped names were never seeded either
     * (the module is configured deliberately at setup).
     */
    public function test_seeder_creates_no_payment_received_and_no_fulfillment_statuses(): void
    {
        (new OrderStatusSeeder())->run();

        foreach (['Payment Received', 'Order Being Shipped', 'Done Pickup', 'Start Pickup', 'In Transit to Manila'] as $name) {
            $this->assertFalse(
                OrderStatus::where('store_id', 1)->where('name', $name)->exists(),
                "The seeder must not create [{$name}].",
            );
        }
    }

    /**
     * No placement data at all: core has no column to hold it, and the
     * seeder writes nothing into the module's table either (which does not
     * even exist until the module is installed).
     */
    public function test_seeder_writes_no_placement_data(): void
    {
        (new OrderStatusSeeder())->run();

        $this->assertFalse(Schema::hasColumn('order_statuses', 'fulfillment_placement'));
        $this->assertFalse(Schema::hasTable('courier_fulfillment_status_placements'));
    }

    /** The name-list machinery left core with the placements it used to seed. */
    public function test_the_seeder_no_longer_carries_placement_name_lists(): void
    {
        $this->assertFalse(method_exists(OrderStatusSeeder::class, 'placementFor'));
    }
}
