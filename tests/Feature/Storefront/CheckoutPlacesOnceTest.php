<?php

namespace Tests\Feature\Storefront;

use App\Models\Cart;
use App\Models\Country;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Store;
use App\Services\CheckoutService;
use App\Services\ExtensionManager;
use App\Services\OrderTotals\OrderTotalProviderInterface;
use App\Services\OrderTotals\OrderTotals;
use App\Services\OrderTotals\TotalsContext;
use App\Services\PaymentManager;
use App\Services\Shipping\ShippingQuoteService;
use Extensions\ShippingFlatRate\Models\FlatShippingRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * One cart becomes one order, however many times Place order arrives for
 * it: a double click, a second tab, a browser retrying on a slow network.
 * A repeat is sent to the order the first submit made. A shopper who comes
 * back from a payment that failed or was cancelled still places again, and
 * gets a new order and a new payment, as before.
 */
class CheckoutPlacesOnceTest extends TestCase
{
    use RefreshDatabase;

    private const BEING_PLACED = 'Your order is already being placed. Please wait a moment, then check your orders before trying again.';

    private Customer $customer;

    private CustomerAddress $address;

    private Product $product;

    private Cart $cart;

    private FlatShippingRate $standard;

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
        foreach (['shipping-flat-rate', 'payment-cod', 'payment-hitpay'] as $name) {
            $extensions->install($name);
        }
        $extensions->bootEnabled();

        Setting::create(['group' => 'payment', 'key' => 'hitpay_mode', 'value' => 'sandbox']);
        Setting::create(['group' => 'payment', 'key' => 'hitpay_sandbox_api_key', 'value' => Crypt::encryptString('k')]);

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
        $this->cart->items()->create([
            'product_id' => $this->product->id, 'name' => 'Demo Cable', 'price' => 500, 'quantity' => 2,
        ]);

        $this->standard = FlatShippingRate::create(['name' => 'Standard Delivery', 'cost' => 150, 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        ShippingQuoteService::reset();
        PaymentManager::reset();
        OrderTotals::reset();
        RunsDuringTotals::$run = null;
        parent::tearDown();
    }

    private function form(array $overrides = []): array
    {
        return array_merge([
            'shipping_address_id' => $this->address->id,
            'payment_method' => 'cod',
            'shipping_method' => 'flat_rate_'.$this->standard->id,
        ], $overrides);
    }

    private function place(array $overrides = [])
    {
        return $this->actingAs($this->customer, 'customer')
            ->from(route('checkout.index'))
            ->post(route('checkout.place'), $this->form($overrides));
    }

    /** HitPay opens a new payment page for each payment request it is asked for. */
    private function hitpayOpensPages(): void
    {
        $count = 0;
        Http::fake(['*/payment-requests' => function () use (&$count) {
            $count++;

            return Http::response(['id' => 'pr_'.$count, 'url' => 'https://pay.example.test/pr_'.$count], 201);
        }]);
    }

    /**
     * A second placement for the same cart that runs to the end while
     * this request is still working out its totals: what a second tab or
     * a retried request does when it overtakes the first.
     */
    private function anotherRequestPlacesMidway(array $overrides = []): void
    {
        RunsDuringTotals::$run = function () use ($overrides) {
            // The overtaking request does not wait for this one's lock.
            config(['checkout.placement.wait_seconds' => 0]);
            $form = $this->form($overrides);

            app(CheckoutService::class)->placeOrder(
                customer: $this->customer,
                shippingAddress: $this->address,
                billingAddress: null,
                paymentMethodKey: $form['payment_method'],
                shippingMethodKey: $form['shipping_method'],
                cart: Cart::find($this->cart->id),
            );
        };
        OrderTotals::register(RunsDuringTotals::class, 1);
    }

    // -----------------------------------------------------------------
    // Cash on delivery
    // -----------------------------------------------------------------

    public function test_a_single_cash_on_delivery_submit_places_as_before(): void
    {
        $this->place()->assertSessionHasNoErrors()->assertRedirect(route('checkout.confirmation', Order::sole()));

        $order = Order::sole();
        $this->assertNotNull($order->order_status_id);
        $this->assertSame(1150.0, (float) $order->total);
        $this->assertNull($order->payment_url);
        $this->assertSame(0, $this->cart->items()->count());
        $this->assertSame($order->id, (int) $this->cart->fresh()->placed_order_id);
    }

    public function test_a_repeated_cash_on_delivery_submit_is_sent_to_the_order_it_made(): void
    {
        $this->place()->assertSessionHasNoErrors();
        $order = Order::sole();

        $this->place()
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('checkout.confirmation', $order));

        $this->assertSame(1, Order::count(), 'One cart, one order.');
        $this->assertSame(1, $order->products()->count());
    }

