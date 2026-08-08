<?php

namespace Tests\Feature\AdminV2;

use App\Models\OrderStatus;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusesPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Store::create(['id' => 1, 'name' => 'Test Store', 'code' => 'test']);
    }

    public function test_page_shows_stock_action_badges(): void
    {
        $this->actingAs(User::factory()->create());
        OrderStatus::create(['store_id' => 1, 'name' => 'Processing', 'stock_action' => 'decrement', 'sort_order' => 2]);
        OrderStatus::create(['store_id' => 1, 'name' => 'Refunded', 'stock_action' => 'increment', 'sort_order' => 11]);

        $this->get(route('admin.order-statuses.index'))
            ->assertOk()
            ->assertSee('v-admin', false)
            ->assertSee('Decrements')->assertSee('Increments')
            ->assertSee('Processing')->assertSee('Refunded');
    }

    public function test_create_and_update_round_trip_unchanged(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.order-statuses.store'), [
            'name' => 'Quality Check', 'stock_action' => 'decrement', 'sort_order' => 4,
        ])->assertRedirect();
        $this->assertDatabaseHas('order_statuses', ['name' => 'Quality Check', 'stock_action' => 'decrement']);
    }

    public function test_fulfillment_stage_column_defaults_to_ready(): void
    {
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Some Status', 'sort_order' => 1]);

        $this->assertSame('ready', $status->fresh()->fulfillment_stage);
    }

    public function test_page_shows_fulfillment_stage_selector_and_badges(): void
    {
        $this->actingAs(User::factory()->create());
        OrderStatus::create(['store_id' => 1, 'name' => 'Unpaid', 'fulfillment_stage' => 'unpaid', 'sort_order' => 1]);
        OrderStatus::create(['store_id' => 1, 'name' => 'Canceled', 'fulfillment_stage' => 'cancelled', 'sort_order' => 2]);
        OrderStatus::create(['store_id' => 1, 'name' => 'Processing', 'fulfillment_stage' => 'ready', 'sort_order' => 3]);

        $this->get(route('admin.order-statuses.index'))
            ->assertOk()
            ->assertSee('fulfillment_stage', false)
            ->assertSee('Unpaid')
            ->assertSee('Cancelled')
            ->assertSee('Ready');
    }

    public function test_fulfillment_stage_save_round_trip(): void
    {
        $this->actingAs(User::factory()->create());
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Backordered', 'fulfillment_stage' => 'ready', 'sort_order' => 5]);

        $this->put(route('admin.order-statuses.update', $status), [
            'name' => 'Backordered', 'fulfillment_stage' => 'unpaid', 'sort_order' => 5,
        ])->assertRedirect();

        $this->assertSame('unpaid', $status->fresh()->fulfillment_stage);
    }

    public function test_unknown_fulfillment_stage_value_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Backordered', 'fulfillment_stage' => 'ready', 'sort_order' => 5]);

        $this->put(route('admin.order-statuses.update', $status), [
            'name' => 'Backordered', 'fulfillment_stage' => 'bogus', 'sort_order' => 5,
        ])->assertSessionHasErrors('fulfillment_stage');

        $this->assertSame('ready', $status->fresh()->fulfillment_stage);

        $this->post(route('admin.order-statuses.store'), [
            'name' => 'Another Status', 'fulfillment_stage' => 'bogus', 'sort_order' => 6,
        ])->assertSessionHasErrors('fulfillment_stage');

        $this->assertDatabaseMissing('order_statuses', ['name' => 'Another Status']);
    }
}
