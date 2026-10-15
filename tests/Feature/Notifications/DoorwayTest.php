<?php

namespace Tests\Feature\Notifications;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Store;
use App\Services\OrderNotificationService;
use App\Services\OrderStatusChanger;
use App\Support\NotifyChoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\RecordingChannel;
use Tests\Support\ResetsNotificationChannels;
use Tests\TestCase;

class DoorwayTest extends TestCase
{
    use RefreshDatabase;
    use ResetsNotificationChannels;

    private OrderStatus $packing;

    private OrderStatus $shipped;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        $this->packing = OrderStatus::create(['store_id' => 1, 'name' => 'Packing', 'sort_order' => 1]);
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

    private function order(): Order
    {
        return Order::create([
            'store_id' => 1, 'first_name' => 'A', 'last_name' => 'Buyer',
            'email' => 'buyer@example.test', 'order_status_id' => $this->packing->id,
        ]);
    }

    public function test_moving_an_order_tells_the_customer_per_the_ticks(): void
    {
        $order = $this->order();

        OrderStatusChanger::apply($order, $this->shipped->id, 'On its way');

        $this->assertSame(1, RecordingChannel::$sends);
        $this->assertSame($this->shipped->id, $order->fresh()->order_status_id);
    }

    /** Review Focus 1: the ERP and the courier poll both landing on Shipped. */
    public function test_applying_the_same_status_again_says_nothing(): void
    {
        $order = $this->order();
        OrderStatusChanger::apply($order, $this->shipped->id, 'On its way');
        RecordingChannel::reset();

        OrderStatusChanger::apply($order->fresh(), $this->shipped->id, 'Courier says picked up');

        $this->assertSame(0, RecordingChannel::$sends, 'The order was already Shipped. The customer was told once.');
    }

    public function test_a_silent_move_tells_nobody(): void
    {
        $order = $this->order();

        OrderStatusChanger::apply($order, $this->shipped->id, 'Internal move', NotifyChoice::silent());

        $this->assertSame(0, RecordingChannel::$sends);
    }

    public function test_the_history_row_records_what_was_decided(): void
    {
        $order = $this->order();

        OrderStatusChanger::apply($order, $this->shipped->id, 'On its way');

        $history = $order->fresh()->histories()->latest('id')->first();
        $this->assertTrue((bool) $history->notify_customer, 'The status emails, so the row says the customer was written to.');
        $this->assertTrue($history->notifications['recorder']['sent']);
    }

    /**
     * emailHandledElsewhere() suppresses the STATUS email because the
     * caller (checkout, HitPay) sends its own confirmation instead. That
     * caller still told the customer, so the history row must still read
     * as customer-visible, or the account page hides a comment ("Order
     * confirmed.") that was written precisely for the customer to see.
     */
    public function test_email_handled_elsewhere_still_marks_the_row_customer_visible(): void
    {
        $order = $this->order();

        OrderStatusChanger::apply($order, $this->shipped->id, 'Order confirmed.', NotifyChoice::emailHandledElsewhere());

        $history = $order->fresh()->histories()->latest('id')->first();
        $this->assertTrue((bool) $history->notify_customer, 'The caller told the customer itself, through its own email.');
        Mail::assertNothingSent();
        $this->assertArrayNotHasKey('email', $history->notifications ?? [], 'No status email was queued on top of the confirmation.');
    }

    /**
     * emailHandledElsewhere() defers on channels the same way follow() does
     * (isExplicit() reads $channels, not $email): a HitPay webhook retry
     * that lands on an order already at the target status must announce
     * nothing, the same as the ERP/courier race test_applying_the_same_
     * status_again_says_nothing above.
     */
    public function test_email_handled_elsewhere_on_an_unchanged_status_announces_nothing(): void
    {
        $order = $this->order();
        $order->update(['order_status_id' => $this->shipped->id]);

        OrderStatusChanger::apply($order->fresh(), $this->shipped->id, 'Payment confirmed via HitPay.', NotifyChoice::emailHandledElsewhere());

        $this->assertSame(0, RecordingChannel::$sends, 'The status did not move: nothing new to tell.');
        Mail::assertNothingSent();
    }
}