    public function test_a_cash_on_delivery_submit_overtaken_by_another_is_sent_to_that_order(): void
    {
        $this->anotherRequestPlacesMidway();

        $response = $this->place();

        $order = Order::sole();
        $response->assertSessionHasNoErrors()->assertRedirect(route('checkout.confirmation', $order));
        $this->assertNotNull($order->order_status_id);
        $this->assertSame(0, $this->cart->items()->count());
    }

    public function test_a_second_tab_with_another_payment_method_does_not_make_a_second_order(): void
    {
        $this->hitpayOpensPages();
        $this->anotherRequestPlacesMidway(['payment_method' => 'cod']);

        $response = $this->place(['payment_method' => 'hitpay']);

        $order = Order::sole();
        $this->assertSame('cod', $order->payment_method);
        $response->assertRedirect(route('checkout.confirmation', $order));
        Http::assertNothingSent();
    }

    public function test_a_repeat_long_after_the_order_finds_an_empty_cart(): void
    {
        $this->place()->assertSessionHasNoErrors();

        $this->travel(11)->minutes();

        $this->place()->assertRedirect(route('cart.index'))->assertSessionHas('error', 'Your cart is empty.');
        $this->assertSame(1, Order::count());
    }

    public function test_the_cart_places_a_new_order_once_it_is_filled_again(): void
    {
        $this->place()->assertSessionHasNoErrors();

        $this->actingAs($this->customer, 'customer')
            ->postJson(route('cart.add'), ['product_id' => $this->product->id, 'quantity' => 1])
            ->assertOk();

        $this->place()->assertSessionHasNoErrors();

        $this->assertSame(2, Order::count());
        $second = Order::latest('id')->first();
        $this->assertSame(650.0, (float) $second->total);
        $this->assertSame($second->id, (int) $this->cart->fresh()->placed_order_id);
    }

    // -----------------------------------------------------------------
    // An order still being placed
    // -----------------------------------------------------------------

    public function test_a_submit_while_the_first_is_still_placing_is_refused_and_places_nothing(): void
    {
        // Written, linked, and its payment step not finished yet.
        $first = Order::create(['store_id' => 1, 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'email' => 'juan@example.test', 'customer_id' => $this->customer->id, 'total' => 1150, 'payment_method' => 'hitpay']);
        Cart::whereKey($this->cart->id)->update(['placed_order_id' => $first->id]);

        $this->place()
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHasErrors(['checkout' => self::BEING_PLACED]);
        $this->assertSame(1, Order::count());
        $this->assertSame(1, $this->cart->items()->count());
    }

    public function test_a_placement_that_died_does_not_hold_the_cart_for_ever(): void
    {
        $first = Order::create(['store_id' => 1, 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'email' => 'juan@example.test', 'customer_id' => $this->customer->id, 'total' => 1150, 'payment_method' => 'cod']);
        Cart::whereKey($this->cart->id)->update(['placed_order_id' => $first->id]);

        $this->travel(2)->minutes();

        $this->place()->assertSessionHasNoErrors();
        $this->assertSame(2, Order::count());
    }

