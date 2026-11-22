<?php

namespace Tests\Feature\Storefront;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Country;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\ProductDiscount;
use App\Models\ProductSpecial;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\Store;
use App\Services\CartService;
use App\Services\ExtensionManager;
use App\Services\OrderTotals\OrderTotalProviderInterface;
use App\Services\OrderTotals\OrderTotals;
use App\Services\OrderTotals\TotalsContext;
use App\Services\PaymentManager;
use App\Services\Shipping\ShippingQuoteService;
use Extensions\Coupons\Models\Coupon;
use Extensions\ShippingFlatRate\Models\FlatShippingRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * An order is charged what core prices each cart line at when it is
 * placed, not what the line cost when it was added. When the two differ,
 * in either direction, the order is refused, the cart takes the new
 * prices, and the shopper reviews the new total before placing again.
 */
class CheckoutRepricingTest extends TestCase
{
    use RefreshDatabase;

    private const PRICE_CHANGED = 'Some prices in your cart changed since you added them. Please review your order before placing it.';

    private Customer $customer;

    private CustomerAddress $address;

    private Product $product;

    private Cart $cart;

    private FlatShippingRate $standard;

    private int $retailGroupId;

    protected function setUp(): void
    {
        parent::setUp();
        ShippingQuoteService::reset();
        PaymentManager::reset();
        OrderTotals::reset();
        Mail::fake();
        Cache::forget('default_customer_group_id');

        Store::firstOrCreate(['code' => 'test'], ['name' => 'Demo Store']);
        OrderStatus::create(['store_id' => 1, 'name' => 'Pending', 'sort_order' => 1, 'is_default' => true]);

        $extensions = app(ExtensionManager::class);
        foreach (['shipping-flat-rate', 'payment-cod', 'payment-hitpay', 'coupons'] as $name) {
            $extensions->install($name);
        }
        $extensions->bootEnabled();

        $this->retailGroupId = CustomerGroup::where('is_default', true)->value('id')
            ?? CustomerGroup::create(['store_id' => 1, 'name' => 'Retail', 'is_default' => true])->id;

        $ph = Country::create(['iso2' => 'PH', 'iso3' => 'PHL', 'name' => 'Philippines', 'phone_code' => '63', 'is_active' => true]);

        $this->customer = Customer::create([
            'store_id' => 1, 'first_name' => 'Juan', 'last_name' => 'Dela Cruz',
            'email' => 'juan@example.test', 'password' => bcrypt('secret'),
        ]);
        $this->address = $this->customer->addresses()->create([
            'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'address_1' => '1 Demo Street',
            'country_id' => $ph->id, 'state' => 'Metro Manila', 'city' => 'Manila',
            'district' => 'Sampaloc', 'barangay' => 'Barangay 556', 'postcode' => '1008', 'phone' => '9170000000',
            'is_default' => true,
        ]);

        $this->product = Product::create([
            'store_id' => 1, 'name' => 'Demo Cable', 'slug' => 'demo-cable', 'sku' => 'DEMO-001',
            'price' => 500, 'quantity' => 20, 'weight' => 1, 'is_active' => true, 'track_stock' => true,
        ]);
        $this->cart = Cart::create(['store_id' => 1, 'customer_id' => $this->customer->id]);

        $this->standard = FlatShippingRate::create(['name' => 'Standard Delivery', 'cost' => 150, 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        ShippingQuoteService::reset();
        PaymentManager::reset();
        OrderTotals::reset();
        ChangesPriceDuringTotals::$change = null;
        parent::tearDown();
    }

    private function line(float $price, int $quantity = 2, ?ProductVariant $variant = null): CartItem
    {
        return $this->cart->items()->create([
            'product_id' => $this->product->id,
            'product_variant_id' => $variant?->id,
            'name' => 'Demo Cable',
            'variant_label' => $variant ? 'Long' : null,
            'price' => $price,
            'quantity' => $quantity,
        ]);
    }

    private function place(array $form = [], ?Customer $customer = null)
    {
        return $this->actingAs($customer ?? $this->customer, 'customer')
            ->from(route('checkout.index'))
            ->post(route('checkout.place'), array_merge([
                'shipping_address_id' => $this->address->id,
                'payment_method' => 'cod',
                'shipping_method' => 'flat_rate_' . $this->standard->id,
            ], $form));
    }

    private function assertRefusedForPrices($response, string $item = 'Demo Cable'): void
    {
        $response->assertRedirect(route('checkout.index'));
        $response->assertSessionHasErrors(['checkout' => self::PRICE_CHANGED . ' Updated: ' . $item . '.']);
        $this->assertSame(0, Order::count(), 'No order is written.');
    }

    // -----------------------------------------------------------------
    // Prices that moved since the line was added
    // -----------------------------------------------------------------

    public function test_a_special_that_ended_after_adding_refuses_the_order_and_updates_the_cart(): void
    {
        $special = ProductSpecial::create([
            'product_id' => $this->product->id, 'price_type' => 'fixed', 'value' => 400,
            'date_end' => now()->addHour(),
        ]);

        $this->actingAs($this->customer, 'customer')
            ->postJson(route('cart.add'), ['product_id' => $this->product->id, 'quantity' => 2])
            ->assertOk();
        $this->assertSame(400.0, (float) CartItem::sole()->price, 'Added while the special ran.');

        $special->update(['date_end' => now()->subMinute()]);

        $this->assertRefusedForPrices($this->place());
        $this->assertSame(500.0, (float) CartItem::sole()->price, 'The cart now holds the current price.');
        $this->assertSame(20, (int) $this->product->fresh()->quantity, 'Stock is untouched.');

        // Placing again, at the price now shown, goes through.
        $this->place()->assertSessionHasNoErrors();
        $order = Order::sole();
        $this->assertSame(1000.0, (float) $order->subtotal);
        $this->assertSame(1150.0, (float) $order->total);
    }

    public function test_cart_reprices_when_special_ends(): void
    {
        $special = ProductSpecial::create([
            'product_id' => $this->product->id, 'price_type' => 'percent', 'value' => 20,
            'date_end' => now()->addHour(),
        ]);

        $this->actingAs($this->customer, 'customer');
        $cart = app(CartService::class);
        $cart->add($this->product->id, 1);
        $this->assertSame(400.0, (float) CartItem::sole()->price, '20% off while the special runs.');

        $special->update(['date_end' => now()->subMinute()]);

        $this->assertSame(500.0, (float) $cart->getItems()->sole()->price, 'The cart page shows the regular price again.');
        $this->assertSame(500.0, (float) CartItem::sole()->fresh()->price);
    }

    public function test_a_price_the_merchant_raised_refuses_the_order(): void
    {
        $this->line(500);
        $this->product->update(['price' => 600]);

        $this->assertRefusedForPrices($this->place());
        $this->assertSame(600.0, (float) CartItem::sole()->price);
    }

    public function test_a_price_the_merchant_lowered_also_refuses_so_the_shopper_sees_the_new_total(): void
    {
        $this->line(500);
        $this->product->update(['price' => 450]);

        $this->assertRefusedForPrices($this->place());
        $this->assertSame(450.0, (float) CartItem::sole()->price);

        $this->place()->assertSessionHasNoErrors();
        $this->assertSame(1050.0, (float) Order::sole()->total);
    }

    public function test_an_unchanged_cart_places_the_order_as_before(): void
    {
        $this->line(500);

        $this->place()->assertSessionHasNoErrors()->assertRedirect();

        $order = Order::sole();
        $this->assertSame(1150.0, (float) $order->total);
        $this->assertSame(500.0, (float) $order->products()->sole()->price);
        $this->assertNotNull($order->order_status_id, 'Cash on delivery confirms at once.');
        $this->assertSame(0, $this->cart->items()->count());
    }

    // -----------------------------------------------------------------
    // Core's pricing rules, as the cart applies them
    // -----------------------------------------------------------------

    private function resellerDiscount(): CustomerGroup
    {
        $resellers = CustomerGroup::create(['store_id' => 1, 'name' => 'Resellers']);
        ProductDiscount::create([
            'product_id' => $this->product->id, 'customer_group_id' => $resellers->id,
            'quantity' => 1, 'price_type' => 'fixed', 'value' => 400,
        ]);

        return $resellers;
    }

    public function test_a_customer_group_price_is_kept_for_that_group(): void
    {
        $this->customer->update(['customer_group_id' => $this->resellerDiscount()->id]);
        $this->line(400);

        $this->place()->assertSessionHasNoErrors();
        $this->assertSame(400.0, (float) Order::sole()->products()->sole()->price);
    }

    public function test_another_groups_price_is_not_charged_to_this_customer(): void
    {
        // The line holds the reseller price, but the shopper is retail
        // (moved out of the group, say, after adding to the cart).
        $this->resellerDiscount();
        $this->customer->update(['customer_group_id' => $this->retailGroupId]);
        $this->line(400);

        $this->assertRefusedForPrices($this->place());
        $this->assertSame(500.0, (float) CartItem::sole()->price);
    }

    public function test_a_volume_tier_follows_the_line_quantity(): void
    {
        ProductDiscount::create(['product_id' => $this->product->id, 'customer_group_id' => null, 'quantity' => 2, 'price_type' => 'fixed', 'value' => 450]);

        // Two units reach the tier, but the line still holds the one-unit price.
        $this->line(500, 2);
        $this->assertRefusedForPrices($this->place());
        $this->assertSame(450.0, (float) CartItem::sole()->price);

        $this->place()->assertSessionHasNoErrors();
        $this->assertSame(900.0, (float) Order::sole()->subtotal);
    }

    public function test_a_variant_keeps_its_price_modifier_on_top_of_a_special(): void
    {
        $this->product->update(['has_variants' => true]);
        $variant = ProductVariant::create([
            'product_id' => $this->product->id, 'sku' => 'DEMO-001-L', 'price' => 700,
            'quantity' => 10, 'is_active' => true,
        ]);
        $this->line(700, 1, $variant);

        ProductSpecial::create(['product_id' => $this->product->id, 'price_type' => 'fixed', 'value' => 400, 'date_end' => now()->addWeek()]);

        $this->assertRefusedForPrices($this->place(), 'Demo Cable (Long)');
        $this->assertSame(600.0, (float) CartItem::sole()->price, 'Special 400 plus the variant\'s 200.');

        $this->place()->assertSessionHasNoErrors();
        $this->assertSame(600.0, (float) Order::sole()->products()->sole()->price);
    }

    // -----------------------------------------------------------------
    // Coupons and subtotal-based rules see the new prices
    // -----------------------------------------------------------------

    public function test_a_percent_coupon_is_worked_out_on_the_new_subtotal(): void
    {
        Coupon::create([
            'code' => 'TEN', 'name' => 'Ten percent', 'type' => 'percent',
            'discount_value' => 10, 'customer_eligibility' => 'all', 'is_active' => true,
        ]);
        $this->line(500);
        $this->cart->update(['coupon_code' => 'TEN']);
        $this->product->update(['price' => 600]);

        $this->assertRefusedForPrices($this->place());

        $this->place()->assertSessionHasNoErrors();
        $order = Order::sole();
        $this->assertSame(120.0, (float) $order->coupon_discount, 'Ten percent of 1200, not of 1000.');
        $this->assertSame(1230.0, (float) $order->total);
    }

    public function test_a_coupon_minimum_is_judged_on_the_new_subtotal(): void
    {
        Coupon::create([
            'code' => 'SPEND1000', 'name' => 'Spend 1000', 'type' => 'fixed', 'discount_value' => 100,
            'min_order_total' => 1000, 'customer_eligibility' => 'all', 'is_active' => true,
        ]);
        $this->line(500);
        $this->cart->update(['coupon_code' => 'SPEND1000']);
        $this->product->update(['price' => 450]);

        $this->assertRefusedForPrices($this->place());

        // The checkout page, read at the new prices, drops the coupon and says why.
        $this->actingAs($this->customer, 'customer')->get(route('checkout.index'))
            ->assertOk()
            ->assertViewHas('subtotal', 900.0)
            ->assertViewHas('couponCode', null)
            ->assertViewHas('couponError', 'Minimum order total of ₱1,000.00 required.');

        $this->place()->assertSessionHasNoErrors();
        $order = Order::sole();
        $this->assertSame(0.0, (float) $order->coupon_discount);
        $this->assertSame(1050.0, (float) $order->total);
    }

    public function test_applying_a_coupon_uses_current_prices(): void
    {
        Coupon::create([
            'code' => 'SPEND1000', 'name' => 'Spend 1000', 'type' => 'fixed', 'discount_value' => 100,
            'min_order_total' => 1000, 'customer_eligibility' => 'all', 'is_active' => true,
        ]);
        $this->line(500);
        $this->product->update(['price' => 450]);

        $this->actingAs($this->customer, 'customer')
            ->postJson(route('checkout.coupon.apply'), ['code' => 'SPEND1000'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Minimum order total of ₱1,000.00 required.');

        $this->assertSame(450.0, (float) CartItem::sole()->price);
    }

    // -----------------------------------------------------------------
    // Pages show current prices before Place order
    // -----------------------------------------------------------------

    public function test_the_cart_and_checkout_pages_show_current_prices(): void
    {
        $this->line(500);
        $this->product->update(['price' => 550]);

        $this->actingAs($this->customer, 'customer')->get(route('cart.index'))
            ->assertOk()->assertViewHas('subtotal', 1100.0);
        $this->assertSame(550.0, (float) CartItem::sole()->price);

        $this->product->update(['price' => 525]);
        $this->actingAs($this->customer, 'customer')->get(route('checkout.index'))
            ->assertOk()->assertViewHas('subtotal', 1050.0);
        $this->assertSame(525.0, (float) CartItem::sole()->price);

        // Having seen the page, the shopper places at that price.
        $this->place()->assertSessionHasNoErrors();
        $this->assertSame(1200.0, (float) Order::sole()->total);
    }

    // -----------------------------------------------------------------
    // A change while the order is being placed
    // -----------------------------------------------------------------

    public function test_a_price_change_after_the_first_check_is_caught_inside_the_transaction(): void
    {
        $this->line(500);
        // A totals step runs after the first check and before the order
        // is written; the price moves while it runs.
        ChangesPriceDuringTotals::$change = fn () => $this->product->update(['price' => 520]);
        OrderTotals::register(ChangesPriceDuringTotals::class, 1);

        $this->assertRefusedForPrices($this->place());
        $this->assertSame(520.0, (float) CartItem::sole()->price, 'Saved although the transaction rolled back.');
    }

    // -----------------------------------------------------------------
    // Online payment
    // -----------------------------------------------------------------

    public function test_online_payment_is_not_started_when_prices_changed_and_unchanged_otherwise(): void
    {
        Setting::create(['group' => 'payment', 'key' => 'hitpay_mode', 'value' => 'sandbox']);
        Setting::create(['group' => 'payment', 'key' => 'hitpay_sandbox_api_key', 'value' => Crypt::encryptString('k')]);
        Http::fake(['*/payment-requests' => Http::response(['id' => 'pr_demo', 'url' => 'https://pay.example.test/pr_demo'], 201)]);

        $this->line(500);
        $this->product->update(['price' => 480]);

        $this->assertRefusedForPrices($this->place(['payment_method' => 'hitpay']));
        Http::assertNothingSent();

        $this->place(['payment_method' => 'hitpay'])->assertRedirect('https://pay.example.test/pr_demo');
        $this->assertSame(1110.0, (float) Order::sole()->total);
        Http::assertSent(fn ($request) => ($request['amount'] ?? null) === '1110.00');
        $this->assertSame(1, $this->cart->items()->count(), 'The cart waits for the payment to be confirmed.');
    }
}

/**
 * A totals step that changes a price while it runs, standing in for a
 * merchant saving a price during the seconds an order is being placed.
 */
class ChangesPriceDuringTotals implements OrderTotalProviderInterface
{
    public static ?\Closure $change = null;

    public function getKey(): string
    {
        return 'price_change_probe';
    }

    public function collect(TotalsContext $ctx): void
    {
        if (self::$change) {
            (self::$change)();
            self::$change = null;
        }
    }

    public function confirm(\App\Models\Order $order, array $line): void
    {
    }
}
