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

    /**
     * The stock action is the SELECT and nothing else: the badge that
     * repeated the selected value beside it, and the legend card that existed
     * only to explain that badge, are both gone (owner's UI copy rule,
     * 2026-08-09 - no text that restates a control).
     */
    public function test_stock_action_is_a_select_with_no_badge_repeating_it(): void
    {
        $this->actingAs(User::factory()->create());
        OrderStatus::create(['store_id' => 1, 'name' => 'Processing', 'stock_action' => 'decrement', 'sort_order' => 2]);
        OrderStatus::create(['store_id' => 1, 'name' => 'Refunded', 'stock_action' => 'increment', 'sort_order' => 11]);

        $this->get(route('admin.order-statuses.index'))
            ->assertOk()
            ->assertSee('v-admin', false)
            ->assertSee('Processing')->assertSee('Refunded')
            ->assertSee('name="stock_action"', false)
            // The badge text and its legend card are both gone; the select
            // beside them is untouched.
            ->assertDontSee('Decrements')
            ->assertDontSee('Increments')
            ->assertDontSee('stock committed')
            ->assertDontSee('stock returned')
            ->assertDontSee('Stock actions');
    }

    public function test_create_and_update_round_trip_unchanged(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.order-statuses.store'), [
            'name' => 'Quality Check', 'stock_action' => 'decrement', 'sort_order' => 4,
        ])->assertRedirect();
        $this->assertDatabaseHas('order_statuses', ['name' => 'Quality Check', 'stock_action' => 'decrement']);
    }

    /**
     * Fulfillment placement left this page with the column it lived on (owner
     * ruling, 2026-08-09): the select, the badge and the legend card are all
     * gone, and so is the per-status order count that existed only to show
     * the scale of a placement change. Core is plain CRUD again.
     */
    public function test_page_offers_no_fulfillment_placement_at_all(): void
    {
        $this->actingAs(User::factory()->create());
        OrderStatus::create(['store_id' => 1, 'name' => 'Unpaid', 'sort_order' => 1]);
        OrderStatus::create(['store_id' => 1, 'name' => 'Shipped', 'sort_order' => 4]);

        $response = $this->get(route('admin.order-statuses.index'));
        $response->assertOk()
            ->assertSee('Unpaid')
            ->assertSee('Shipped')
            ->assertDontSee('fulfillment_placement', false)
            ->assertDontSee('Fulfillment placement')
            ->assertDontSee('To Handover')
            ->assertDontSee('Lost & Damaged', false);

        foreach (['to_pack', 'to_handover', 'lost_damaged'] as $value) {
            $response->assertDontSee('value="' . $value . '"', false);
        }
    }

    /** The order count column went with the placement UI it served. */
    public function test_page_no_longer_renders_a_per_status_order_count(): void
    {
        $this->actingAs(User::factory()->create());
        $processing = OrderStatus::create(['store_id' => 1, 'name' => 'Processing', 'sort_order' => 3]);
        $this->makeOrder($processing->id);

        $response = $this->get(route('admin.order-statuses.index'));
        $response->assertOk();

        $this->assertArrayNotHasKey('orderCounts', $response->original->getData());
    }

    /**
     * The endpoint REFUSES a placement field rather than silently dropping
     * it: the mapping is module data now, and a stale form (or a script that
     * predates the move) must be told so instead of appearing to save.
     */
    public function test_a_placement_field_is_rejected_by_both_endpoints(): void
    {
        $this->actingAs(User::factory()->create());
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Backordered', 'sort_order' => 5]);

        $this->put(route('admin.order-statuses.update', $status), [
            'name' => 'Backordered', 'fulfillment_placement' => 'unpaid', 'sort_order' => 5,
        ])->assertSessionHasErrors('fulfillment_placement');

        $this->post(route('admin.order-statuses.store'), [
            'name' => 'Another Status', 'fulfillment_placement' => 'unpaid', 'sort_order' => 6,
        ])->assertSessionHasErrors('fulfillment_placement');

        $this->assertDatabaseMissing('order_statuses', ['name' => 'Another Status']);
    }

    /** Ordinary CRUD is untouched by the removal. */
    public function test_name_and_sort_order_still_round_trip(): void
    {
        $this->actingAs(User::factory()->create());
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Backordered', 'sort_order' => 5]);

        $this->put(route('admin.order-statuses.update', $status), [
            'name' => 'Back Ordered', 'sort_order' => 7,
        ])->assertRedirect();

        $status->refresh();
        $this->assertSame('Back Ordered', $status->name);
        $this->assertSame(7, (int) $status->sort_order);
    }

    /**
     * The page lists one store's statuses only, but route-model binding
     * resolves by id alone: without a scope check, another store's status was
     * writable and deletable through these endpoints.
     */
    public function test_another_stores_status_cannot_be_updated_or_deleted(): void
    {
        $this->actingAs(User::factory()->create());
        Store::create(['id' => 2, 'name' => 'Other Store', 'code' => 'other']);
        $foreign = OrderStatus::create([
            'store_id' => 2, 'name' => 'Foreign Status', 'sort_order' => 1,
        ]);

        $this->put(route('admin.order-statuses.update', $foreign), [
            'name' => 'Hijacked', 'sort_order' => 1,
        ])->assertNotFound();

        $this->delete(route('admin.order-statuses.destroy', $foreign))->assertNotFound();

        $foreign->refresh();
        $this->assertSame('Foreign Status', $foreign->name);
        $this->assertDatabaseHas('order_statuses', ['id' => $foreign->id]);
    }

}
