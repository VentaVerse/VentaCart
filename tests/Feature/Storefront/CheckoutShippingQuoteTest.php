<?php

namespace Tests\Feature\Storefront;

use App\Models\Cart;
use App\Models\Country;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PaymentMethodRestriction;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Store;
use App\Services\ExtensionManager;
use App\Services\OrderTotals\OrderTotals;
use App\Services\PaymentManager;
use App\Services\Shipping\ShippingQuoteService;
use Extensions\ShippingFlatRate\Models\FlatShippingRate;
use Extensions\ShippingFree\Models\FreeShippingRule;
use Extensions\ShippingWeightBased\Models\WeightBasedMethod;
use Extensions\ShippingWeightBased\Models\WeightBasedRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Checkout charges shipping from a quote the server computes for the cart
 * and the delivery address. The checkout form carries only the chosen
 * method's key: a cost or a name posted beside it is never read, and a key
 * that names no live quote is refused before any order exists.
 */
class CheckoutShippingQuoteTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private CustomerAddress $address;

    private Product $product;

    private FlatShippingRate $standard;

    protected function setUp(): void
    {
        parent::setUp();
        // Providers register into static lists that outlive a test's app;
        // start from none so another test's extensions cannot answer here.
        ShippingQuoteService::reset();
        PaymentManager::reset();
        OrderTotals::reset();
        Mail::fake();

        Store::firstOrCreate(['code' => 'test'], ['name' => 'Demo Store']);
        OrderStatus::create(['store_id' => 1, 'name' => 'Pending', 'sort_order' => 1, 'is_default' => true]);

        $extensions = app(ExtensionManager::class);
        foreach (['shipping-flat-rate', 'shipping-weight-based', 'shipping-free', 'shipping-dhl', 'payment-cod', 'payment-hitpay'] as $name) {
            $extensions->install($name);
        }
        $extensions->bootEnabled();

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
        Cart::create(['store_id' => 1, 'customer_id' => $this->customer->id])->items()->create([
            'product_id' => $this->product->id, 'name' => 'Demo Cable', 'price' => 500, 'quantity' => 2,
        ]);

        $this->standard = FlatShippingRate::create(['name' => 'Standard Delivery', 'cost' => 150, 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        ShippingQuoteService::reset();
        PaymentManager::reset();
        OrderTotals::reset();
        parent::tearDown();
    }

    private function place(array $form)
    {
        return $this->actingAs($this->customer, 'customer')->post(route('checkout.place'), array_merge([
            'shipping_address_id' => $this->address->id,
            'payment_method' => 'cod',
        ], $form));
    }

    // -----------------------------------------------------------------
    // What the form may not decide
    // -----------------------------------------------------------------

    public function test_a_posted_shipping_cost_is_ignored(): void
    {
        $this->place([
            'shipping_method' => 'flat_rate_' . $this->standard->id,
            'shipping_cost' => '0',
            'shipping_method_name' => 'Express',
        ])->assertRedirect();

        $order = Order::sole();
        $this->assertSame(150.0, (float) $order->shipping_cost);
        $this->assertSame(1150.0, (float) $order->total);
        $this->assertSame('Standard Delivery', $order->shipping_method, 'The name is the quote\'s, not the form\'s.');
        $this->assertSame('flat_rate_' . $this->standard->id, $order->shipping_meta['quote_key']);
    }

    public function test_an_unknown_method_with_a_forged_cost_is_refused_and_no_order_is_created(): void
    {
        $this->place([
            'shipping_method' => 'x',
            'shipping_cost' => '0',
            'shipping_method_name' => 'Free Express',
        ])->assertSessionHasErrors([
            'shipping_method' => 'That delivery option is no longer available. Choose one again.',
        ]);

        $this->assertSame(0, Order::count());
        $this->assertSame(1, Cart::sole()->items()->count(), 'The cart is kept for the next try.');
    }

    public function test_a_method_not_quoted_for_this_customer_is_refused(): void
    {
        // A real rate, but for another customer group: this customer is
        // never quoted it, so naming its key buys nothing.
        $resellers = CustomerGroup::create(['store_id' => 1, 'name' => 'Resellers']);
        $resellerRate = FlatShippingRate::create([
            'name' => 'Reseller Pickup', 'cost' => 0, 'customer_group_id' => $resellers->id, 'is_active' => true,
        ]);

        $this->place(['shipping_method' => 'flat_rate_' . $resellerRate->id, 'shipping_cost' => '0'])
            ->assertSessionHasErrors('shipping_method');

        $this->assertSame(0, Order::count());
    }

    public function test_a_disabled_method_is_refused(): void
    {
        $this->standard->update(['is_active' => false]);
        FlatShippingRate::create(['name' => 'Next Day', 'cost' => 300, 'is_active' => true]);

        $this->place(['shipping_method' => 'flat_rate_' . $this->standard->id])
            ->assertSessionHasErrors('shipping_method');

        $this->assertSame(0, Order::count());
    }

    public function test_no_quotes_at_all_is_refused_with_a_try_again_message(): void
    {
        // Every rate source is quiet: a rate service that cannot be reached
        // looks exactly like this, and the order waits rather than trusting
        // a figure from the browser.
        $this->standard->update(['is_active' => false]);

        $this->place(['shipping_method' => 'flat_rate_' . $this->standard->id, 'shipping_cost' => '150'])
            ->assertSessionHasErrors([
                'shipping_method' => 'We could not get delivery options for this address right now. Please try again in a few minutes.',
            ]);

        $this->assertSame(0, Order::count());
    }

    public function test_the_form_fields_for_name_and_cost_are_gone_from_the_checkout_page(): void
    {
        $html = $this->actingAs($this->customer, 'customer')->get(route('checkout.index'))->assertOk()->getContent();

        $this->assertStringContainsString('name="shipping_method"', $html);
        $this->assertStringNotContainsString('name="shipping_cost"', $html);
        $this->assertStringNotContainsString('name="shipping_method_name"', $html);
    }

    // -----------------------------------------------------------------
    // Payment rules keyed on the shipping method
    // -----------------------------------------------------------------

    public function test_a_payment_rule_sees_the_matched_method(): void
    {
        $pickup = FlatShippingRate::create(['name' => 'Store Pickup', 'cost' => 0, 'is_active' => true]);
        PaymentMethodRestriction::create([
            'payment_method' => 'cod', 'is_active' => true,
            'shipping_method_keys' => ['flat_rate_' . $pickup->id],
        ]);

        $this->place(['shipping_method' => 'flat_rate_' . $this->standard->id])
            ->assertSessionHasErrors(['checkout' => 'Selected payment method is not available for this order.']);
        $this->assertSame(0, Order::count());

        $this->place(['shipping_method' => 'flat_rate_' . $pickup->id])->assertSessionHasNoErrors();
        $this->assertSame('Store Pickup', Order::sole()->shipping_method);
    }

    public function test_a_payment_rule_cannot_be_passed_with_an_allowed_key_that_is_not_quoted(): void
    {
        // The allowed method exists but is not offered to this customer.
        // Before, its key plus a posted cost placed a COD order anyway.
        $resellers = CustomerGroup::create(['store_id' => 1, 'name' => 'Resellers']);
        $resellerRate = FlatShippingRate::create([
            'name' => 'Reseller Courier', 'cost' => 50, 'customer_group_id' => $resellers->id, 'is_active' => true,
        ]);
        PaymentMethodRestriction::create([
            'payment_method' => 'cod', 'is_active' => true,
            'shipping_method_keys' => ['flat_rate_' . $resellerRate->id],
        ]);

        $this->place(['shipping_method' => 'flat_rate_' . $resellerRate->id, 'shipping_cost' => '50'])
            ->assertSessionHasErrors('shipping_method');

        $this->assertSame(0, Order::count());
    }

    // -----------------------------------------------------------------
    // Every quoting provider still checks out
    // -----------------------------------------------------------------

    public function test_weight_based_shipping_checks_out_at_its_tier(): void
    {
        $method = WeightBasedMethod::create(['name' => 'Provincial Courier', 'admin_name' => 'Provincial', 'is_active' => true]);
        WeightBasedRate::create(['weight_based_method_id' => $method->id, 'weight_from' => 0, 'weight_to' => 1, 'cost' => 90]);
        WeightBasedRate::create(['weight_based_method_id' => $method->id, 'weight_from' => 1.001, 'weight_to' => 5, 'cost' => 180]);

        $this->place(['shipping_method' => 'weight_based_' . $method->id, 'shipping_cost' => '90'])
            ->assertSessionHasNoErrors();

        $order = Order::sole();
        $this->assertSame(180.0, (float) $order->shipping_cost, 'Two 1 kg items fall in the 1-5 kg tier.');
        $this->assertSame('Provincial Courier', $order->shipping_method);
    }

    public function test_a_free_shipping_rule_checks_out_free_and_replaces_paid_methods(): void
    {
        FreeShippingRule::create(['name' => 'Over 500', 'checkout_name' => 'Free Delivery', 'min_order_total' => 500, 'is_active' => true]);

        // A paid method is not offered while the rule applies.
        $this->place(['shipping_method' => 'flat_rate_' . $this->standard->id])
            ->assertSessionHasErrors('shipping_method');
        $this->assertSame(0, Order::count());

        $this->place(['shipping_method' => 'free_shipping'])->assertSessionHasNoErrors();

        $order = Order::sole();
        $this->assertSame(0.0, (float) $order->shipping_cost);
        $this->assertSame(1000.0, (float) $order->total);
        $this->assertSame('Free Delivery', $order->shipping_method);
    }

    public function test_free_shipping_cannot_be_claimed_below_its_threshold(): void
    {
        FreeShippingRule::create(['name' => 'Over 5000', 'min_order_total' => 5000, 'is_active' => true]);

        $this->place(['shipping_method' => 'free_shipping', 'shipping_cost' => '0'])
            ->assertSessionHasErrors('shipping_method');

        $this->assertSame(0, Order::count());
    }

    public function test_dhl_checks_out_at_the_quoted_price_for_the_saved_postcode(): void
    {
        $sg = Country::create(['iso2' => 'SG', 'iso3' => 'SGP', 'name' => 'Singapore', 'phone_code' => '65', 'is_active' => true]);
        $this->address->update([
            'country_id' => $sg->id, 'state' => null, 'city' => 'Singapore',
            'district' => null, 'barangay' => null, 'postcode' => '018956',
        ]);
        $this->standard->update(['is_active' => false]);

        foreach (['dhl_site_id' => Crypt::encryptString('site'), 'dhl_password' => Crypt::encryptString('pass'), 'dhl_account_number' => '123456789'] as $key => $value) {
            Setting::create(['group' => 'shipping', 'key' => $key, 'value' => $value]);
        }

        Http::fake(['xmlpi-ea.dhl.com/*' => Http::response(
            '<?xml version="1.0"?><res:DCTResponse xmlns:res="http://www.dhl.com"><GetQuoteResponse><BkgDetails>'
            . '<QtdShp><GlobalProductCode>P</GlobalProductCode><ProductShortName>EXPRESS WORLDWIDE</ProductShortName>'
            . '<CurrencyCode>PHP</CurrencyCode><ShippingCharge>2450.00</ShippingCharge></QtdShp>'
            . '</BkgDetails></GetQuoteResponse></res:DCTResponse>'
        )]);

        $this->place(['shipping_method' => 'dhl_p', 'shipping_cost' => '1'])->assertSessionHasNoErrors();

        $order = Order::sole();
        $this->assertSame(2450.0, (float) $order->shipping_cost);
        $this->assertSame('DHL EXPRESS WORLDWIDE', $order->shipping_method);
        $this->assertSame('dhl_express', $order->shipping_meta['provider']);

        // The quote at placement is asked for the same destination the
        // checkout page showed, postcode included.
        Http::assertSent(fn ($request) => str_contains($request->body(), '<Postalcode>018956</Postalcode>'));
    }

    public function test_dhl_unreachable_refuses_instead_of_trusting_the_form(): void
    {
        $sg = Country::create(['iso2' => 'SG', 'iso3' => 'SGP', 'name' => 'Singapore', 'phone_code' => '65', 'is_active' => true]);
        $this->address->update(['country_id' => $sg->id, 'state' => null, 'city' => 'Singapore', 'district' => null, 'barangay' => null]);
        $this->standard->update(['is_active' => false]);
        foreach (['dhl_site_id' => 'site', 'dhl_password' => 'pass', 'dhl_account_number' => '123456789'] as $key => $value) {
            Setting::create(['group' => 'shipping', 'key' => $key, 'value' => $value]);
        }
        Http::fake(['xmlpi-ea.dhl.com/*' => Http::response('', 503)]);

        $this->place(['shipping_method' => 'dhl_p', 'shipping_cost' => '1', 'shipping_method_name' => 'DHL EXPRESS WORLDWIDE'])
            ->assertSessionHasErrors('shipping_method');

        $this->assertSame(0, Order::count());
    }

    // -----------------------------------------------------------------
    // Payment paths unchanged
    // -----------------------------------------------------------------

    public function test_cash_on_delivery_confirms_and_clears_the_cart(): void
    {
        $this->place(['shipping_method' => 'flat_rate_' . $this->standard->id])->assertSessionHasNoErrors();

        $order = Order::sole();
        $this->assertSame('cod', $order->payment_method);
        $this->assertNotNull($order->order_status_id);
        $this->assertSame(0, Cart::sole()->items()->count());
    }

    public function test_online_payment_is_charged_the_quoted_total(): void
    {
        Setting::create(['group' => 'payment', 'key' => 'hitpay_mode', 'value' => 'sandbox']);
        Setting::create(['group' => 'payment', 'key' => 'hitpay_sandbox_api_key', 'value' => Crypt::encryptString('k')]);
        Http::fake(['*/payment-requests' => Http::response(['id' => 'pr_demo', 'url' => 'https://pay.example.test/pr_demo'], 201)]);

        $this->place([
            'payment_method' => 'hitpay',
            'shipping_method' => 'flat_rate_' . $this->standard->id,
            'shipping_cost' => '0',
        ])->assertRedirect('https://pay.example.test/pr_demo');

        $order = Order::sole();
        $this->assertSame(1150.0, (float) $order->total);
        Http::assertSent(fn ($request) => ($request['amount'] ?? null) === '1150.00');
        $this->assertSame(1, Cart::sole()->items()->count(), 'The cart waits for the payment to be confirmed.');
    }
}
