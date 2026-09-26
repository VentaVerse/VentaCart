<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\Store;
use App\Services\ExtensionManager;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Support\ResetsNotificationChannels;
use Tests\Support\SmsRecordingChannel;
use Tests\TestCase;

/**
 * The path the business uses most. It told nobody and moved no stock.
 */
class ErpStatusNotificationTest extends TestCase
{
    use RefreshDatabase;
    use ResetsNotificationChannels;

    private OrderStatus $packing;

    private OrderStatus $shipped;

    protected function setUp(): void
    {
        parent::setUp();
        // TemplateMailService::send() finds no active template and quietly
        // returns false without this, so Mail::assertSentCount() would pass
        // whether or not the email path actually fired.
        $this->seed(EmailTemplateSeeder::class);
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        app(ExtensionManager::class)->install('erp-sync');
        app(ExtensionManager::class)->bootEnabled();
        $this->apiKey('erp-test-token');
        $this->packing = OrderStatus::create(['store_id' => 1, 'name' => 'Packing', 'sort_order' => 1]);
        $this->shipped = OrderStatus::create([
            'store_id' => 1, 'name' => 'Shipped', 'sort_order' => 2, 'deducts_stock' => true]);
        $this->emailAutomaticallyOn($this->shipped->id);
        Mail::fake();
    }

    private function order(): Order
    {
        return Order::create([
            'store_id' => 1, 'first_name' => 'A', 'last_name' => 'Buyer',
            'email' => 'buyer@example.test', 'order_status_id' => $this->packing->id,
        ]);
    }

    public function test_an_erp_status_update_emails_the_customer(): void
    {
        $order = $this->order();

        $this->withToken('erp-test-token')
            ->putJson('/api/v1/orders/' . $order->id . '/status', ['status_id' => $this->shipped->id])
            ->assertOk();

        Mail::assertSentCount(1);
    }

    public function test_an_erp_status_update_moves_stock(): void
    {
        $product = Product::create([
            'store_id' => 1, 'name' => 'Cable', 'slug' => 'cable',
            'price' => 100, 'quantity' => 5, 'track_stock' => true,
        ]);
        $order = $this->order();
        $order->products()->create([
            'product_id' => $product->id, 'name' => 'Cable', 'price' => 100, 'quantity' => 2, 'total' => 200,
        ]);

        $this->withToken('erp-test-token')
            ->putJson('/api/v1/orders/' . $order->id . '/status', ['status_id' => $this->shipped->id])
            ->assertOk();

        $this->assertSame(3, $product->fresh()->quantity, 'Shipped deducts stock, whoever moved the order.');
    }

    public function test_setting_the_status_it_already_has_says_nothing(): void
    {
        $order = $this->order();
        $order->update(['order_status_id' => $this->shipped->id]);

        $this->withToken('erp-test-token')
            ->putJson('/api/v1/orders/' . $order->id . '/status', ['status_id' => $this->shipped->id])
            ->assertOk();

        Mail::assertNothingSent();
    }

    /**
     * M2: the fallback wording is for the record. Without a comment from the
     * ERP the customer's email {comment} is empty, and the account page
     * shows no "Status updated via ERP API".
     */
    public function test_the_fallback_wording_stays_on_the_record(): void
    {
        Queue::fake();
        $order = $this->order();

        $this->withToken('erp-test-token')
            ->putJson('/api/v1/orders/' . $order->id . '/status', ['status_id' => $this->shipped->id])
            ->assertOk();

        $history = $order->histories()->latest('id')->first();
        $this->assertSame('Status updated via ERP API', $history->comment);
        $this->assertSame('', $history->customerFacingComment());
        Queue::assertPushed(\App\Jobs\SendOrderStatusNotification::class, fn ($job) => $job->target === 'email' && $job->comment === '');
    }

    public function test_a_comment_from_the_erp_reaches_the_customer(): void
    {
        Queue::fake();
        $order = $this->order();

        $this->withToken('erp-test-token')
            ->putJson('/api/v1/orders/' . $order->id . '/status', ['status_id' => $this->shipped->id, 'comment' => 'Packed with care'])
            ->assertOk();

        $history = $order->histories()->latest('id')->first();
        $this->assertSame('Packed with care', $history->comment);
        $this->assertSame('Packed with care', $history->customerFacingComment());
        Queue::assertPushed(\App\Jobs\SendOrderStatusNotification::class, fn ($job) => $job->target === 'email' && $job->comment === 'Packed with care');
    }

    protected function tearDown(): void
    {
        $this->resetNotificationChannels();
        parent::tearDown();
    }

    /** @return list<string> the targets queued for this request, email and channels alike */
    private function queuedTargets(): array
    {
        return Queue::pushed(\App\Jobs\SendOrderStatusNotification::class)
            ->map(fn ($job) => $job->target)->sort()->values()->all();
    }

    private function moveToShipped(Order $order, array $body): void
    {
        $this->withToken('erp-test-token')
            ->putJson('/api/v1/orders/' . $order->id . '/status', ['status_id' => $this->shipped->id] + $body)
            ->assertOk();
    }

    /** The caller may ask for the email on a status the store does not email about. */
    public function test_notify_true_emails_on_a_status_off_the_automatic_list(): void
    {
        Queue::fake();
        $this->emailAutomaticallyOn();

        $this->moveToShipped($this->order(), ['notify' => true]);

        $this->assertSame(['email'], $this->queuedTargets());
    }

    public function test_notify_false_keeps_a_listed_status_quiet(): void
    {
        Queue::fake();

        $this->moveToShipped($this->order(), ['notify' => false]);

        $this->assertSame([], $this->queuedTargets());
    }

    /** Asking is deliberate: it speaks even when the status did not move. */
    public function test_notify_true_emails_even_on_the_status_it_already_has(): void
    {
        Queue::fake();
        $order = $this->order();
        $order->update(['order_status_id' => $this->shipped->id]);

        $this->moveToShipped($order, ['notify' => true]);

        $this->assertSame(['email'], $this->queuedTargets());
    }

    public function test_notify_sms_true_texts_and_email_follows_the_list(): void
    {
        Queue::fake();
        SmsRecordingChannel::reset();
        SmsRecordingChannel::$wants = false;
        \App\Services\OrderNotificationService::registerChannel(SmsRecordingChannel::class);

        $this->moveToShipped($this->order(), ['notify_sms' => true]);

        $this->assertSame(['email', 'sms'], $this->queuedTargets());
    }

    public function test_notify_sms_false_stops_the_text_and_leaves_the_email(): void
    {
        Queue::fake();
        SmsRecordingChannel::reset();
        \App\Services\OrderNotificationService::registerChannel(SmsRecordingChannel::class);

        $this->moveToShipped($this->order(), ['notify_sms' => false]);

        $this->assertSame(['email'], $this->queuedTargets());
    }

    public function test_saying_neither_follows_the_store_for_both(): void
    {
        Queue::fake();
        SmsRecordingChannel::reset();
        \App\Services\OrderNotificationService::registerChannel(SmsRecordingChannel::class);

        $this->moveToShipped($this->order(), []);

        $this->assertSame(['email', 'sms'], $this->queuedTargets());
    }

    public function test_notify_must_be_a_yes_or_no(): void
    {
        $this->withToken('erp-test-token')
            ->putJson('/api/v1/orders/' . $this->order()->id . '/status', ['status_id' => $this->shipped->id, 'notify' => 'loudly'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('notify');
    }
}
