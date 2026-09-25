<?php

namespace Tests\Feature\Notifications;

use App\Jobs\SendOrderStatusNotification;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\OrderStatus;
use App\Models\Store;
use App\Services\OrderNotificationService;
use App\Services\OrderStatusChanger;
use App\Support\NotifyChoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Support\RecordingChannel;
use Tests\Support\ResetsNotificationChannels;
use Tests\TestCase;

/**
 * The doorway's own bookkeeping: it decides against the row as it stands in
 * the database (not a copy some caller read earlier), writes everything in
 * one transaction, and the history row says truthfully whether the customer
 * was told and what they were told.
 */
class DoorwayIntegrityTest extends TestCase
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

    private function order(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'store_id' => 1, 'first_name' => 'A', 'last_name' => 'Buyer',
            'email' => 'buyer@example.test', 'order_status_id' => $this->packing->id,
        ], $overrides));
    }

    /**
     * M1: a courier webhook and a poll each read the order while it was on
     * Packing. The first moves it to Shipped; the second arrives holding its
     * stale copy. The doorway re-reads the row, sees Shipped, and says
     * nothing a second time.
     */
    public function test_a_stale_copy_of_the_order_does_not_announce_twice(): void
    {
        $order = $this->order();
        $staleCopy = Order::find($order->id);

        OrderStatusChanger::apply($order, $this->shipped->id, 'Picked up');
        RecordingChannel::reset();

        OrderStatusChanger::apply($staleCopy, $this->shipped->id, 'Picked up');

        $this->assertSame(0, RecordingChannel::$sends, 'The second caller\'s copy was stale; the row was already Shipped.');
    }

    /** M1: history, status and stock land together or not at all. */
    public function test_the_history_row_is_written_inside_a_transaction(): void
    {
        $outside = DB::transactionLevel();
        $levelAtWrite = null;
        OrderHistory::created(function () use (&$levelAtWrite): void {
            $levelAtWrite = DB::transactionLevel();
        });

        OrderStatusChanger::apply($this->order(), $this->shipped->id, 'Picked up');

        $this->assertGreaterThan($outside, $levelAtWrite);
    }

    /** M1: nested inside claimStatus()'s own transaction it is a savepoint, not a deadlock. */
    public function test_claiming_a_status_still_applies_it_once(): void
    {
        $order = $this->order();

        $this->assertTrue(OrderStatusChanger::claimStatus($order, $this->shipped->id, 'Paid'));
        $this->assertFalse(OrderStatusChanger::claimStatus($order, $this->shipped->id, 'Paid'));

        $this->assertSame($this->shipped->id, $order->fresh()->order_status_id);
        $this->assertSame(1, RecordingChannel::$sends);
        $this->assertSame(1, $order->histories()->count());
    }

    /** M3: a text alone still told the customer, so they may read the comment. */
    public function test_a_row_where_only_a_channel_was_queued_reads_as_customer_visible(): void
    {
        $quiet = OrderStatus::create(['store_id' => 1, 'name' => 'Out for delivery', 'sort_order' => 3, 'emails_customer' => false]);

        OrderStatusChanger::apply($this->order(), $quiet->id, 'Rider is on the way');

        $history = OrderHistory::latest('id')->first();
        $this->assertSame(1, RecordingChannel::$sends);
        $this->assertTrue((bool) $history->notify_customer);
    }

    public function test_a_row_where_nothing_was_queued_stays_internal(): void
    {
        RecordingChannel::$wants = false;
        $quiet = OrderStatus::create(['store_id' => 1, 'name' => 'Out for delivery', 'sort_order' => 3, 'emails_customer' => false]);

        OrderStatusChanger::apply($this->order(), $quiet->id, 'Internal note');

        $this->assertFalse((bool) OrderHistory::latest('id')->first()->notify_customer);
    }

    public function test_a_silent_move_stays_internal(): void
    {
        OrderStatusChanger::apply($this->order(), $this->shipped->id, 'Internal', NotifyChoice::silent());

        $this->assertFalse((bool) OrderHistory::latest('id')->first()->notify_customer);
    }

    /**
     * M2: a line kept for the record (an ERP fallback, a fulfilment audit
     * suffix) stays on the history row; the customer reads only what was
     * written for them, in their email and on their account page alike.
     */
    public function test_the_customer_reads_the_customer_comment_not_the_record_line(): void
    {
        Queue::fake();
        $customer = Customer::create([
            'store_id' => 1, 'first_name' => 'A', 'last_name' => 'Buyer',
            'email' => 'buyer@example.test', 'password' => bcrypt('secret'),
        ]);
        $order = $this->order(['customer_id' => $customer->id]);

        OrderStatusChanger::apply($order, $this->shipped->id, 'Status updated via ERP API', NotifyChoice::follow(), null, '');

        $history = OrderHistory::latest('id')->first();
        $this->assertSame('Status updated via ERP API', $history->comment, 'The record keeps its line.');
        Queue::assertPushed(SendOrderStatusNotification::class, fn ($job) => $job->target === 'email' && $job->comment === '');

        $this->actingAs($customer, 'customer')
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSee('Shipped')
            ->assertDontSee('Status updated via ERP API');
    }

    public function test_a_row_with_no_separate_customer_comment_shows_its_own_comment(): void
    {
        $customer = Customer::create([
            'store_id' => 1, 'first_name' => 'A', 'last_name' => 'Buyer',
            'email' => 'buyer@example.test', 'password' => bcrypt('secret'),
        ]);
        $order = $this->order(['customer_id' => $customer->id]);

        OrderStatusChanger::apply($order, $this->shipped->id, 'On its way to you');

        $this->actingAs($customer, 'customer')
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSee('On its way to you');
    }
}
