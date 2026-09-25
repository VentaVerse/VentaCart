<?php

namespace Tests\Feature\Fulfillment;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\Store;
use App\Services\OrderNotificationService;
use Extensions\CourierFulfillment\StatusMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\RecordingChannel;
use Tests\Support\ResetsNotificationChannels;
use Tests\TestCase;

/**
 * The QuadX case: the courier says picked up, the order becomes Shipped, and
 * Shipped is ticked to text. The text must go.
 */
class CourierFetchNotificationTest extends TestCase
{
    use RefreshDatabase;
    use ResetsNotificationChannels;

    private OrderStatus $packing;

    private OrderStatus $shipped;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        app(\App\Services\ExtensionManager::class)->install('courier-fulfillment');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        $this->packing = OrderStatus::create(['store_id' => 1, 'name' => 'Packing', 'sort_order' => 1]);
        $this->shipped = OrderStatus::create([
            'store_id' => 1, 'name' => 'Shipped', 'sort_order' => 2, 'emails_customer' => true,
        ]);
        RecordingChannel::reset();
        OrderNotificationService::registerChannel(RecordingChannel::class);
        Mail::fake();
    }

    protected function tearDown(): void
    {
        $this->resetNotificationChannels();
        parent::tearDown();
    }

    public function test_a_fetched_courier_status_texts_the_customer(): void
    {
        $order = Order::create([
            'store_id' => 1, 'first_name' => 'A', 'last_name' => 'Buyer',
            'email' => 'buyer@example.test', 'order_status_id' => $this->packing->id,
        ]);
        $order->forceFill(['courier_provider' => 'quadx'])->save();
        // The map is a settings row keyed by the courier's own status, which
        // is what StatusMapper::currentMap() reads.
        Setting::create([
            'group' => 'courier',
            'key' => 'quadx_status_map',
            'value' => json_encode([
                'picked_up' => ['order_status_id' => $this->shipped->id, 'notify' => false],
            ]),
        ]);

        StatusMapper::apply($order, 'picked_up');

        $this->assertSame($this->shipped->id, $order->fresh()->order_status_id);
        $this->assertSame(1, RecordingChannel::$sends);
    }
}
