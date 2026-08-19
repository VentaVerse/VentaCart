<?php

namespace Tests\Feature\AdminV2;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Store;
use App\Models\User;
use App\Services\ExtensionManager;
use Extensions\CourierFulfillment\PlacementMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Card #135: the manual form in Fulfillment mirrors "Update order" on the core
 * order page. Status required, courier optional and defaulting to "Select
 * courier...", tracking, comment, a notify-customer tickbox, then Update.
 *
 * The consequence the card asks for and the old form could not do: submitting
 * with no courier is a PLAIN STATUS UPDATE. It moves the order, saves tracking
 * and comment, notifies if ticked, and records no courier against the order.
 * Before this, courier was required, so an operator standing in the packing
 * queue had to open the order page to do something as ordinary as moving a
 * status.
 */
class ManualUpdateMirrorsOrderTest extends TestCase
{
    use RefreshDatabase;

    private OrderStatus $from;
    private OrderStatus $to;

    protected function setUp(): void
    {
        parent::setUp();
        app(ExtensionManager::class)->install('courier-fulfillment');
        app(ExtensionManager::class)->bootEnabled();

        Store::firstOrCreate(['id' => 1], ['name' => 'Test Store', 'code' => 'test']);

        $this->from = OrderStatus::create(['store_id' => 1, 'name' => 'Processing', 'sort_order' => 1, 'is_default' => true]);
        $this->to   = OrderStatus::create(['store_id' => 1, 'name' => 'Shipped', 'sort_order' => 2]);
        PlacementMap::put($this->from->id, 'to_pack');
        PlacementMap::put($this->to->id, 'to_handover');

        $this->actingAs(User::factory()->create());
    }

    private function order(): Order
    {
        return Order::create([
            'store_id' => 1, 'order_status_id' => $this->from->id,
            'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com',
            'subtotal' => 100, 'total' => 100,
        ]);
    }

    private function url(Order $order): string
    {
        return '/' . config('admin.prefix', 'admin') . '/extensions/courier-fulfillment/' . $order->id . '/book-manual';
    }

    public function test_submitting_with_no_courier_is_a_plain_status_update(): void
    {
        $order = $this->order();

        $this->post($this->url($order), [
            'order_status_id' => $this->to->id,
            'shipping_courier_id' => '',
            'tracking_number' => 'TRACK-123',
            'comment' => 'Handed to the rider myself.',
        ])->assertRedirect();

        $order->refresh();

        $this->assertSame($this->to->id, $order->order_status_id, 'The status did not move.');
        $this->assertNull($order->shipping_courier_id, 'A courier was recorded when none was chosen.');
        $this->assertSame('TRACK-123', $order->tracking_number);
    }

    public function test_a_courier_is_still_recorded_when_one_is_chosen(): void
    {
        $courier = DB::table('shipping_couriers')->insertGetId([
            'store_id' => 1, 'name' => 'Grab Express', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $order = $this->order();

        $this->post($this->url($order), [
            'order_status_id' => $this->to->id,
            'shipping_courier_id' => $courier,
            'tracking_number' => 'GX-9',
        ])->assertRedirect();

        $order->refresh();

        $this->assertSame($courier, (int) $order->shipping_courier_id);
        $this->assertSame($this->to->id, $order->order_status_id);
    }

    public function test_status_is_still_required(): void
    {
        $order = $this->order();

        $this->post($this->url($order), ['shipping_courier_id' => ''])
            ->assertSessionHasErrors('order_status_id');
    }

    public function test_ticking_notify_customer_records_it_on_the_history_row(): void
    {
        $order = $this->order();

        $this->post($this->url($order), [
            'order_status_id' => $this->to->id,
            'shipping_courier_id' => '',
            'comment' => 'On its way.',
            'notify_customer' => '1',
        ])->assertRedirect();

        $this->assertTrue(
            (bool) DB::table('order_histories')->where('order_id', $order->id)->latest('id')->value('notify_customer'),
            'Notify customer was ticked and the history row does not say so.'
        );
    }

    public function test_not_ticking_it_leaves_the_customer_alone(): void
    {
        $order = $this->order();

        $this->post($this->url($order), [
            'order_status_id' => $this->to->id,
            'shipping_courier_id' => '',
            'comment' => 'Quietly moved.',
        ])->assertRedirect();

        $this->assertFalse(
            (bool) DB::table('order_histories')->where('order_id', $order->id)->latest('id')->value('notify_customer')
        );
    }
}
