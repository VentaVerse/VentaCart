<?php

namespace Tests\Feature\AdminV2;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Setting;
use Extensions\CourierFulfillment\CourierStatusLabel;
use Extensions\CourierFulfillment\StatusMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A mapped courier status is written into the order's history, and sent to
 * the customer when the mapping says so, as a sentence in the courier's own
 * words: "QuadX: Out for delivery". The raw code ("out_for_delivery") stays
 * on courier_status for the admin and the API; it never reaches a customer.
 */
class CourierStatusSentenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The extension's classes autoload only once it is installed and
        // booted, the same way the app boots it.
        app(\App\Services\ExtensionManager::class)->install('courier-fulfillment');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        CourierStatusLabel::forget();
    }

    private function order(OrderStatus $status, string $provider, ?string $courierName): Order
    {
        $customer = Customer::create(['store_id' => 1, 'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => uniqid('jane') . '@example.com', 'password' => bcrypt('x')]);

        $order = Order::create([
            'store_id' => 1, 'customer_id' => $customer->id, 'order_status_id' => $status->id,
            'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com', 'phone' => '1',
            'shipping_first_name' => 'Jane', 'shipping_last_name' => 'Doe', 'shipping_address_1' => 'x',
            'shipping_city' => 'Cebu City', 'shipping_postcode' => '6000', 'subtotal' => 100, 'total' => 100,
        ])->forceFill(['courier_provider' => $provider, 'shipping_courier_name' => $courierName]);
        $order->save();

        return $order;
    }

    public function test_the_history_row_reads_as_the_courier_would_say_it(): void
    {
        $pending = OrderStatus::create(['store_id' => 1, 'name' => 'Pending', 'is_default' => true, 'sort_order' => 1]);
        $shipped = OrderStatus::create(['store_id' => 1, 'name' => 'Shipped', 'sort_order' => 2]);
        Setting::updateOrCreate(['group' => 'courier', 'key' => 'quadx_status_map'], ['value' => json_encode([
            'out_for_delivery' => ['order_status_id' => $shipped->id, 'notify' => false],
        ])]);
        $order = $this->order($pending, 'quadx', 'QuadX');

        $this->assertTrue(StatusMapper::apply($order, 'out_for_delivery'));

        $this->assertDatabaseHas('order_histories', ['order_id' => $order->id, 'order_status_id' => $shipped->id, 'comment' => 'QuadX: Out for delivery']);
        $this->assertDatabaseMissing('order_histories', ['order_id' => $order->id, 'comment' => 'Courier status: out_for_delivery']);
    }

    public function test_labels_the_admin_refreshed_from_the_courier_win_over_the_spaced_code(): void
    {
        Setting::updateOrCreate(['group' => 'courier', 'key' => 'quadx_status_labels'], ['value' => json_encode(['for_pickup' => 'Waiting for pickup rider'])]);

        $this->assertSame('Waiting for pickup rider', CourierStatusLabel::for('quadx', 'for_pickup'));
        $this->assertSame('At sorting center', CourierStatusLabel::for('quadx', 'at_sorting_center'));
    }

    public function test_a_courier_with_a_known_vocabulary_speaks_it_without_a_refresh(): void
    {
        $this->assertSame('Delivered', CourierStatusLabel::for('spx', '4001'));
    }

    public function test_the_courier_name_comes_from_the_orders_own_snapshot(): void
    {
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Pending', 'is_default' => true, 'sort_order' => 1]);

        $this->assertSame('GoGo Xpress: Delivered', CourierStatusLabel::sentence($this->order($status, 'quadx', 'GoGo Xpress'), 'delivered'));
        $this->assertSame('QuadX: Delivered', CourierStatusLabel::sentence($this->order($status, 'quadx', null), 'delivered'));
    }
}
