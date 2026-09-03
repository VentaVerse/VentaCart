<?php

namespace Tests\Feature\Fulfillment;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Store;
use App\Models\User;
use Extensions\CourierFulfillment\FulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

use Tests\TestCase;

/**
 * A refused cancel has to leave something behind.
 *
 * The courier says no, and nothing else about the order changes: same status,
 * same tracking number, same queue row. The only signal was a toast, and the
 * toast used to remove itself after four and a half seconds, so a failed
 * cancel was indistinguishable from a cancel nobody clicked.
 */
class CancelFailureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        app(\App\Services\ExtensionManager::class)->install('courier-fulfillment');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        $this->actingAs(User::factory()->create());

        \App\Models\Setting::create(['group' => 'courier', 'key' => 'quadx_enabled', 'value' => '1']);
        \App\Models\Setting::create(['group' => 'courier', 'key' => 'quadx_mode', 'value' => 'staging']);
        \App\Models\Setting::create(['group' => 'courier', 'key' => 'quadx_staging_api_key', 'value' => \Illuminate\Support\Facades\Crypt::encryptString('k')]);
        \App\Models\Setting::create(['group' => 'courier', 'key' => 'quadx_staging_secret_key', 'value' => \Illuminate\Support\Facades\Crypt::encryptString('s')]);
    }

    private function bookedOrder(): Order
    {
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Shipped', 'sort_order' => 2]);

        return Order::create([
            'store_id' => 1, 'first_name' => 'A', 'last_name' => 'Buyer',
            'email' => 'buyer@example.test', 'order_status_id' => $status->id,
        ])->forceFill([
            'courier_provider' => 'quadx',
            'courier_tracking_number' => 'GPZZ-LIVE-0001',
            'courier_status' => 'out_for_delivery',
        ]);
    }

    public function test_a_refused_cancel_is_written_to_the_order_history(): void
    {
        $order = $this->bookedOrder();
        $order->save();

        Http::fake([
            '*/orders/GPZZ-LIVE-0001/cancel' => Http::response(
                ['errors' => ['detail' => 'Order already picked up.']], 422
            ),
        ]);

        $result = FulfillmentService::cancelBooking($order, null);

        $this->assertFalse($result['success']);
        $this->assertDatabaseHas('order_histories', ['order_id' => $order->id]);

        $comment = \App\Models\OrderHistory::where('order_id', $order->id)->latest('id')->value('comment');
        $this->assertStringContainsString('REFUSED', $comment);
        $this->assertStringContainsString('GPZZ-LIVE-0001', $comment);
        $this->assertStringContainsString('already picked up', strtolower($comment));
    }

    /** The booking must survive a refusal untouched: nothing was cancelled. */
    public function test_a_refused_cancel_leaves_the_booking_alone(): void
    {
        $order = $this->bookedOrder();
        $order->save();

        Http::fake(['*/orders/GPZZ-LIVE-0001/cancel' => Http::response([], 500)]);

        FulfillmentService::cancelBooking($order, null);

        $order->refresh();
        $this->assertSame('quadx', $order->courier_provider);
        $this->assertSame('GPZZ-LIVE-0001', $order->courier_tracking_number);
    }

    /** The message must say what to do, not just what the status code was. */
    public function test_the_refusal_explains_what_to_do_next(): void
    {
        $order = $this->bookedOrder();
        $order->save();

        Http::fake([
            '*/orders/GPZZ-LIVE-0001/cancel' => Http::response(
                ['errors' => ['detail' => 'Parcel is in transit.']], 409
            ),
        ]);

        $error = FulfillmentService::cancelBooking($order, null)['error'];

        // The courier's own words survive.
        $this->assertStringContainsString('in transit', $error);
        // And so does the instruction.
        $this->assertStringContainsString('still live', $error);
        $this->assertStringContainsString('order history', $error);
    }
}
