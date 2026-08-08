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

    public function test_seeder_assigns_unpaid_stage_to_named_statuses(): void
    {
        (new OrderStatusSeeder())->run();

        foreach (['Unpaid', 'Pending'] as $name) {
            $this->assertSame('unpaid', OrderStatus::where('store_id', 1)->where('name', $name)->value('fulfillment_stage'), "$name should be unpaid");
        }
    }

    public function test_seeder_assigns_cancelled_stage_to_named_statuses(): void
    {
        (new OrderStatusSeeder())->run();

        foreach (['Canceled', 'Denied', 'Failed', 'Refunded', 'Expired'] as $name) {
            $this->assertSame('cancelled', OrderStatus::where('store_id', 1)->where('name', $name)->value('fulfillment_stage'), "$name should be cancelled");
        }
    }

    public function test_seeder_defaults_unlisted_statuses_to_ready(): void
    {
        (new OrderStatusSeeder())->run();

        foreach (['Processing', 'Shipped', 'Complete', 'Payment Received', 'Delivered'] as $name) {
            $this->assertSame('ready', OrderStatus::where('store_id', 1)->where('name', $name)->value('fulfillment_stage'), "$name should be ready");
        }
    }

    public function test_stage_for_is_case_insensitive(): void
    {
        $this->assertSame('unpaid', OrderStatusSeeder::stageFor('unpaid'));
        $this->assertSame('unpaid', OrderStatusSeeder::stageFor('UNPAID'));
        $this->assertSame('unpaid', OrderStatusSeeder::stageFor('Quote Ready For Payment'));
        $this->assertSame('cancelled', OrderStatusSeeder::stageFor('chargeback'));
        $this->assertSame('cancelled', OrderStatusSeeder::stageFor('VOIDED'));
        $this->assertSame('ready', OrderStatusSeeder::stageFor('Something Else'));
    }
}
