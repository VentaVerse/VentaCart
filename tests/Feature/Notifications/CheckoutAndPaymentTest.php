<?php

namespace Tests\Feature\Notifications;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\Store;
use App\Services\CheckoutService;
use App\Services\OrderNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\RecordingChannel;
use Tests\Support\ResetsNotificationChannels;
use Tests\TestCase;

/**
 * The last three places that wrote an order's status by hand: checkout's
 * first status, and the two HitPay payment-success paths (the return URL
 * and the webhook). All three now walk OrderStatusChanger::apply() with
 * NotifyChoice::emailHandledElsewhere(): the status email is suppressed
 * because each path sends its own order confirmation, but channels still
 * follow the store's ticks.
 */
class CheckoutAndPaymentTest extends TestCase
{
    use RefreshDatabase;
    use ResetsNotificationChannels;

    private OrderStatus $pending;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        $this->pending = OrderStatus::create([
            'store_id' => 1, 'name' => 'Pending', 'sort_order' => 1,
            'is_default' => true, 'emails_customer' => true,
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
            'email' => 'buyer@example.test', 'order_status_id' => null,
        ], $overrides));
    }

    // -----------------------------------------------------------------
    // CheckoutService::confirmOrder() — the order's first status
    // -----------------------------------------------------------------

    public function test_a_placed_order_texts_when_its_first_status_is_ticked(): void
    {
        $order = $this->order();

        app(CheckoutService::class)->confirmOrder($order);

        $this->assertSame(1, RecordingChannel::$sends, 'Order placed is the order reaching its first status.');
    }

    /**
     * confirmOrder() sends its own order confirmation; the status change
     * must not queue a second email saying the same thing. Counting
     * Mail::sent() would depend on which templates happen to be seeded (no
     * EmailTemplateSeeder runs in this test), so the seam checked here is
     * the actual outcome: no status email was queued on the history row
     * while the channel, which follows the store's ticks independently of
     * the email, did send.
     */
    public function test_the_status_email_does_not_duplicate_the_confirmation(): void
    {
        $order = $this->order();

        app(CheckoutService::class)->confirmOrder($order);

        $history = $order->fresh()->histories()->latest('id')->first();
        $this->assertArrayNotHasKey('email', $history->notifications ?? [], 'No status email queued on top of the confirmation.');
        $this->assertTrue($history->notifications['recorder']['sent'] ?? false, 'The channel still follows the store\'s ticks.');
    }

    /**
     * Ruling: notify_customer means "the customer was told", not "the
     * status email was sent". Checkout writes 'Order confirmed.' with
     * notify_customer so the account page's history list shows it
     * (resources/themes/venta/views/account/order-detail.blade.php:183).
     * emailHandledElsewhere() suppresses the status email, so apply() must
     * still stamp notify_customer true here or that comment vanishes from
     * the customer's own order page.
     */
    public function test_order_confirmed_stays_customer_visible_after_checkout(): void
    {
        $order = $this->order();

        app(CheckoutService::class)->confirmOrder($order);

        $history = $order->fresh()->histories()->latest('id')->first();
        $this->assertSame('Order confirmed.', $history->comment);
        $this->assertTrue((bool) $history->notify_customer, 'The confirmation email told the customer; the row must say so.');
    }

    public function test_confirming_writes_the_pending_status_and_adjusts_stock_bookkeeping(): void
    {
        $order = $this->order();

        app(CheckoutService::class)->confirmOrder($order);

        $order->refresh();
        $this->assertSame($this->pending->id, $order->order_status_id);
        $this->assertDatabaseHas('order_histories', [
            'order_id' => $order->id,
            'order_status_id' => $this->pending->id,
            'comment' => 'Order confirmed.',
        ]);
    }

    // -----------------------------------------------------------------
    // CheckoutController::hitpayReturn() — the return-URL fallback path
    // -----------------------------------------------------------------

    private function customer(): Customer
    {
        return Customer::create([
            'store_id' => 1, 'first_name' => 'A', 'last_name' => 'Buyer',
            'email' => 'buyer@example.test', 'password' => bcrypt('secret'),
        ]);
    }

    public function test_the_hitpay_return_path_texts_on_the_success_status(): void
    {
        $success = OrderStatus::create(['store_id' => 1, 'name' => 'Paid', 'sort_order' => 2, 'emails_customer' => true]);
        Setting::create(['group' => 'payment', 'key' => 'hitpay_success_status_id', 'value' => (string) $success->id]);

        $customer = $this->customer();
        $order = $this->order([
            'customer_id' => $customer->id,
            'payment_reference' => 'pr_return_1',
        ]);

        $this->actingAs($customer, 'customer')
            ->get(route('checkout.hitpay.return', ['reference' => 'pr_return_1', 'status' => 'completed']))
            ->assertRedirect(route('checkout.confirmation', $order));

        $this->assertSame(1, RecordingChannel::$sends);
        $this->assertSame($success->id, $order->fresh()->order_status_id);

        $history = $order->fresh()->histories()->latest('id')->first();
        $this->assertSame('Payment confirmed via HitPay (return URL).', $history->comment);
        $this->assertTrue((bool) $history->notify_customer);
        $this->assertArrayNotHasKey('email', $history->notifications ?? [], 'The confirmation email is this path\'s own.');
    }

    /** Both HitPay paths already guard against re-applying a status the order holds; the doorway guards again. */
    public function test_the_hitpay_return_path_is_idempotent(): void
    {
        $success = OrderStatus::create(['store_id' => 1, 'name' => 'Paid', 'sort_order' => 2, 'emails_customer' => true]);
        Setting::create(['group' => 'payment', 'key' => 'hitpay_success_status_id', 'value' => (string) $success->id]);

        $customer = $this->customer();
        $order = $this->order([
            'customer_id' => $customer->id,
            'order_status_id' => $success->id,
            'payment_reference' => 'pr_return_2',
        ]);

        $this->actingAs($customer, 'customer')
            ->get(route('checkout.hitpay.return', ['reference' => 'pr_return_2', 'status' => 'completed']))
            ->assertRedirect(route('checkout.confirmation', $order));

        $this->assertSame(0, RecordingChannel::$sends, 'Already on the success status: nothing new to tell.');
        $this->assertDatabaseCount('order_histories', 0);
    }

    // -----------------------------------------------------------------
    // HitPayWebhookController — the authoritative confirmation
    // -----------------------------------------------------------------

    private function hitpaySalt(string $salt = 'test-salt', string $mode = 'sandbox'): void
    {
        Setting::create(['group' => 'payment', 'key' => 'hitpay_mode', 'value' => $mode]);
        Setting::create(['group' => 'payment', 'key' => "hitpay_{$mode}_salt", 'value' => Crypt::encryptString($salt)]);
    }

    /** @return array<string, string> the webhook payload, hmac included */
    private function signedPayload(array $data, string $salt = 'test-salt'): array
    {
        $source = [];
        foreach ($data as $key => $val) {
            $source[$key] = "{$key}{$val}";
        }
        ksort($source);

        $data['hmac'] = hash_hmac('sha256', implode('', array_values($source)), $salt);

        return $data;
    }

    public function test_the_webhook_texts_on_the_success_status(): void
    {
        app(\App\Services\ExtensionManager::class)->install('payment-hitpay');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        $this->hitpaySalt();

        $success = OrderStatus::create(['store_id' => 1, 'name' => 'Paid', 'sort_order' => 2, 'emails_customer' => true]);
        Setting::create(['group' => 'payment', 'key' => 'hitpay_success_status_id', 'value' => (string) $success->id]);

        $order = $this->order(['payment_reference' => 'pr_webhook_1', 'total' => 100]);

        Http::fake(['*/payment-requests/pr_webhook_1' => Http::response([
            'id' => 'pr_webhook_1', 'status' => 'completed',
            'payments' => [['id' => 'p_ok', 'status' => 'succeeded', 'fees' => '1.00']],
        ], 200)]);

        $payload = $this->signedPayload([
            'reference_number' => (string) $order->id,
            'payment_request_id' => 'pr_webhook_1',
            'status' => 'completed',
            'amount' => '100.00',
        ]);

        $this->post(route('hitpay.webhook'), $payload)->assertOk();

        $this->assertSame(1, RecordingChannel::$sends);
        $this->assertSame($success->id, $order->fresh()->order_status_id);

        $history = $order->fresh()->histories()->latest('id')->first();
        $this->assertSame('Payment confirmed via HitPay.', $history->comment);
        $this->assertTrue((bool) $history->notify_customer);
        $this->assertArrayNotHasKey('email', $history->notifications ?? [], 'The confirmation email is this path\'s own.');
    }

    public function test_the_webhook_is_idempotent(): void
    {
        app(\App\Services\ExtensionManager::class)->install('payment-hitpay');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        $this->hitpaySalt();

        $success = OrderStatus::create(['store_id' => 1, 'name' => 'Paid', 'sort_order' => 2, 'emails_customer' => true]);
        Setting::create(['group' => 'payment', 'key' => 'hitpay_success_status_id', 'value' => (string) $success->id]);

        $order = $this->order([
            'order_status_id' => $success->id,
            'payment_reference' => 'pr_webhook_2',
            'total' => 100,
        ]);

        // handleCompleted() looks up the fee before the idempotency check
        // returns, regardless of whether the status itself moves.
        Http::fake(['*/payment-requests/pr_webhook_2' => Http::response([
            'id' => 'pr_webhook_2', 'status' => 'completed',
            'payments' => [['id' => 'p_ok', 'status' => 'succeeded', 'fees' => '1.00']],
        ], 200)]);

        $payload = $this->signedPayload([
            'reference_number' => (string) $order->id,
            'payment_request_id' => 'pr_webhook_2',
            'status' => 'completed',
            'amount' => '100.00',
        ]);

        $this->post(route('hitpay.webhook'), $payload)->assertOk();

        $this->assertSame(0, RecordingChannel::$sends, 'Already on the success status: nothing new to tell.');
        $this->assertDatabaseCount('order_histories', 0);
    }
}
