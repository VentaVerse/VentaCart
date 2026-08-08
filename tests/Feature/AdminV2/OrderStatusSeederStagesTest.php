<?php

namespace Tests\Feature\AdminV2;

use App\Models\OrderStatus;
use App\Models\Store;
use Database\Seeders\OrderStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusSeederStagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Store::create(['id' => 1, 'name' => 'Test Store', 'code' => 'test']);
    }

    public function test_seeder_assigns_unpaid_placement_to_named_statuses(): void
    {
        (new OrderStatusSeeder())->run();

        foreach (['Unpaid', 'Pending'] as $name) {
            $this->assertSame('unpaid', OrderStatus::where('store_id', 1)->where('name', $name)->value('fulfillment_placement'), "$name should be unpaid");
        }
    }

    public function test_seeder_assigns_cancelled_placement_to_named_statuses(): void
    {
        (new OrderStatusSeeder())->run();

        foreach (['Canceled', 'Denied', 'Failed', 'Refunded', 'Expired'] as $name) {
            $this->assertSame('cancelled', OrderStatus::where('store_id', 1)->where('name', $name)->value('fulfillment_placement'), "$name should be cancelled");
        }
    }

    public function test_seeder_assigns_delivered_placement_to_named_statuses(): void
    {
        (new OrderStatusSeeder())->run();

        $this->assertSame('delivered', OrderStatus::where('store_id', 1)->where('name', 'Delivered')->value('fulfillment_placement'));
    }

    public function test_seeder_defaults_unlisted_statuses_to_to_pack(): void
    {
        (new OrderStatusSeeder())->run();

        foreach (['Processing', 'Payment Received'] as $name) {
            $this->assertSame('to_pack', OrderStatus::where('store_id', 1)->where('name', $name)->value('fulfillment_placement'), "$name should be to_pack");
        }
    }

    public function test_seeder_assigns_shipping_placement_to_shipped(): void
    {
        (new OrderStatusSeeder())->run();

        $this->assertSame('shipping', OrderStatus::where('store_id', 1)->where('name', 'Shipped')->value('fulfillment_placement'));
    }

    /**
     * SETUP POLICY (owner ruling, 2026-08-09): the seeder ships NO
     * fulfillment-specific statuses. "Order Being Shipped" was seeded by an
     * earlier task so booking would work out of the box; the owner reverted
     * that, because the fulfillment module is configured deliberately at
     * setup. A fresh install therefore has nothing placed under To Handover
     * (booking refuses, and the settings hub says so with a setup notice).
     */
    public function test_seeder_seeds_no_fulfillment_specific_statuses(): void
    {
        (new OrderStatusSeeder())->run();

        $this->assertFalse(
            OrderStatus::where('store_id', 1)->where('name', 'Order Being Shipped')->exists(),
            'The seeder must not create fulfillment-specific statuses.',
        );
        $this->assertFalse(
            OrderStatus::where('store_id', 1)->where('fulfillment_placement', 'to_handover')->exists(),
            'A fresh install has no To Handover-placed status until the merchant places one.',
        );
    }

    /**
     * placementFor() keeps its name lists regardless: it classifies whatever
     * names DO exist (a merchant's own status, or one an OpenCart-era store
     * brings with it), which is a different job from seeding them.
     */
    public function test_placement_for_still_classifies_unseeded_fulfillment_names(): void
    {
        $this->assertSame('to_handover', OrderStatusSeeder::placementFor('Order Being Shipped'));
        $this->assertSame('shipping', OrderStatusSeeder::placementFor('Shipped'));
    }

    public function test_placement_for_is_case_insensitive(): void
    {
        $this->assertSame('unpaid', OrderStatusSeeder::placementFor('unpaid'));
        $this->assertSame('unpaid', OrderStatusSeeder::placementFor('UNPAID'));
        $this->assertSame('unpaid', OrderStatusSeeder::placementFor('Quote Ready For Payment'));
        $this->assertSame('cancelled', OrderStatusSeeder::placementFor('chargeback'));
        $this->assertSame('cancelled', OrderStatusSeeder::placementFor('VOIDED'));
        $this->assertSame('delivered', OrderStatusSeeder::placementFor('delivered'));
        $this->assertSame('delivered', OrderStatusSeeder::placementFor('COMPLETE'));
        $this->assertSame('shipping', OrderStatusSeeder::placementFor('shipped'));
        $this->assertSame('shipping', OrderStatusSeeder::placementFor('In Transit To Manila'));
        $this->assertSame('to_handover', OrderStatusSeeder::placementFor('order being shipped'));
        $this->assertSame('to_handover', OrderStatusSeeder::placementFor('DONE PICKUP'));
        $this->assertSame('to_handover', OrderStatusSeeder::placementFor('Start Pickup'));
        $this->assertSame('to_pack', OrderStatusSeeder::placementFor('Something Else'));
    }
}
