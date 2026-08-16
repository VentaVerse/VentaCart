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
     * Stock is ONE checkbox and nothing else (owner ruling, 2026-08-08): a
     * status either deducts or it does not, so the three-way select is gone
     * along with the badge that repeated its value and the legend card that
     * existed only to explain that badge (owner's UI copy rule, 2026-08-09 -
     * no text that restates a control).
     */
    public function test_stock_is_a_single_checkbox_with_no_badge_or_legend(): void
    {
        $this->actingAs(User::factory()->create());
        OrderStatus::create(['store_id' => 1, 'name' => 'Processing', 'deducts_stock' => true, 'sort_order' => 2]);
        OrderStatus::create(['store_id' => 1, 'name' => 'Refunded', 'deducts_stock' => false, 'sort_order' => 11]);

        // Migrated to the v3 settings shell.
        $this->get(route('admin.order-statuses.index'))
            ->assertOk()
            ->assertSee('class="v3"', false)
            ->assertSee('Processing')->assertSee('Refunded')
            ->assertSee('Deducts stock')
            ->assertSee('name="deducts_stock" value="1"', false)
            // Nothing left of the retired three-way vocabulary, on the page or
            // in the markup.
            ->assertDontSee('stock_action', false)
            ->assertDontSee('Decrement')
            ->assertDontSee('Increment')
            ->assertDontSee('Stock action')
            // The retired legend card and its two explanatory lines.
            ->assertDontSee('Stock actions')
            ->assertDontSee('stock committed')
            ->assertDontSee('no inventory effect');
    }

    /** The checked state reflects the stored flag, per row. */
    public function test_the_checkbox_reflects_each_statuss_stored_flag(): void
    {
        $this->actingAs(User::factory()->create());
        OrderStatus::create(['store_id' => 1, 'name' => 'Processing', 'deducts_stock' => true, 'sort_order' => 1]);
        OrderStatus::create(['store_id' => 1, 'name' => 'Refunded', 'deducts_stock' => false, 'sort_order' => 2]);

        $content = $this->get(route('admin.order-statuses.index'))->assertOk()->getContent();

        // Three controls: one per row, plus the Add status form's own. Exactly
        // one is checked - the deducting row - and a brand new status starts
        // unchecked.
        $this->assertSame(3, substr_count($content, 'name="deducts_stock" value="1"'));
        $this->assertSame(1, substr_count($content, 'name="deducts_stock" value="1" checked'));
    }

    public function test_create_and_update_round_trip_the_stock_flag(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.order-statuses.store'), [
            'name' => 'Quality Check', 'deducts_stock' => '1', 'sort_order' => 4,
        ])->assertRedirect();
        $this->assertDatabaseHas('order_statuses', ['name' => 'Quality Check', 'deducts_stock' => true]);

        $status = OrderStatus::where('name', 'Quality Check')->firstOrFail();

        // Clearing it saves: the row's hidden 0 companion is what makes an
        // unchecked box post at all.
        $this->put(route('admin.order-statuses.update', $status), [
            'name' => 'Quality Check', 'deducts_stock' => '0', 'sort_order' => 4,
        ])->assertRedirect();
        $this->assertFalse((bool) $status->fresh()->deducts_stock);

        $this->put(route('admin.order-statuses.update', $status), [
            'name' => 'Quality Check', 'deducts_stock' => '1', 'sort_order' => 4,
        ])->assertRedirect();
        $this->assertTrue((bool) $status->fresh()->deducts_stock);
    }

    /**
     * The flag is two-valued: anything that is not a boolean is refused rather
     * than quietly coerced to "does not deduct", which would silently hand a
     * store's stock back.
     */
    public function test_a_non_boolean_stock_flag_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Processing', 'deducts_stock' => true, 'sort_order' => 1]);

        $this->put(route('admin.order-statuses.update', $status), [
            'name' => 'Processing', 'deducts_stock' => 'increment', 'sort_order' => 1,
        ])->assertSessionHasErrors('deducts_stock');

        $this->assertTrue((bool) $status->fresh()->deducts_stock);

        $this->post(route('admin.order-statuses.store'), [
            'name' => 'Bogus Status', 'deducts_stock' => 'decrement', 'sort_order' => 2,
        ])->assertSessionHasErrors('deducts_stock');

        $this->assertDatabaseMissing('order_statuses', ['name' => 'Bogus Status']);
    }

    /** A status created without the flag does not deduct. */
    public function test_a_new_status_defaults_to_not_deducting(): void
    {
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Some Status', 'sort_order' => 1]);

        $this->assertFalse((bool) $status->fresh()->deducts_stock);
    }

    // -----------------------------------------------------------------
    // Counts as revenue (owner ruling, 2026-08-09)
    // -----------------------------------------------------------------

    /**
     * Revenue is a core property of a status, edited beside stock and in the
     * same idiom: one checkbox, one hidden 0 companion, one tooltip carrying
     * the single non-obvious consequence. No second control pattern.
     */
    public function test_revenue_is_a_checkbox_beside_deducts_stock(): void
    {
        $this->actingAs(User::factory()->create());
        OrderStatus::create(['store_id' => 1, 'name' => 'Complete', 'deducts_stock' => true, 'counts_as_revenue' => true, 'sort_order' => 1]);
        OrderStatus::create(['store_id' => 1, 'name' => 'Refunded', 'deducts_stock' => false, 'counts_as_revenue' => false, 'sort_order' => 2]);

        $response = $this->get(route('admin.order-statuses.index'))->assertOk();

        $content = $response->getContent();

        $response->assertSee('Counts as revenue')
            ->assertSee('name="counts_as_revenue" value="1"', false)
            ->assertSee('name="counts_as_revenue" value="0"', false)
            // The one consequence a merchant cannot infer from the label.
            ->assertSee('Orders with no status never count', false);

        // Two rows plus the add form, exactly one of them ticked.
        $this->assertSame(3, substr_count($content, 'name="counts_as_revenue" value="1"'));
        $this->assertSame(1, substr_count($content, 'name="counts_as_revenue" value="1" checked'));

        // The stock column is untouched and still sits beside it.
        $response->assertSee('Deducts stock');
    }

    /** Every row's checkbox is named for its own status, for screen readers. */
    public function test_each_revenue_checkbox_is_labelled_with_its_status(): void
    {
        $this->actingAs(User::factory()->create());
        OrderStatus::create(['store_id' => 1, 'name' => 'Complete', 'counts_as_revenue' => true, 'sort_order' => 1]);

        $this->get(route('admin.order-statuses.index'))
            ->assertOk()
            ->assertSee('aria-label="Complete counts as revenue"', false);
    }

    public function test_create_and_update_round_trip_the_revenue_flag(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.order-statuses.store'), [
            'name' => 'Quality Check', 'counts_as_revenue' => '1', 'sort_order' => 4,
        ])->assertRedirect();
        $this->assertDatabaseHas('order_statuses', ['name' => 'Quality Check', 'counts_as_revenue' => true]);

        $status = OrderStatus::where('name', 'Quality Check')->firstOrFail();

        // Clearing it saves: the hidden 0 companion is what makes an unchecked
        // box post at all.
        $this->put(route('admin.order-statuses.update', $status), [
            'name' => 'Quality Check', 'counts_as_revenue' => '0', 'sort_order' => 4,
        ])->assertRedirect();
        $this->assertFalse((bool) $status->fresh()->counts_as_revenue);

        $this->put(route('admin.order-statuses.update', $status), [
            'name' => 'Quality Check', 'counts_as_revenue' => '1', 'sort_order' => 4,
        ])->assertRedirect();
        $this->assertTrue((bool) $status->fresh()->counts_as_revenue);
    }

    /**
     * Two-valued, validated the way the stock flag is: anything else is
     * refused rather than coerced to false, which would silently drop a
     * store's revenue figure.
     */
    public function test_a_non_boolean_revenue_flag_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Complete', 'counts_as_revenue' => true, 'sort_order' => 1]);

        $this->put(route('admin.order-statuses.update', $status), [
            'name' => 'Complete', 'counts_as_revenue' => 'yes please', 'sort_order' => 1,
        ])->assertSessionHasErrors('counts_as_revenue');

        $this->assertTrue((bool) $status->fresh()->counts_as_revenue);

        $this->post(route('admin.order-statuses.store'), [
            'name' => 'Bogus Status', 'counts_as_revenue' => 'sometimes', 'sort_order' => 2,
        ])->assertSessionHasErrors('counts_as_revenue');

        $this->assertDatabaseMissing('order_statuses', ['name' => 'Bogus Status']);
    }

    /**
     * The two properties are independent once set. They are derived to agree
     * on migrate and then part company whenever the merchant says so: a lost
     * parcel took the goods out and earned nothing.
     */
    public function test_revenue_and_stock_move_independently(): void
    {
        $this->actingAs(User::factory()->create());
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Lost', 'deducts_stock' => true, 'counts_as_revenue' => true, 'sort_order' => 1]);

        $this->put(route('admin.order-statuses.update', $status), [
            'name' => 'Lost', 'deducts_stock' => '1', 'counts_as_revenue' => '0', 'sort_order' => 1,
        ])->assertRedirect();

        $fresh = $status->fresh();
        $this->assertTrue((bool) $fresh->deducts_stock);
        $this->assertFalse((bool) $fresh->counts_as_revenue);
    }

    /** A status created without the flag is not revenue. */
    public function test_a_new_status_defaults_to_not_counting_as_revenue(): void
    {
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Some Status', 'sort_order' => 1]);

        $this->assertFalse((bool) $status->fresh()->counts_as_revenue);
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
