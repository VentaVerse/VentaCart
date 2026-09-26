<?php

namespace Tests\Feature\Notifications;

use App\Mail\TemplateMail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Store;
use App\Services\CheckoutService;
use App\Services\OrderNotificationService;
use App\Services\OrderStatusChanger;
use App\Support\NotifyChoice;
use Database\Seeders\EmailTemplateSeeder;
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
        // TemplateMailService::send() finds no active template and quietly
        // returns false without this, so a test asserting the confirmation
        // email was actually sent would pass whether or not it fired.
        $this->seed(EmailTemplateSeeder::class);
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
    // CheckoutService::confirmOrder(): the order's first status
    // -----------------------------------------------------------------

    public function test_a_placed_order_texts_when_its_first_status_is_ticked(): void
    {
        $order = $this->order();

        app(CheckoutService::class)->confirmOrder($order);

        $this->assertSame(1, RecordingChannel::$sends, 'Order placed is the order reaching its first status.');
    }

    /**
     * confirmOrder() sends its own order confirmation; the status change
     * must not queue a second email saying the same thing. Counting a bare
     * Mail::sent() would not tell the two apart if it somehow doubled, so
     * this proves both halves: exactly one TemplateMail went out, and it
     * is the confirmation (not, say, a status email that slipped through),
     * while the history row shows no status email was ever queued and the
     * channel, which follows the store's ticks independently of the email,
     * still sent.
     */
    public function test_the_status_email_does_not_duplicate_the_confirmation(): void
    {
        $order = $this->order();

        app(CheckoutService::class)->confirmOrder($order);

        Mail::assertSent(TemplateMail::class, 1);
        Mail::assertSent(TemplateMail::class, fn (TemplateMail $m) => str_contains($m->renderedSubject, 'Confirmed'));

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

    public function test_confirming_writes_the_pending_status_and_deducts_stock(): void
    {
        $this->pending->update(['deducts_stock' => true]);
        $product = Product::create([
            'store_id' => 1, 'name' => 'Tube Screamer', 'slug' => 'tube-screamer-' . uniqid(),
            'price' => 1000, 'quantity' => 10, 'track_stock' => true, 'is_active' => true,
        ]);

        $order = $this->order();
        OrderProduct::create([
            'order_id' => $order->id, 'product_id' => $product->id, 'name' => $product->name,
            'price' => 500, 'quantity' => 2, 'total' => 1000,
        ]);

        app(CheckoutService::class)->confirmOrder($order);

        $order->refresh();
        $this->assertSame($this->pending->id, $order->order_status_id);
        $this->assertDatabaseHas('order_histories', [
            'order_id' => $order->id,
            'order_status_id' => $this->pending->id,
            'comment' => 'Order confirmed.',
        ]);
        $this->assertSame(8, (int) $product->fresh()->quantity, 'The doorway still runs StockService, same as the manual write it replaced.');
        $this->assertTrue((bool) $order->stock_adjusted);
    }

    // -----------------------------------------------------------------
    // CheckoutController::hitpayReturn(): the return-URL fallback path
    // -----------------------------------------------------------------

    private function customer(): Customer
    {
        return Customer::create([
            'store_id' => 1, 'first_name' => 'A', 'last_name' => 'Buyer',
            'email' => 'buyer@example.test', 'password' => bcrypt('secret'),
        ]);
    }

    /**
     * HitPay's API confirming this order's payment, the way the return page
     * now requires before it will call an order paid: the order's own
     * payment request, its reference, its total, and a succeeded payment.
     */
    private function hitpayConfirms(Order $order): void
    {
        app(\App\Services\ExtensionManager::class)->install('payment-hitpay');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        Setting::updateOrCreate(['group' => 'payment', 'key' => 'hitpay_mode'], ['value' => 'sandbox']);
        Setting::updateOrCreate(
            ['group' => 'payment', 'key' => 'hitpay_sandbox_api_key'],
            ['value' => \Illuminate\Support\Facades\Crypt::encryptString('test-key')]
        );

        Http::fake(['*/payment-requests/' . $order->payment_reference => Http::response([
            'id' => $order->payment_reference,
            'status' => 'completed',
            'reference_number' => (string) $order->id,
            'amount' => number_format((float) $order->total, 2, '.', ''),
            'payments' => [['id' => 'p_ok', 'status' => 'succeeded', 'fees' => '1.00']],
        ], 200)]);
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
        $this->hitpayConfirms($order);

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

    /**
     * A single repeated hit: the order already holds the target status
     * when this request reads it, so apply() is never even called. A true
     * race against the webhook, where both requests read the order before
     * either writes, is a different guard entirely: see
     * test_the_return_path_and_the_webhook_racing_only_tells_the_customer_once
     * below.
     */
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
        $this->hitpayConfirms($order);

        $this->actingAs($customer, 'customer')
            ->get(route('checkout.hitpay.return', ['reference' => 'pr_return_2', 'status' => 'completed']))
            ->assertRedirect(route('checkout.confirmation', $order));

        $this->assertSame(0, RecordingChannel::$sends, 'Already on the success status: nothing new to tell.');
        $this->assertDatabaseCount('order_histories', 0);
    }

    /**
     * I6: the customer bookmarks or reloads the return URL days later. The
     * order was paid and has since shipped; the return path must not drag
     * it back to Paid, and nobody is told anything.
     */
    public function test_revisiting_the_return_url_never_reverts_a_later_status(): void
    {
        $success = OrderStatus::create(['store_id' => 1, 'name' => 'Paid', 'sort_order' => 2, 'emails_customer' => true]);
        $shipped = OrderStatus::create(['store_id' => 1, 'name' => 'Shipped', 'sort_order' => 3, 'emails_customer' => true]);
        Setting::create(['group' => 'payment', 'key' => 'hitpay_success_status_id', 'value' => (string) $success->id]);

        $customer = $this->customer();
        $order = $this->order([
            'customer_id' => $customer->id,
            'payment_reference' => 'pr_return_3',
        ]);
        $this->hitpayConfirms($order);
        $url = route('checkout.hitpay.return', ['reference' => 'pr_return_3', 'status' => 'completed']);

        $this->actingAs($customer, 'customer')->get($url)->assertRedirect(route('checkout.confirmation', $order));
        OrderStatusChanger::apply($order->fresh(), $shipped->id, 'On its way');
        $sendsBefore = RecordingChannel::$sends;
        $historiesBefore = $order->histories()->count();
        $mailsBefore = count(Mail::sent(TemplateMail::class));
        $this->assertGreaterThan(0, $mailsBefore, 'The first visit sent its confirmation, so the count below means something.');

        $this->actingAs($customer, 'customer')->get($url)->assertRedirect(route('checkout.confirmation', $order));

        $this->assertSame($shipped->id, $order->fresh()->order_status_id, 'Paid once already: the return URL has nothing left to confirm.');
        $this->assertSame($sendsBefore, RecordingChannel::$sends);
        $this->assertSame($historiesBefore, $order->histories()->count());
        $this->assertCount($mailsBefore, Mail::sent(TemplateMail::class), 'No second confirmation email.');
    }

    // -----------------------------------------------------------------
    // HitPayWebhookController: the authoritative confirmation
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

    // -----------------------------------------------------------------
    // The return URL and the webhook racing each other
    // -----------------------------------------------------------------

    /**
     * A single PHP test process cannot make two requests genuinely
     * overlap; both HTTP calls below run fully sequentially, and each
     * controller re-fetches the order fresh at its own start regardless
     * of the race fix, so this alone does not exercise the actual
     * vulnerability (see test_a_stale_read_still_loses_the_race below for
     * that). What it does prove end to end, through the real routes: the
     * second path to arrive, however soon after, finds the order already
     * claimed and does nothing more, no second history row, no second
     * confirmation, no second text.
     */
    public function test_the_webhook_then_the_return_path_racing_only_tells_the_customer_once(): void
    {
        app(\App\Services\ExtensionManager::class)->install('payment-hitpay');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        $this->hitpaySalt();

        $success = OrderStatus::create(['store_id' => 1, 'name' => 'Paid', 'sort_order' => 2, 'emails_customer' => true]);
        Setting::create(['group' => 'payment', 'key' => 'hitpay_success_status_id', 'value' => (string) $success->id]);

        $customer = $this->customer();
        $order = $this->order([
            'customer_id' => $customer->id,
            'payment_reference' => 'pr_race_1',
            'total' => 100,
        ]);
        $this->hitpayConfirms($order);

        $payload = $this->signedPayload([
            'reference_number' => (string) $order->id,
            'payment_request_id' => 'pr_race_1',
            'status' => 'completed',
            'amount' => '100.00',
        ]);

        // HitPay's own webhook is first to confirm the payment.
        $this->post(route('hitpay.webhook'), $payload)->assertOk();

        // The customer's browser, redirected before the webhook landed,
        // arrives moments later: the loser of the race.
        $this->actingAs($customer, 'customer')
            ->get(route('checkout.hitpay.return', ['reference' => 'pr_race_1', 'status' => 'completed']))
            ->assertRedirect(route('checkout.confirmation', $order));

        $this->assertSame($success->id, $order->fresh()->order_status_id);
        $this->assertSame(1, RecordingChannel::$sends, 'Only the winner texts.');
        $this->assertSame(1, $order->fresh()->histories()->count(), 'One history row total, whichever path wrote it.');
        Mail::assertSent(TemplateMail::class, 1);
        Mail::assertSent(TemplateMail::class, fn (TemplateMail $m) => str_contains($m->renderedSubject, 'Confirmed'));
    }

    /** The same race, the other way round. */
    public function test_the_return_path_then_the_webhook_racing_only_tells_the_customer_once(): void
    {
        app(\App\Services\ExtensionManager::class)->install('payment-hitpay');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        $this->hitpaySalt();

        $success = OrderStatus::create(['store_id' => 1, 'name' => 'Paid', 'sort_order' => 2, 'emails_customer' => true]);
        Setting::create(['group' => 'payment', 'key' => 'hitpay_success_status_id', 'value' => (string) $success->id]);

        $customer = $this->customer();
        $order = $this->order([
            'customer_id' => $customer->id,
            'payment_reference' => 'pr_race_2',
            'total' => 100,
        ]);

        // handleCompleted() looks up the fee unconditionally, so the
        // webhook leg of this race needs the same fake as any other
        // webhook test, even though it is the loser here.
        $this->hitpayConfirms($order);

        // The customer's browser reaches the return URL first.
        $this->actingAs($customer, 'customer')
            ->get(route('checkout.hitpay.return', ['reference' => 'pr_race_2', 'status' => 'completed']))
            ->assertRedirect(route('checkout.confirmation', $order));

        // HitPay's webhook, delayed on their end, arrives after: the loser.
        $payload = $this->signedPayload([
            'reference_number' => (string) $order->id,
            'payment_request_id' => 'pr_race_2',
            'status' => 'completed',
            'amount' => '100.00',
        ]);
        $this->post(route('hitpay.webhook'), $payload)->assertOk();

        $this->assertSame($success->id, $order->fresh()->order_status_id);
        $this->assertSame(1, RecordingChannel::$sends, 'Only the winner texts.');
        $this->assertSame(1, $order->fresh()->histories()->count(), 'One history row total, whichever path wrote it.');
        Mail::assertSent(TemplateMail::class, 1);
        Mail::assertSent(TemplateMail::class, fn (TemplateMail $m) => str_contains($m->renderedSubject, 'Confirmed'));
    }

    /**
     * The actual vulnerability the two HTTP tests above cannot reach: two
     * requests that BOTH read the order before EITHER wrote. Two separate
     * Order instances stand in for the webhook's and the return URL's own
     * fetch, taken here at the same moment, before anything changes. The
     * first claim writes the status; the second is handed the SAME stale
     * instance, its in-memory order_status_id still null, exactly as it
     * would be had the two requests actually overlapped. OrderStatusChanger
     * ::claimStatus() must decide from a fresh lockForUpdate() read, not
     * from that stale attribute, or this second call would re-apply
     * everything: a naive `if ($order->order_status_id !== $target)` guard
     * (what both paths used to write inline) would fail this exact test,
     * because $order->order_status_id really is null on both instances.
     */
    public function test_a_stale_read_still_loses_the_race(): void
    {
        $shipped = OrderStatus::create(['store_id' => 1, 'name' => 'Paid', 'sort_order' => 2, 'emails_customer' => true]);
        $order = $this->order();

        // Both "requests" read the order before either writes.
        $readByWebhook = Order::find($order->id);
        $readByReturnUrl = Order::find($order->id);
        $this->assertNull($readByReturnUrl->order_status_id, 'Confirms the second read really is stale, not already updated.');

        $winner = OrderStatusChanger::claimStatus($readByWebhook, $shipped->id, 'Payment confirmed via HitPay.', NotifyChoice::emailHandledElsewhere());
        $loser = OrderStatusChanger::claimStatus($readByReturnUrl, $shipped->id, 'Payment confirmed via HitPay (return URL).', NotifyChoice::emailHandledElsewhere());

        $this->assertTrue($winner);
        $this->assertFalse($loser, 'The stale in-memory read must not fool the guard.');
        $this->assertSame($shipped->id, $order->fresh()->order_status_id);
        $this->assertSame(1, $order->fresh()->histories()->count());
        $this->assertSame(1, RecordingChannel::$sends);
    }
}
