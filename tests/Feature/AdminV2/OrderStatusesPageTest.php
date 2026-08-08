<?php

namespace Tests\Feature\AdminV2;

use App\Models\Order;
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

    private function makeOrder(int $orderStatusId): Order
    {
        return Order::create([
            'store_id' => 1,
            'order_status_id' => $orderStatusId,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'shipping_first_name' => 'Jane',
            'shipping_last_name' => 'Doe',
            'shipping_address_1' => '123 Mango St',
            'shipping_city' => 'Cebu City',
            'shipping_state' => 'Cebu',
            'payment_method' => 'bank_transfer',
            'subtotal' => 1000,
            'shipping_cost' => 100,
            'total' => 1100,
        ]);
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

    public function test_fulfillment_placement_column_defaults_to_to_pack(): void
    {
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Some Status', 'sort_order' => 1]);

        $this->assertSame('to_pack', $status->fresh()->fulfillment_placement);
    }

    public function test_page_shows_all_eight_placement_options_and_badges(): void
    {
        $this->actingAs(User::factory()->create());
        OrderStatus::create(['store_id' => 1, 'name' => 'Unpaid', 'fulfillment_placement' => 'unpaid', 'sort_order' => 1]);
        OrderStatus::create(['store_id' => 1, 'name' => 'Canceled', 'fulfillment_placement' => 'cancelled', 'sort_order' => 2]);
        OrderStatus::create(['store_id' => 1, 'name' => 'Processing', 'fulfillment_placement' => 'to_pack', 'sort_order' => 3]);

        $response = $this->get(route('admin.order-statuses.index'));
        $response->assertOk()
            ->assertSee('fulfillment_placement', false)
            ->assertSee('Unpaid')
            ->assertSee('Cancelled')
            ->assertSee('To Pack')
            ->assertSee('To Handover')
            ->assertSee('Shipping')
            ->assertSee('Lost & Damaged')
            ->assertSee('Delivered')
            ->assertSee('Failed')
            // The retired spelling is gone from the page entirely.
            ->assertDontSee('In Transit');

        // Eight <option> values render inside the row selector.
        foreach (['unpaid', 'to_pack', 'to_handover', 'shipping', 'failed', 'lost_damaged', 'delivered', 'cancelled'] as $value) {
            $response->assertSee('value="' . $value . '"', false);
        }
    }

    public function test_page_shows_help_text_for_each_placement(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.order-statuses.index'))
            ->assertOk()
            ->assertSee('waiting on payment')
            ->assertSee('awaiting packing')
            ->assertSee('awaiting courier pickup')
            ->assertSee('with the courier en route')
            ->assertSee('parcel lost or damaged')
            ->assertSee('delivered to the customer')
            ->assertSee('delivery failed or is returning')
            ->assertSee('will not be fulfilled');
    }

    public function test_page_shows_live_order_count_per_status_with_a_single_grouped_query(): void
    {
        $this->actingAs(User::factory()->create());
        $processing = OrderStatus::create(['store_id' => 1, 'name' => 'Processing', 'fulfillment_placement' => 'to_pack', 'sort_order' => 3]);
        $delivered = OrderStatus::create(['store_id' => 1, 'name' => 'Delivered', 'fulfillment_placement' => 'delivered', 'sort_order' => 4]);

        $this->makeOrder($processing->id);
        $this->makeOrder($processing->id);
        $this->makeOrder($delivered->id);

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $response = $this->get(route('admin.order-statuses.index'));
        $queries = \Illuminate\Support\Facades\DB::getQueryLog();
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $countQueries = array_filter($queries, fn ($q) => stripos($q['query'], 'group by') !== false && str_contains($q['query'], 'order_status_id'));
        $this->assertCount(1, $countQueries, 'Expected exactly one grouped count query, not N+1.');

        $response->assertOk()->assertSee('2')->assertSee('1');
    }

    public function test_fulfillment_placement_save_round_trip(): void
    {
        $this->actingAs(User::factory()->create());
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Backordered', 'fulfillment_placement' => 'to_pack', 'sort_order' => 5]);

        $this->put(route('admin.order-statuses.update', $status), [
            'name' => 'Backordered', 'fulfillment_placement' => 'unpaid', 'sort_order' => 5,
        ])->assertRedirect();

        $this->assertSame('unpaid', $status->fresh()->fulfillment_placement);
    }

    public function test_placement_save_round_trip_covers_the_new_values(): void
    {
        $this->actingAs(User::factory()->create());
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Backordered', 'fulfillment_placement' => 'to_pack', 'sort_order' => 5]);

        foreach (['to_handover', 'shipping', 'lost_damaged', 'delivered', 'failed', 'cancelled'] as $value) {
            $this->put(route('admin.order-statuses.update', $status), [
                'name' => 'Backordered', 'fulfillment_placement' => $value, 'sort_order' => 5,
            ])->assertRedirect();

            $this->assertSame($value, $status->fresh()->fulfillment_placement);
        }
    }

    public function test_unknown_fulfillment_placement_value_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Backordered', 'fulfillment_placement' => 'to_pack', 'sort_order' => 5]);

        $this->put(route('admin.order-statuses.update', $status), [
            'name' => 'Backordered', 'fulfillment_placement' => 'bogus', 'sort_order' => 5,
        ])->assertSessionHasErrors('fulfillment_placement');

        $this->assertSame('to_pack', $status->fresh()->fulfillment_placement);

        $this->post(route('admin.order-statuses.store'), [
            'name' => 'Another Status', 'fulfillment_placement' => 'bogus', 'sort_order' => 6,
        ])->assertSessionHasErrors('fulfillment_placement');

        $this->assertDatabaseMissing('order_statuses', ['name' => 'Another Status']);
    }

    public function test_ready_is_no_longer_a_valid_placement_value(): void
    {
        $this->actingAs(User::factory()->create());
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Backordered', 'fulfillment_placement' => 'to_pack', 'sort_order' => 5]);

        $this->put(route('admin.order-statuses.update', $status), [
            'name' => 'Backordered', 'fulfillment_placement' => 'ready', 'sort_order' => 5,
        ])->assertSessionHasErrors('fulfillment_placement');
    }
}
