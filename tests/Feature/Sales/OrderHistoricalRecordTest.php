<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\OrderProduct;
use App\Models\OrderStatus;
use App\Models\ShippingCourier;
use App\Models\Store;
use App\Models\User;
use App\Services\OrderStatusChanger;
use App\Support\NotifyChoice;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * An order is an immutable historical record.
 *
 * Every value the order pages display must be a value the order OWNS. The
 * foreign keys stay, nullable, as convenience links for navigation; nothing
 * rendered resolves through a relationship. So deleting a status, a customer,
 * a staff user, a courier or a product may remove the LINK, but can never
 * remove what the order says about itself.
 *
 * Images are the one accepted exception: the order owns the path, so if the
 * FILE is deleted the thumbnail is expected to disappear.
 */
class OrderHistoricalRecordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Store::create(['id' => 1, 'name' => 'Test Store', 'code' => 'test']);
    }

    private function makeStatus(string $name = 'Processing'): OrderStatus
    {
        return OrderStatus::create([
            'store_id' => 1,
            'name' => $name,
            'deducts_stock' => false,
            'sort_order' => 1,
        ]);
    }

    private function order(array $attributes = []): Order
    {
        return Order::create(array_merge([
            'store_id' => 1,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'phone' => '+63 917 555 0101',
            'shipping_first_name' => 'Jane',
            'shipping_last_name' => 'Doe',
            'shipping_address_1' => '123 Mango St',
            'shipping_city' => 'Cebu City',
            'shipping_state' => 'Cebu',
            'billing_first_name' => 'Jane',
            'billing_last_name' => 'Doe',
            'billing_address_1' => '9 Ilang Ilang Ave',
            'billing_city' => 'Mandaue',
            'payment_method' => 'bank_transfer',
            'payment_method_title' => 'Bank Transfer: BPI',
            'subtotal' => 1000,
            'shipping_cost' => 100,
            'total' => 1100,
        ], $attributes));
    }

    // ------------------------------------------------------ deleting a status

    public function test_order_and_history_keep_the_status_name_after_the_status_is_deleted(): void
    {
        $status = $this->makeStatus('Payment Received');
        $order = $this->order(['order_status_id' => $status->id]);
        $history = OrderHistory::create([
            'order_id' => $order->id,
            'order_status_id' => $status->id,
            'comment' => 'Confirmed.',
        ]);

        $status->delete();

        $order->refresh();
        $history->refresh();

        // The link is gone, exactly as ON DELETE SET NULL promises.
        $this->assertNull($order->order_status_id);
        $this->assertNull($history->order_status_id);

        // What the pages print is not.
        $this->assertSame('Payment Received', $order->order_status_name);
        $this->assertSame('Payment Received', $history->order_status_name);
    }

    public function test_admin_order_pages_still_show_a_deleted_status_name(): void
    {
        $this->actingAs(User::factory()->create());

        $status = $this->makeStatus('Order Being Shipped');
        $order = $this->order(['order_status_id' => $status->id]);
        OrderHistory::create([
            'order_id' => $order->id,
            'order_status_id' => $status->id,
            'comment' => 'On its way.',
        ]);

        $status->delete();

        $this->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Order Being Shipped');

        // The index hides ghost orders by default, so the order appearing here
        // at all is the assertion: a deleted status must not reclassify a real
        // order as an abandoned checkout.
        $this->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee('Order Being Shipped')
            ->assertSee(route('admin.orders.show', $order), false);
    }

    /**
     * order_status_id NULL used to mean "abandoned checkout" AND, accidentally,
     * "the status row was deleted". Deleting a status therefore erased real
     * paid orders from the customer's own history. The sentinel now lives on
     * the owned column, which only an order that never reached a status lacks.
     */
    public function test_a_deleted_status_does_not_hide_a_real_order_from_its_customer(): void
    {
        $group = CustomerGroup::create(['store_id' => 1, 'name' => 'Default', 'is_default' => true]);
        $customer = Customer::create([
            'store_id' => 1,
            'customer_group_id' => $group->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'password' => bcrypt('secret'),
        ]);

        $status = $this->makeStatus('Complete');
        $real = $this->order(['customer_id' => $customer->id, 'order_status_id' => $status->id]);
        $ghost = $this->order(['customer_id' => $customer->id, 'order_status_id' => null]);

        $status->delete();

        $visible = Order::visibleToCustomer()->pluck('id');

        $this->assertTrue($visible->contains($real->id), 'A paid order vanished when its status was deleted.');
        $this->assertFalse($visible->contains($ghost->id), 'An abandoned checkout became visible.');

        // The detail gate uses the same sentinel, so the customer keeps access.
        $this->assertNotNull($real->fresh()->order_status_name);
        $this->assertNull($ghost->fresh()->order_status_name);
    }

    // ---------------------------------------------------- deleting a customer

    public function test_order_keeps_customer_details_after_the_customer_is_deleted(): void
    {
        $group = CustomerGroup::create(['store_id' => 1, 'name' => 'Resellers']);
        $customer = Customer::create([
            'store_id' => 1,
            'customer_group_id' => $group->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'password' => bcrypt('secret'),
        ]);

        $order = $this->order([
            'customer_id' => $customer->id,
            'order_status_id' => $this->makeStatus()->id,
        ]);

        $this->assertSame('Resellers', $order->fresh()->customer_group_name);

        $customer->delete();
        $order->refresh();

        $this->assertNull($order->customer_id);
        $this->assertSame('Jane', $order->first_name);
        $this->assertSame('Doe', $order->last_name);
        $this->assertSame('jane@example.com', $order->email);
        $this->assertSame('+63 917 555 0101', $order->phone);
        $this->assertSame('123 Mango St', $order->shipping_address_1);
        $this->assertSame('9 Ilang Ilang Ave', $order->billing_address_1);
        $this->assertSame('Resellers', $order->customer_group_name);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('jane@example.com')
            ->assertSee('123 Mango St')
            ->assertSee('9 Ilang Ilang Ave')
            ->assertSee('Resellers');
    }

    // -------------------------------------------------- deleting a staff user

    public function test_history_still_names_who_made_the_change_after_the_user_is_deleted(): void
    {
        $staff = User::factory()->create(['name' => 'Paolo Reyes']);
        $status = $this->makeStatus('Refunded');
        $order = $this->order(['order_status_id' => $status->id, 'created_by' => $staff->id]);

        $history = OrderHistory::create([
            'order_id' => $order->id,
            'order_status_id' => $status->id,
            'created_by' => $staff->id,
            'comment' => 'Refund issued.',
        ]);

        $staff->delete();

        $history->refresh();
        $order->refresh();

        $this->assertNull($history->created_by);
        $this->assertSame('Paolo Reyes', $history->created_by_name);
        $this->assertSame('Paolo Reyes', $order->created_by_name);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('by Paolo Reyes');
    }

    // ----------------------------------------------------- deleting a courier

    public function test_order_keeps_the_courier_name_and_tracking_after_the_courier_is_deleted(): void
    {
        $courier = ShippingCourier::create([
            'store_id' => 1,
            'name' => 'SPX Express',
            'tracking_url' => 'https://spx.ph/track?n={tracking_number}',
            'is_active' => true,
        ]);

        $order = $this->order([
            'order_status_id' => $this->makeStatus()->id,
            'shipping_courier_id' => $courier->id,
            'tracking_number' => 'SPEPH000000000001',
            'tracking_link' => 'https://spx.ph/track?n=SPEPH000000000001',
        ]);

        $this->assertSame('SPX Express', $order->fresh()->shipping_courier_name);

        $courier->delete();
        $order->refresh();

        $this->assertNull($order->shipping_courier_id);
        $this->assertSame('SPX Express', $order->shipping_courier_name);
        $this->assertSame('SPEPH000000000001', $order->tracking_number);
        // The link was snapshotted as text, so it outlives the URL template.
        $this->assertSame('https://spx.ph/track?n=SPEPH000000000001', $order->tracking_link);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('SPX Express')
            ->assertSee('SPEPH000000000001');
    }

    // ------------------------------------------------------- deleting a store

    public function test_the_database_refuses_to_delete_a_store_that_has_orders(): void
    {
        $this->order(['order_status_id' => $this->makeStatus()->id]);

        $this->expectException(QueryException::class);

        DB::table('stores')->where('id', 1)->delete();
    }

    public function test_orders_survive_the_attempt(): void
    {
        $order = $this->order(['order_status_id' => $this->makeStatus()->id]);

        try {
            DB::table('stores')->where('id', 1)->delete();
        } catch (QueryException) {
            // expected
        }

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertDatabaseHas('stores', ['id' => 1]);
    }

    // ------------------------------------------------------- the write paths

    public function test_a_status_change_stamps_the_status_and_actor_onto_the_history_row(): void
    {
        $staff = User::factory()->create(['name' => 'Ada Santos']);
        $from = $this->makeStatus('Pending');
        $to = OrderStatus::create(['store_id' => 1, 'name' => 'Shipped', 'deducts_stock' => false, 'sort_order' => 2]);

        $order = $this->order(['order_status_id' => $from->id]);

        OrderStatusChanger::apply($order, $to->id, 'Handed to courier.', NotifyChoice::silent(), $staff->id);

        $this->assertDatabaseHas('order_histories', [
            'order_id' => $order->id,
            'order_status_name' => 'Shipped',
            'created_by_name' => 'Ada Santos',
        ]);

        $this->assertSame('Shipped', $order->fresh()->order_status_name);
    }

    /**
     * System-initiated changes (webhooks, polls, checkout confirmation) have no
     * staff user. That is a null actor, not a missing snapshot.
     */
    public function test_a_system_status_change_records_the_status_with_no_actor(): void
    {
        $to = $this->makeStatus('Payment Received');
        $order = $this->order(['order_status_id' => null]);

        OrderStatusChanger::apply($order, $to->id, 'Payment confirmed via HitPay.');

        $this->assertDatabaseHas('order_histories', [
            'order_id' => $order->id,
            'order_status_name' => 'Payment Received',
            'created_by_name' => null,
        ]);
    }

    public function test_admin_order_creation_writes_every_snapshot_column(): void
    {
        $staff = User::factory()->create(['name' => 'Paolo Reyes']);
        $group = CustomerGroup::create(['store_id' => 1, 'name' => 'Salfini']);
        $customer = Customer::create([
            'store_id' => 1,
            'customer_group_id' => $group->id,
            'first_name' => 'Rei',
            'last_name' => 'Chino',
            'email' => 'rei@example.com',
            'password' => bcrypt('secret'),
        ]);
        $courier = ShippingCourier::create(['store_id' => 1, 'name' => 'J&T Express', 'is_active' => true]);
        $status = $this->makeStatus('Pending');

        $this->actingAs($staff)
            ->post(route('admin.orders.store'), [
                'store_id' => 1,
                'customer_id' => $customer->id,
                'order_status_id' => $status->id,
                'first_name' => 'Rei',
                'last_name' => 'Chino',
                'email' => 'rei@example.com',
                'shipping_first_name' => 'Rei',
                'shipping_last_name' => 'Chino',
                'shipping_address_1' => '5 Narra St',
                'shipping_city' => 'Cebu City',
                'shipping_country_id' => $this->countryId(),
                'payment_method' => 'cod',
                'shipping_method' => 'Flat Rate',
                'shipping_courier_id' => $courier->id,
                'items' => [[
                    'name' => 'Tube Screamer',
                    'price' => 1000,
                    'quantity' => 1,
                    'product_id' => null,
                    'product_variant_id' => null,
                ]],
            ])
            ->assertSessionHasNoErrors();

        $order = Order::latest('id')->first();

        $this->assertSame('Pending', $order->order_status_name);
        $this->assertSame('Salfini', $order->customer_group_name);
        $this->assertSame('J&T Express', $order->shipping_courier_name);
        $this->assertSame('Paolo Reyes', $order->created_by_name);

        $this->assertDatabaseHas('order_histories', [
            'order_id' => $order->id,
            'order_status_name' => 'Pending',
            'created_by_name' => 'Paolo Reyes',
        ]);
    }

    /**
     * A snapshot is history, so a later rename of the config row must not
     * rewrite it. Only a genuine status change re-stamps the order.
     */
    public function test_renaming_a_status_does_not_rewrite_past_orders(): void
    {
        $status = $this->makeStatus('Pending');
        $order = $this->order(['order_status_id' => $status->id]);

        $status->update(['name' => 'Awaiting Payment']);

        // Any unrelated save on the order must leave the stamp alone.
        $order->update(['comment' => 'Customer called.']);

        $this->assertSame('Pending', $order->fresh()->order_status_name);
    }

    // ---------------------------------------------------------- the backfill

    public function test_the_backfill_fills_nulls_and_is_idempotent(): void
    {
        $staff = User::factory()->create(['name' => 'Ada Santos']);
        $group = CustomerGroup::create(['store_id' => 1, 'name' => 'Resellers']);
        $customer = Customer::create([
            'store_id' => 1,
            'customer_group_id' => $group->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'password' => bcrypt('secret'),
        ]);
        $courier = ShippingCourier::create(['store_id' => 1, 'name' => 'SPX Express', 'is_active' => true]);
        $status = $this->makeStatus('Delivered');

        $order = $this->order([
            'customer_id' => $customer->id,
            'order_status_id' => $status->id,
            'created_by' => $staff->id,
            'shipping_courier_id' => $courier->id,
        ]);
        $history = OrderHistory::create([
            'order_id' => $order->id,
            'order_status_id' => $status->id,
            'created_by' => $staff->id,
        ]);
        $line = OrderProduct::create([
            'order_id' => $order->id,
            'name' => 'Tube Screamer',
            'price' => 1000,
            'quantity' => 1,
            'total' => 1000,
        ]);

        // Simulate pre-snapshot rows: strip the columns the hooks just wrote.
        DB::table('orders')->where('id', $order->id)->update([
            'order_status_name' => null,
            'customer_group_name' => null,
            'shipping_courier_name' => null,
            'created_by_name' => null,
        ]);
        DB::table('order_histories')->where('id', $history->id)->update([
            'order_status_name' => null,
            'created_by_name' => null,
        ]);
        $product = \App\Models\Product::create([
            'store_id' => 1,
            'name' => 'Tube Screamer',
            'slug' => 'tube-screamer',
            'sku' => 'TS-808',
            'price' => 1000,
            'quantity' => 5,
            'image' => 'catalog/ts808.jpg',
        ]);
        DB::table('order_products')->where('id', $line->id)->update(['product_id' => $product->id]);

        $this->artisan('orders:backfill-snapshots')->assertSuccessful();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status_name' => 'Delivered',
            'customer_group_name' => 'Resellers',
            'shipping_courier_name' => 'SPX Express',
            'created_by_name' => 'Ada Santos',
        ]);
        $this->assertDatabaseHas('order_histories', [
            'id' => $history->id,
            'order_status_name' => 'Delivered',
            'created_by_name' => 'Ada Santos',
        ]);
        $this->assertDatabaseHas('order_products', [
            'id' => $line->id,
            'image' => 'catalog/ts808.jpg',
        ]);

        // Second run must be a no-op: nothing left to fill, nothing overwritten.
        $before = [
            'orders' => DB::table('orders')->orderBy('id')->get(),
            'order_histories' => DB::table('order_histories')->orderBy('id')->get(),
            'order_products' => DB::table('order_products')->orderBy('id')->get(),
        ];

        $this->artisan('orders:backfill-snapshots')
            ->expectsOutputToContain('Wrote 0 values in total.')
            ->assertSuccessful();

        foreach ($before as $table => $rows) {
            $this->assertEquals($rows, DB::table($table)->orderBy('id')->get(), "{$table} changed on the second run.");
        }
    }

    public function test_the_backfill_never_overwrites_an_existing_snapshot(): void
    {
        $status = $this->makeStatus('Pending');
        $order = $this->order(['order_status_id' => $status->id]);

        // History says one thing, the live config now says another.
        $status->update(['name' => 'Renamed Since']);

        $this->artisan('orders:backfill-snapshots')->assertSuccessful();

        $this->assertSame('Pending', $order->fresh()->order_status_name);
    }

    // -------------------------------------------------------------- the badge

    public function test_the_status_badge_degrades_to_neutral_when_the_order_has_no_status(): void
    {
        $this->actingAs(User::factory()->create());

        // A status name with no entry in the tone map, then no name at all.
        $unmapped = $this->makeStatus('Held At Customs');
        $order = $this->order(['order_status_id' => $unmapped->id]);

        $this->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Held At Customs');

        DB::table('orders')->where('id', $order->id)->update([
            'order_status_id' => null,
            'order_status_name' => null,
        ]);

        $this->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('No Status');
    }

    private function countryId(): int
    {
        $existing = DB::table('countries')->value('id');
        if ($existing) {
            return (int) $existing;
        }

        return (int) DB::table('countries')->insertGetId([
            'name' => 'Philippines',
            'iso2' => 'PH',
            'iso3' => 'PHL',
            'is_active' => true,
        ]);
    }
}
