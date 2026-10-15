<?php

namespace Tests\Feature\Notifications;

use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\OrderStatus;
use App\Models\Store;
use App\Services\OrderNotificationService;
use App\Support\NotifyChoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\RecordingChannel;
use Tests\Support\ResetsNotificationChannels;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;
    use ResetsNotificationChannels;

    private OrderStatus $shipped;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        $this->shipped = OrderStatus::create([
            'store_id' => 1, 'name' => 'Shipped', 'sort_order' => 2]);
        $this->emailAutomaticallyOn($this->shipped->id);
        RecordingChannel::reset();
        OrderNotificationService::registerChannel(RecordingChannel::class);
        Mail::fake();
    }

    protected function tearDown(): void
    {
        $this->resetNotificationChannels();

        parent::tearDown();
    }

    private function order(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'store_id' => 1, 'first_name' => 'A', 'last_name' => 'Buyer',
            'email' => 'buyer@example.test', 'order_status_id' => $this->shipped->id,
        ], $overrides));
    }

    public function test_following_the_store_emails_when_the_status_says_so(): void
    {
        $order = $this->order();

        $plan = OrderNotificationService::announce($order, $this->shipped, 'Packing', 'On its way', NotifyChoice::follow());

        $this->assertTrue($plan['email']);
        $this->assertSame(['recorder'], $plan['channels'], 'The channel said it wants this status.');
        $this->assertSame(1, RecordingChannel::$sends);
    }

    public function test_a_channel_that_does_not_want_the_status_is_not_used(): void
    {
        RecordingChannel::$wants = false;
        $order = $this->order();

        $plan = OrderNotificationService::announce($order, $this->shipped, 'Packing', null, NotifyChoice::follow());

        $this->assertSame([], $plan['channels']);
        $this->assertSame(0, RecordingChannel::$sends);
    }

    public function test_an_explicit_choice_overrides_the_store(): void
    {
        $order = $this->order();

        $plan = OrderNotificationService::announce($order, $this->shipped, 'Packing', null, NotifyChoice::explicit(false, []));

        $this->assertFalse($plan['email'], 'The person with the order open unticked it.');
        $this->assertSame([], $plan['channels']);
        $this->assertSame(0, RecordingChannel::$sends);
    }

    /** Review Focus 2: a gateway that throws changes nothing else. */
    public function test_a_channel_that_throws_is_recorded_and_harmless(): void
    {
        RecordingChannel::$throw = true;
        $order = $this->order();
        $history = OrderHistory::create([
            'order_id' => $order->id, 'order_status_id' => $this->shipped->id, 'comment' => null,
        ]);

        OrderNotificationService::announce($order, $this->shipped, 'Packing', null, NotifyChoice::follow(), $history->id);

        $recorded = $history->fresh()->notifications;
        $this->assertFalse($recorded['recorder']['sent']);
        $this->assertStringContainsString('gateway is down', $recorded['recorder']['reason']);
    }

    /** Review Focus 3: nobody to email means nothing sent and a reason kept. */
    public function test_an_order_with_no_email_records_why(): void
    {
        // orders.email is NOT NULL with no default (see
        // 2026_03_05_155952_create_orders_table.php), so an empty string is
        // how "no email" is representable here. The production check this
        // exercises (`! $order->email`) treats '' the same as null.
        $order = $this->order(['email' => '']);
        $history = OrderHistory::create([
            'order_id' => $order->id, 'order_status_id' => $this->shipped->id, 'comment' => null,
        ]);

        OrderNotificationService::announce($order, $this->shipped, 'Packing', null, NotifyChoice::follow(), $history->id);

        $recorded = $history->fresh()->notifications;
        $this->assertFalse($recorded['email']['sent']);
        $this->assertNotNull($recorded['email']['reason']);
    }
}