    public function test_a_submit_that_cannot_get_the_lock_is_still_decided_by_the_database(): void
    {
        config(['checkout.placement.wait_seconds' => 0]);
        $held = Cache::lock('checkout-placement:'.$this->cart->id, 90);
        $this->assertTrue($held->get());

        try {
            $this->place()->assertSessionHasNoErrors();
            $this->place()->assertRedirect(route('checkout.confirmation', Order::sole()));
        } finally {
            $held->release();
        }

        $this->assertSame(1, Order::count());
    }

    // -----------------------------------------------------------------
    // HitPay: the cart is kept until the payment is confirmed
    // -----------------------------------------------------------------

    public function test_a_single_hitpay_submit_opens_one_payment_as_before(): void
    {
        $this->hitpayOpensPages();

        $this->place(['payment_method' => 'hitpay'])->assertRedirect('https://pay.example.test/pr_1');

        $order = Order::sole();
        $this->assertNull($order->order_status_id);
        $this->assertSame('pr_1', $order->payment_reference);
        $this->assertSame('https://pay.example.test/pr_1', $order->payment_url);
        $this->assertSame(1, $this->cart->items()->count(), 'The cart waits for the payment.');
    }

    public function test_a_repeated_hitpay_submit_goes_back_to_the_same_payment(): void
    {
        $this->hitpayOpensPages();

        $this->place(['payment_method' => 'hitpay'])->assertRedirect('https://pay.example.test/pr_1');
        $this->place(['payment_method' => 'hitpay'])->assertRedirect('https://pay.example.test/pr_1');

        $this->assertSame(1, Order::count(), 'One unpaid order, not two.');
        Http::assertSentCount(1);
    }

    public function test_a_hitpay_submit_overtaken_by_another_goes_to_that_payment(): void
    {
        $this->hitpayOpensPages();
        $this->anotherRequestPlacesMidway(['payment_method' => 'hitpay']);

        $this->place(['payment_method' => 'hitpay'])->assertRedirect('https://pay.example.test/pr_1');

        $this->assertSame(1, Order::count());
        Http::assertSentCount(1);
    }

    public function test_coming_back_from_a_cancelled_payment_and_placing_again_makes_a_new_order(): void
    {
        $this->hitpayOpensPages();
        $this->place(['payment_method' => 'hitpay'])->assertRedirect('https://pay.example.test/pr_1');

        $this->actingAs($this->customer, 'customer')
            ->get(route('checkout.hitpay.return', ['reference' => 'pr_1', 'status' => 'canceled']))
            ->assertRedirect(route('checkout.index'));

        $this->place(['payment_method' => 'hitpay'])->assertRedirect('https://pay.example.test/pr_2');

        $this->assertSame(2, Order::count(), 'A new order for the retry, as before.');
        $retry = Order::latest('id')->first();
        $this->assertSame('pr_2', $retry->payment_reference);
        $this->assertSame($retry->id, (int) $this->cart->fresh()->placed_order_id);
    }

    public function test_a_payment_the_gateway_refused_can_be_tried_again_at_once(): void
    {
        $attempt = 0;
        Http::fake(['*/payment-requests' => function () use (&$attempt) {
            $attempt++;

            return $attempt === 1
                ? Http::response(['message' => 'Temporarily unavailable'], 503)
                : Http::response(['id' => 'pr_ok', 'url' => 'https://pay.example.test/pr_ok'], 201);
        }]);

        $this->place(['payment_method' => 'hitpay'])
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHasErrors(['checkout' => 'Payment failed: Temporarily unavailable']);
        $this->assertNull($this->cart->fresh()->placed_order_id, 'The refused order lets go of the cart.');

        $this->place(['payment_method' => 'hitpay'])->assertRedirect('https://pay.example.test/pr_ok');
        $this->assertSame(2, Order::count(), 'As before: the refused attempt stays, the retry is new.');
    }

