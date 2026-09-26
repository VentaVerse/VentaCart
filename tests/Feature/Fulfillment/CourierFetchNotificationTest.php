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

    /**
     * M4: the owner's reported bug, driven through the real SMS channel. A
     * status ticked in UniSMS's own list, a courier status map row, the
     * gateway faked at the HTTP layer: exactly one message leaves, and even
     * with a template that pulls in the comment and the tracking number of
     * an order whose tracking link is on file, it carries no URL.
     */
    public function test_the_real_sms_channel_sends_one_text_without_a_url(): void
    {
        $this->resetNotificationChannels();
        app(\App\Services\ExtensionManager::class)->install('sms-unisms');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        Setting::updateOrCreate(['group' => 'sms', 'key' => \Extensions\SmsUnisms\UniSms::KEY_ENABLED], ['value' => '1']);
        Setting::updateOrCreate(['group' => 'sms', 'key' => \Extensions\SmsUnisms\UniSms::KEY_API], ['value' => \Illuminate\Support\Facades\Crypt::encryptString('dummy-key')]);
        Setting::updateOrCreate(['group' => 'sms', 'key' => \Extensions\SmsUnisms\UniSms::KEY_SENDER], ['value' => 'DUMMY']);
        \Extensions\SmsUnisms\SmsTemplate::create(['title' => 'Shipped', 'message' => '{store_name}: Order #{order_id} is now {order_status}. {comment} {tracking_number}'])
            ->statuses()->sync([$this->shipped->id]);
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['data' => ['reference_id' => 'msg_1']], 200)]);

        $order = Order::create([
            'store_id' => 1, 'first_name' => 'A', 'last_name' => 'Buyer',
            'email' => 'buyer@example.test', 'phone' => '+639171234567',
            'order_status_id' => $this->packing->id,
            'tracking_number' => 'GGX123',
            'tracking_link' => 'https://gogoxpress.com/track/GGX123',
        ]);
        $order->forceFill(['courier_provider' => 'quadx'])->save();
        Setting::create([
            'group' => 'courier',
            'key' => 'quadx_status_map',
            'value' => json_encode([
                'picked_up' => ['order_status_id' => $this->shipped->id, 'notify' => false],
            ]),
        ]);

        StatusMapper::apply($order, 'picked_up');

        $posts = \Illuminate\Support\Facades\Http::recorded(fn ($request) => $request->method() === 'POST');
        $this->assertCount(1, $posts, 'Exactly one text for one status change.');
        $content = (string) $posts[0][0]->data()['content'];
        $this->assertStringContainsString('is now Shipped', $content);
        $this->assertStringContainsString('GGX123', $content, 'The number survives; only the link goes.');
        $this->assertDoesNotMatchRegularExpression('~https?://|www\\.|gogoxpress\\.com|/track~i', $content);
        $this->assertTrue($order->histories()->latest('id')->first()->notifications['sms']['sent']);
    }
}
