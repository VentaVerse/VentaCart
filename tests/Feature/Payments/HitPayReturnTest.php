<?php

namespace Tests\Feature\Payments;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The page a shopper lands on coming back from HitPay.
 *
 * HitPay appends ?reference=...&status=completed to the redirect, and the
 * shopper can type both. This page used to mark the order paid on that word
 * alone, so a shopper could close the payment page unpaid, edit the address
 * and receive a paid order. HitPay's own API now decides, held to the same
 * checks as the signed webhook.
 */
class HitPayReturnTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private OrderStatus $pending;

    private OrderStatus $paid;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        app(\App\Services\ExtensionManager::class)->install('payment-hitpay');
        app(\App\Services\ExtensionManager::class)->bootEnabled();

        Setting::create(['group' => 'payment', 'key' => 'hitpay_mode', 'value' => 'sandbox']);
        Setting::create(['group' => 'payment', 'key' => 'hitpay_sandbox_api_key', 'value' => Crypt::encryptString('k')]);

        $this->pending = OrderStatus::create(['store_id' => 1, 'name' => 'Pending', 'sort_order' => 1]);
        $this->paid = OrderStatus::create(['store_id' => 1, 'name' => 'Processing', 'sort_order' => 2]);
        Setting::create(['group' => 'payment', 'key' => 'hitpay_success_status_id', 'value' => (string) $this->paid->id]);

        $this->customer = Customer::create([
            'store_id' => 1, 'first_name' => 'Jane', 'last_name' => 'Doe',
            'email' => 'jane@example.test', 'password' => bcrypt('secret'),
        ]);
    }

    private function order(float $total = 4715.00): Order
    {
        return Order::create([
            'store_id' => 1, 'customer_id' => $this->customer->id,
            'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.test',
            'order_status_id' => $this->pending->id,
            'total' => $total, 'payment_reference' => 'pr_abc123',
        ]);
    }

    /** What HitPay's API answers for pr_abc123, with any field overridden. */
    private function hitpaySays(Order $order, array $overrides = []): void
    {
        Http::fake(['*/payment-requests/pr_abc123' => Http::response(array_merge([
            'id' => 'pr_abc123',
            'status' => 'completed',
            'reference_number' => (string) $order->id,
            'amount' => number_format((float) $order->total, 2, '.', ''),
            'payments' => [['id' => 'p_ok', 'status' => 'succeeded', 'fees' => '170.02']],
        ], $overrides), 200)]);
    }

    private function comeBack(string $status = 'completed')
    {
        return $this->actingAs($this->customer, 'customer')
            ->get(route('checkout.hitpay.return', ['reference' => 'pr_abc123', 'status' => $status]));
    }

    public function test_a_payment_hitpay_confirms_marks_the_order_paid(): void
    {
        $order = $this->order();
        $this->hitpaySays($order);

        $this->comeBack()->assertRedirect(route('checkout.confirmation', $order));

        $this->assertSame($this->paid->id, (int) $order->fresh()->order_status_id);
    }

    /** The reported attack: close HitPay unpaid, type status=completed. */
    public function test_typing_completed_into_the_address_does_not_pay_the_order(): void
    {
        $order = $this->order();
        $this->hitpaySays($order, ['status' => 'pending', 'payments' => []]);

        $this->comeBack()->assertRedirect(route('account.orders.show', $order));

        $this->assertSame($this->pending->id, (int) $order->fresh()->order_status_id);
    }

    public function test_a_payment_request_for_another_order_does_not_pay_this_one(): void
    {
        $order = $this->order();
        $this->hitpaySays($order, ['reference_number' => (string) ($order->id + 1)]);

        $this->comeBack();

        $this->assertSame($this->pending->id, (int) $order->fresh()->order_status_id);
    }

    public function test_a_payment_for_less_than_the_total_does_not_pay_the_order(): void
    {
        $order = $this->order(4715.00);
        $this->hitpaySays($order, ['amount' => '50.00']);

        $this->comeBack();

        $this->assertSame($this->pending->id, (int) $order->fresh()->order_status_id);
    }

    public function test_a_completed_request_with_no_succeeded_payment_does_not_pay_the_order(): void
    {
        $order = $this->order();
        $this->hitpaySays($order, ['payments' => [['id' => 'p1', 'status' => 'failed']]]);

        $this->comeBack();

        $this->assertSame($this->pending->id, (int) $order->fresh()->order_status_id);
    }

    /** HitPay down is not proof of payment: the webhook confirms it later. */
    public function test_hitpay_being_unreachable_leaves_the_order_for_the_webhook(): void
    {
        $order = $this->order();
        Http::fake(['*/payment-requests/*' => Http::response(['message' => 'down'], 503)]);

        $this->comeBack()->assertRedirect(route('account.orders.show', $order))->assertSessionHas('success');

        $this->assertSame($this->pending->id, (int) $order->fresh()->order_status_id);
    }

    public function test_an_unconfirmed_return_keeps_the_cart(): void
    {
        $order = $this->order();
        $this->hitpaySays($order, ['status' => 'pending', 'payments' => []]);
        $cart = $this->customer->cart()->create(['store_id' => 1]);
        $product = \App\Models\Product::create(['store_id' => 1, 'name' => 'Cable', 'slug' => 'cable', 'sku' => 'C1', 'price' => 100, 'quantity' => 3, 'is_active' => true]);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 1, 'name' => 'Cable', 'price' => 100]);

        $this->comeBack();

        $this->assertSame(1, $cart->items()->count(), 'A shopper who has not paid still has their cart.');
    }

    public function test_a_cancelled_payment_goes_back_to_checkout_without_asking_hitpay(): void
    {
        $order = $this->order();
        Http::fake();

        $this->comeBack('canceled')->assertRedirect(route('checkout.index'));

        Http::assertNothingSent();
        $this->assertSame($this->pending->id, (int) $order->fresh()->order_status_id);
    }

    public function test_another_customers_payment_reference_finds_nothing(): void
    {
        $order = $this->order();
        $this->hitpaySays($order);
        $other = Customer::create([
            'store_id' => 1, 'first_name' => 'Sam', 'last_name' => 'Roe',
            'email' => 'sam@example.test', 'password' => bcrypt('secret'),
        ]);

        $this->actingAs($other, 'customer')
            ->get(route('checkout.hitpay.return', ['reference' => 'pr_abc123', 'status' => 'completed']))
            ->assertRedirect(route('account.orders'));

        $this->assertSame($this->pending->id, (int) $order->fresh()->order_status_id);
    }
}