    public function test_changing_a_choice_after_leaving_the_payment_page_places_a_new_order(): void
    {
        $this->hitpayOpensPages();
        $this->place(['payment_method' => 'hitpay'])->assertRedirect('https://pay.example.test/pr_1');

        // Back from the payment page with the browser's Back button, a note added.
        $this->place(['payment_method' => 'hitpay', 'comment' => 'Please call before delivery.'])
            ->assertRedirect('https://pay.example.test/pr_2');

        $this->assertSame(2, Order::count());
        $this->assertSame('Please call before delivery.', Order::latest('id')->first()->comment);
    }

    public function test_switching_to_cash_on_delivery_after_leaving_the_payment_page_places_it(): void
    {
        $this->hitpayOpensPages();
        $this->place(['payment_method' => 'hitpay'])->assertRedirect('https://pay.example.test/pr_1');

        $this->place(['payment_method' => 'cod'])->assertSessionHasNoErrors();

        $cod = Order::latest('id')->first();
        $this->assertSame('cod', $cod->payment_method);
        $this->assertNotNull($cod->order_status_id);
        $this->assertSame(0, $this->cart->items()->count());
    }

    public function test_a_paid_hitpay_order_is_shown_to_a_late_repeat(): void
    {
        $this->hitpayOpensPages();
        $this->place(['payment_method' => 'hitpay']);
        $order = Order::sole();

        // HitPay confirms the payment; the webhook marks it and empties the cart.
        $order->forceFill(['order_status_id' => OrderStatus::value('id')])->save();
        $this->cart->items()->delete();

        $this->place(['payment_method' => 'hitpay'])->assertRedirect(route('checkout.confirmation', $order));
        $this->assertSame(1, Order::count());
        Http::assertSentCount(1);
    }

    // -----------------------------------------------------------------
    // The price check is unchanged
    // -----------------------------------------------------------------

    public function test_a_price_change_still_refuses_and_a_repeated_submit_then_places_one_order(): void
    {
        $this->product->update(['price' => 520]);

        $this->place()->assertSessionHasErrors(['checkout' => 'Some prices in your cart changed since you added them. Please review your order before placing it. Updated: Demo Cable.']);
        $this->assertSame(0, Order::count());
        $this->assertNull($this->cart->fresh()->placed_order_id);

        $this->place()->assertSessionHasNoErrors();
        $this->place()->assertRedirect(route('checkout.confirmation', Order::sole()));
        $this->assertSame(1190.0, (float) Order::sole()->total);
    }

    public function test_another_customers_order_is_never_used_as_a_repeat(): void
    {
        $other = Customer::create([
            'store_id' => 1, 'first_name' => 'Maria', 'last_name' => 'Santos',
            'email' => 'maria@example.test', 'password' => bcrypt('secret'),
        ]);
        $theirs = Order::create(['store_id' => 1, 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'email' => 'juan@example.test', 'customer_id' => $other->id, 'total' => 1, 'payment_method' => 'cod', 'order_status_id' => OrderStatus::value('id')]);
        Cart::whereKey($this->cart->id)->update(['placed_order_id' => $theirs->id]);

        $this->place()->assertSessionHasNoErrors();

        $mine = Order::where('customer_id', $this->customer->id)->sole();
        $this->assertSame(1150.0, (float) $mine->total);
    }
}

/**
 * A totals step that runs something while this request works out its
 * totals, standing in for another request that overtakes it.
 */
class RunsDuringTotals implements OrderTotalProviderInterface
{
    public static ?\Closure $run = null;

    public function getKey(): string
    {
        return 'overtaking_request_probe';
    }

    public function collect(TotalsContext $ctx): void
    {
        if (self::$run) {
            $run = self::$run;
            self::$run = null;
            $run();
        }
    }

    public function confirm(\App\Models\Order $order, array $line): void {}
}
