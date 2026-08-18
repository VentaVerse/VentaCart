<?php

namespace Tests\Feature\AdminV2;

use App\Models\Cart;
use App\Services\ExtensionManager;
use App\Services\OrderTotals\OrderTotals;
use App\Services\OrderTotals\TotalsContext;
use Extensions\Coupons\CouponTotalProvider;
use Extensions\Coupons\Models\Coupon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The order-totals pipeline: registered providers contribute signed
 * lines against one context, the grand total is core arithmetic. The
 * coupon provider is its first tenant; its free-shipping semantics are
 * pinned here because the pre-pipeline checkout got them wrong (it
 * zeroed shipping AND subtracted a discount that included shipping, so
 * the charge undercut the preview the customer accepted).
 */
class OrderTotalsPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        OrderTotals::reset();

        $manager = app(ExtensionManager::class);
        $manager->install('coupons');
        $manager->install('store-credit');
        $manager->bootEnabled();
    }

    public function test_store_credit_applies_after_the_coupon_and_never_exceeds_the_remainder(): void
    {
        Coupon::create([
            'code' => 'TEN', 'name' => 'Ten percent', 'type' => 'percent',
            'discount_value' => 10, 'customer_eligibility' => 'all', 'is_active' => true,
        ]);
        $cart = $this->cartWithItem(1000.0, 'TEN');
        $customer = $cart->customer;
        \Extensions\StoreCredit\CreditService::add($customer->id, 5000.0, 'Test grant');

        $ctx = OrderTotals::collect(new TotalsContext($customer, collect(), 1000.0, 150.0, ['cart' => $cart]));

        // Coupon first (-100), credit covers ALL of what remains (1050),
        // capped by the remainder even though the balance is larger.
        $this->assertSame(-100.0, $ctx->line('coupon')['amount']);
        $this->assertSame(-1050.0, $ctx->line('store_credit')['amount']);
        $this->assertSame(0.0, $ctx->total());
    }

    public function test_store_credit_confirm_writes_the_spend_into_the_ledger(): void
    {
        $cart = $this->cartWithItem(500.0, '');
        $customer = $cart->customer;
        \Extensions\StoreCredit\CreditService::add($customer->id, 200.0, 'Test grant');

        $ctx = OrderTotals::collect(new TotalsContext($customer, collect(), 500.0, 0.0, ['cart' => $cart]));
        $this->assertSame(-200.0, $ctx->line('store_credit')['amount']);

        $order = \App\Models\Order::create([
            'store_id' => 1, 'customer_id' => $customer->id,
            'first_name' => 'Test', 'last_name' => 'Buyer', 'email' => $customer->email,
            'shipping_first_name' => 'Test', 'shipping_last_name' => 'Buyer',
            'shipping_address_1' => 'Street', 'shipping_city' => 'City',
            'subtotal' => 500, 'shipping_cost' => 0, 'total' => 300,
            'total_lines' => $ctx->lines,
        ]);
        OrderTotals::confirm($order, $ctx->lines);

        $this->assertSame(0.0, \Extensions\StoreCredit\CreditService::balance($customer->id));
        $spend = \Extensions\StoreCredit\Models\CustomerTransaction::where('order_id', $order->id)->first();
        $this->assertNotNull($spend);
        $this->assertSame(-200.0, (float) $spend->amount);
    }

    public function test_context_total_is_subtotal_plus_shipping_plus_lines_floored_at_zero(): void
    {
        $ctx = new TotalsContext(null, collect(), 1000.0, 150.0);
        $this->assertSame(1150.0, $ctx->total());

        $ctx->addLine('coupon', 'Coupon (X)', -200.0);
        $this->assertSame(950.0, $ctx->total());

        $ctx->addLine('store_credit', 'Store Credit', -5000.0);
        $this->assertSame(0.0, $ctx->total(), 'Total must never go negative.');
    }

    public function test_percent_coupon_contributes_a_negative_line(): void
    {
        Coupon::create([
            'code' => 'TEN', 'name' => 'Ten percent', 'type' => 'percent',
            'discount_value' => 10, 'customer_eligibility' => 'all', 'is_active' => true,
        ]);
        $cart = $this->cartWithItem(1000.0, 'TEN');

        $ctx = OrderTotals::collect(new TotalsContext(null, collect(), 1000.0, 150.0, ['cart' => $cart]));

        $line = $ctx->line('coupon');
        $this->assertNotNull($line);
        $this->assertSame(-100.0, $line['amount']);
        $this->assertSame(150.0, $ctx->shippingCost, 'Plain percent coupon leaves shipping alone.');
        $this->assertSame(1050.0, $ctx->total());
    }

    public function test_free_shipping_coupon_zeroes_shipping_without_double_discount(): void
    {
        Coupon::create([
            'code' => 'SHIPFREE', 'name' => 'Free shipping', 'type' => 'free_shipping',
            'customer_eligibility' => 'all', 'is_active' => true,
        ]);
        $cart = $this->cartWithItem(1000.0, 'SHIPFREE');

        $ctx = OrderTotals::collect(new TotalsContext(null, collect(), 1000.0, 150.0, ['cart' => $cart]));

        $this->assertSame(0.0, $ctx->shippingCost);
        $line = $ctx->line('coupon');
        $this->assertNotNull($line, 'Line still recorded so the code lands on the order and burns a use.');
        $this->assertSame(0.0, $line['amount'], 'Shipping is zeroed on its own line, never discounted twice.');
        $this->assertSame(1000.0, $ctx->total(), 'Matches the storefront preview: subtotal + zero shipping.');
    }

    public function test_invalid_stored_code_is_cleared_silently_and_charges_full_price(): void
    {
        $cart = $this->cartWithItem(1000.0, 'NEVER-EXISTED');

        $ctx = OrderTotals::collect(new TotalsContext(null, collect(), 1000.0, 150.0, ['cart' => $cart]));

        $this->assertNull($ctx->line('coupon'));
        $this->assertSame(1150.0, $ctx->total());
        $this->assertNull($cart->fresh()->coupon_code);
    }

    public function test_coupons_provider_is_on_the_sign_in_sheet_when_enabled(): void
    {
        $keys = array_map(fn ($p) => $p->getKey(), OrderTotals::providers());
        $this->assertContains('coupon', $keys);
        $this->assertInstanceOf(CouponTotalProvider::class, collect(OrderTotals::providers())->firstWhere(fn ($p) => $p->getKey() === 'coupon'));
    }

    private function cartWithItem(float $price, string $couponCode): Cart
    {
        $customer = \App\Models\Customer::create(['store_id' => 1, 'customer_group_id' => \App\Models\CustomerGroup::create(['store_id' => 1, 'name' => 'Default ' . uniqid(), 'is_default' => true])->id, 'first_name' => 'Test', 'last_name' => 'Buyer', 'email' => uniqid() . '@example.test', 'password' => bcrypt('secret')]);
        $cart = Cart::create(['store_id' => 1, 'customer_id' => $customer->id, 'coupon_code' => $couponCode]);
        $product = \App\Models\Product::create(['store_id' => 1, 'name' => 'Pedal', 'slug' => 'pedal-' . uniqid(), 'price' => $price]);
        $cart->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'price' => $price,
            'quantity' => 1,
        ]);

        return $cart;
    }
}
