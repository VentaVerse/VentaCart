<?php

namespace Tests\Feature\Notifications;

use App\Mail\TemplateMail;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Store;
use App\Models\User;
use App\Services\OrderNotificationService;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\RecordingChannel;
use Tests\Support\ResetsNotificationChannels;
use Tests\TestCase;

class AdminOverrideTest extends TestCase
{
    use RefreshDatabase;
    use ResetsNotificationChannels;

    private OrderStatus $packing;

    private OrderStatus $shipped;

    protected function setUp(): void
    {
        parent::setUp();
        // TemplateMailService::send() finds no active template and quietly
        // returns false without this, so Mail::assertSent() would pass
        // whether or not the email path actually fired.
        $this->seed(EmailTemplateSeeder::class);
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        $this->packing = OrderStatus::create(['store_id' => 1, 'name' => 'Packing', 'sort_order' => 1]);
        $this->shipped = OrderStatus::create([
            'store_id' => 1, 'name' => 'Shipped', 'sort_order' => 2, 'emails_customer' => true,
        ]);
        RecordingChannel::reset();
        OrderNotificationService::registerChannel(RecordingChannel::class);
        Mail::fake();
        $this->actingAs(User::factory()->create());
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

    public function test_unticking_on_the_page_silences_that_one_change(): void
    {
        $order = $this->order();

        $this->post(route('admin.orders.history.store', $order), [
            'order_status_id' => $this->shipped->id,
            'comment' => 'Quietly moved',
            // notify_customer absent: the operator unticked it
        ])->assertSessionHasNoErrors();

        Mail::assertNothingSent();
        $this->assertSame(0, RecordingChannel::$sends);
        $this->assertSame($this->shipped->id, $order->fresh()->order_status_id);
    }

    public function test_ticking_a_channel_on_the_page_sends_it(): void
    {
        $order = $this->order();

        $this->post(route('admin.orders.history.store', $order), [
            'order_status_id' => $this->shipped->id,
            'comment' => 'On its way',
            'notify_customer' => '1',
            'channels' => ['recorder'],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, RecordingChannel::$sends, 'Exactly once: the page must not send and then announce as well.');

        // Ticking Notify has to genuinely produce the status email, not only
        // a channel send; a template-less test would let this pass either way.
        Mail::assertSent(TemplateMail::class, 1);
        Mail::assertSent(TemplateMail::class, fn (TemplateMail $m) => str_contains($m->renderedBody, 'On its way'));
    }

    /**
     * The everyday flow: a parcel already on Shipped gets its tracking
     * number added, the operator ticks Notify and a channel, and the status
     * itself does not move. apply() used to announce only when the status
     * changed, so this sent nothing at all while the history row still
     * claimed notify_customer = true.
     */
    public function test_adding_tracking_while_already_on_the_target_status_still_tells_the_customer(): void
    {
        $order = $this->order();
        $order->update(['order_status_id' => $this->shipped->id]);

        $this->post(route('admin.orders.history.store', $order), [
            'order_status_id' => $this->shipped->id,
            'tracking_number' => 'TRK1',
            'comment' => 'Adding the tracking number',
            'notify_customer' => '1',
            'channels' => ['recorder'],
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->shipped->id, $order->fresh()->order_status_id, 'The status genuinely did not move.');
        Mail::assertSent(TemplateMail::class, 1);
        $this->assertSame(1, RecordingChannel::$sends);
    }

    /**
     * The edit form (Details + Products tabs) can move the status too, and
     * used to do it with a bare mass-update: no history row, no
     * announcement, ever. It now walks the same doorway as every other
     * status change, following the store's own tick since the form carries
     * no per-change override for this.
     */
    public function test_the_edit_form_changing_status_writes_history_and_announces_once(): void
    {
        $order = $this->order();

        $this->put(route('admin.orders.update', $order), [
            'store_id' => 1,
            'order_status_id' => $this->shipped->id,
            'first_name' => 'A', 'last_name' => 'Buyer', 'email' => 'buyer@example.test',
            'items' => [],
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->shipped->id, $order->fresh()->order_status_id);
        $this->assertDatabaseHas('order_histories', [
            'order_id' => $order->id,
            'order_status_id' => $this->shipped->id,
        ]);
        $this->assertSame(1, RecordingChannel::$sends, 'Shipped emails the customer per the store\'s own tick.');
    }
}
