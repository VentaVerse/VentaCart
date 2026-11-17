<?php

namespace Tests\Feature\Payments;

use App\Models\Cart;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Services\ExtensionManager;
use App\Services\OrderStatusChanger;
use App\Services\OrderTotals\OrderTotals;
use App\Services\PaymentManager;
use App\Services\Shipping\ShippingQuoteService;
use App\Services\UnpaidOrderAbandonment;
use Extensions\StoreCredit\CreditService;
use Extensions\StoreCredit\Models\CustomerTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Store credit is taken off an order the moment it is written, before the
 * shopper pays. An order that is then never paid (the payment failed or
 * expired, the shopper came back without paying, the gateway refused it,
 * the merchant deleted it) gives the credit back, exactly once, whichever
 * of those paths gets there first. An order that is paid after all takes
 * it again.
 */
class UnpaidOrderStoreCreditTest extends TestCase
{
    use RefreshDatabase;

    private const SALT = 'test-salt';

    private Customer $customer;

    private OrderStatus $paid;

    protected function setUp(): void
    {
        parent::setUp();
        OrderTotals::reset();
        PaymentManager::reset();
        ShippingQuoteService::reset();
        Mail::fake();

        Store::firstOrCreate(['code' => 'test'], ['name' => 'Demo Store']);
        OrderStatus::create(['store_id' => 1, 'name' => 'Pending', 'sort_order' => 1, 'is_default' => true]);
        $this->paid = OrderStatus::create(['store_id' => 1, 'name' => 'Processing', 'sort_order' => 2]);

        $manager = app(ExtensionManager::class);
        $manager->install('store-credit');
        $manager->install('payment-hitpay');
        $manager->bootEnabled();

        Setting::create(['group' => 'payment', 'key' => 'hitpay_mode', 'value' => 'sandbox']);
        Setting::create(['group' => 'payment', 'key' => 'hitpay_sandbox_api_key', 'value' => Crypt::encryptString('k')]);
        Setting::create(['group' => 'payment', 'key' => 'hitpay_sandbox_salt', 'value' => Crypt::encryptString(self::SALT)]);
        Setting::create(['group' => 'payment', 'key' => 'hitpay_success_status_id', 'value' => (string) $this->paid->id]);

        $this->customer = Customer::create([
            'store_id' => 1, 'first_name' => 'Juan', 'last_name' => 'Dela Cruz',
            'email' => 'juan@example.test', 'password' => bcrypt('secret'),
        ]);
        CreditService::add($this->customer->id, 500.0, 'Welcome credit');
    }

    protected function tearDown(): void
    {
        OrderTotals::reset();
        PaymentManager::reset();
        ShippingQuoteService::reset();
        parent::tearDown();
    }

    /**
     * An unpaid HitPay order, written the way checkout writes it: the
     * credit line on the order and its spend in the ledger.
     */
    private function unpaidOrder(float $credit = 200.0, string $reference = 'pr_demo_1'): Order
    {
        $lines = $credit > 0
            ? [['code' => 'store_credit', 'label' => 'Store Credit', 'amount' => -$credit, 'extras' => ['customer_id' => $this->customer->id]]]
            : [];

        $order = Order::create([
            'store_id' => 1, 'customer_id' => $this->customer->id,
            'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'email' => 'juan@example.test',
            'order_status_id' => null,
            'payment_method' => 'hitpay', 'payment_reference' => $reference,
            'subtotal' => 1000, 'shipping_cost' => 0, 'total' => 1000 - $credit,
            'total_lines' => $lines,
        ]);
        OrderTotals::confirm($order, $lines);

        return $order;
    }

    /** @return array<string, string> */
    private function signed(array $data): array
    {
        $source = [];
        foreach ($data as $key => $val) {
            $source[$key] = "{$key}{$val}";
        }
        ksort($source);
        $data['hmac'] = hash_hmac('sha256', implode('', array_values($source)), self::SALT);

        return $data;
    }

    private function webhook(Order $order, string $status)
    {
        return $this->post(route('hitpay.webhook'), $this->signed([
            'payment_request_id' => (string) $order->payment_reference,
            'reference_number' => (string) $order->id,
            'status' => $status,
            'amount' => number_format((float) $order->total, 2, '.', ''),
        ]));
    }

    private function balance(): float
    {
        return CreditService::balance($this->customer->id);
    }

    private function returnsFor(Order $order)
    {
        return CustomerTransaction::where('order_id', $order->id)->where('amount', '>', 0)->get();
    }

    public function test_a_failed_payment_returns_the_credit(): void
    {
        $order = $this->unpaidOrder(200.0);
        $this->assertSame(300.0, $this->balance());

        $this->webhook($order, 'failed')->assertOk();

        $this->assertSame(500.0, $this->balance());
        $returned = $this->returnsFor($order);
        $this->assertCount(1, $returned);
        $this->assertSame(200.0, (float) $returned[0]->amount);
        $this->assertSame("Order #{$order->id} not paid, store credit returned", $returned[0]->description);
        $this->assertTrue($order->histories()->where('comment', 'like', '%store credit%returned%')->exists());
    }

    public function test_an_expired_payment_returns_the_credit(): void
    {
        $order = $this->unpaidOrder(200.0);

        $this->webhook($order, 'expired')->assertOk();

        $this->assertSame(500.0, $this->balance());
        $this->assertCount(1, $this->returnsFor($order));
    }

    public function test_a_repeated_webhook_returns_the_credit_once(): void
    {
        $order = $this->unpaidOrder(200.0);

        $this->webhook($order, 'failed')->assertOk();
        $this->webhook($order, 'expired')->assertOk();
        $this->webhook($order, 'failed')->assertOk();

        $this->assertSame(500.0, $this->balance());
        $this->assertCount(1, $this->returnsFor($order));
    }

    /**
     * The webhook, the shopper's browser and any other path can each hold
     * a copy of the order read before the others wrote. Each decides under
     * the order's row lock from the ledger as it stands, so the second
     * finds nothing left to return.
     */
    public function test_racing_paths_holding_stale_copies_return_the_credit_once(): void
    {
        $order = $this->unpaidOrder(200.0);
        $first = Order::find($order->id);
        $second = Order::find($order->id);

        $this->assertTrue(UnpaidOrderAbandonment::abandon($first));
        $this->assertTrue(UnpaidOrderAbandonment::abandon($second));
        $this->actingAs($this->customer, 'customer')
            ->get(route('checkout.hitpay.return', ['reference' => 'pr_demo_1', 'status' => 'canceled']));
        $this->webhook($order, 'failed')->assertOk();

        $this->assertSame(500.0, $this->balance());
        $this->assertCount(1, $this->returnsFor($order));
    }

    public function test_coming_back_without_paying_returns_the_credit(): void
    {
        $order = $this->unpaidOrder(200.0);

        $this->actingAs($this->customer, 'customer')
            ->get(route('checkout.hitpay.return', ['reference' => 'pr_demo_1', 'status' => 'canceled']))
            ->assertRedirect(route('checkout.index'));

        $this->assertSame(500.0, $this->balance());
    }

    public function test_an_order_without_credit_is_unaffected(): void
    {
        $order = $this->unpaidOrder(0.0);
        $cart = Cart::create(['store_id' => 1, 'customer_id' => $this->customer->id, 'placed_order_id' => $order->id]);

        $this->webhook($order, 'failed')->assertOk();

        $this->assertSame(500.0, $this->balance());
        $this->assertSame(0, CustomerTransaction::where('order_id', $order->id)->count());
        $this->assertNull($cart->fresh()->placed_order_id, 'The cart still lets go of the order.');
        $this->assertFalse($order->histories()->where('comment', 'like', '%store credit%')->exists());
    }

    public function test_a_paid_order_is_never_given_its_credit_back(): void
    {
        $order = $this->unpaidOrder(200.0);
        OrderStatusChanger::apply($order, $this->paid->id, 'Paid.');

        $this->webhook($order->fresh(), 'failed')->assertOk();
        $this->assertFalse(UnpaidOrderAbandonment::abandon($order->fresh()));
        $this->actingAs($this->customer, 'customer')
            ->get(route('checkout.hitpay.return', ['reference' => 'pr_demo_1', 'status' => 'canceled']));

        $this->assertSame(300.0, $this->balance());
        $this->assertCount(0, $this->returnsFor($order));
    }

    public function test_credit_already_partly_returned_gives_back_only_the_rest(): void
    {
        $order = $this->unpaidOrder(200.0);
        CreditService::add($this->customer->id, 50.0, 'Goodwill for order', $order->id);

        $this->webhook($order, 'failed')->assertOk();

        $this->assertSame(500.0, $this->balance());
        $this->assertSame(150.0, (float) CustomerTransaction::where('order_id', $order->id)
            ->where('description', "Order #{$order->id} not paid, store credit returned")->value('amount'));
    }

    /**
     * Code outside core (a marketplace, say) may already have given the
     * credit back through its own ledger entries for the order. What the
     * order still holds is read from the ledger, so nothing is returned
     * twice.
     */
    public function test_credit_another_extension_already_returned_is_not_returned_again(): void
    {
        $order = $this->unpaidOrder(200.0);
        CreditService::add($this->customer->id, 200.0, 'Seller order not paid, credit returned', $order->id);

        $this->assertTrue(UnpaidOrderAbandonment::abandon($order));
        $this->webhook($order, 'expired')->assertOk();

        $this->assertSame(500.0, $this->balance());
        $this->assertCount(1, $this->returnsFor($order));
        $this->assertFalse($order->histories()->where('comment', 'like', '%store credit%')->exists());
    }

    /**
     * A failed attempt does not close a HitPay payment page: the shopper
     * may try another card and pay. The order then takes again the credit
     * it gave back, so the discount is never had twice.
     */
    public function test_an_order_paid_after_its_credit_was_returned_takes_it_again(): void
    {
        $order = $this->unpaidOrder(200.0);
        $this->webhook($order, 'failed')->assertOk();
        $this->assertSame(500.0, $this->balance());

        Http::fake(['*/payment-requests/pr_demo_1' => Http::response([
            'id' => 'pr_demo_1', 'status' => 'completed', 'reference_number' => (string) $order->id,
            'amount' => '800.00', 'payments' => [['id' => 'p1', 'status' => 'succeeded', 'fees' => '10.00']],
        ], 200)]);
        $this->webhook($order, 'completed')->assertOk();
        $this->webhook($order, 'completed')->assertOk();

        $this->assertSame($this->paid->id, (int) $order->fresh()->order_status_id);
        $this->assertSame(300.0, $this->balance());
        $this->assertSame(1, CustomerTransaction::where('order_id', $order->id)
            ->where('description', "Order #{$order->id} paid, store credit applied again")->count());
    }

    public function test_an_order_paid_without_ever_being_abandoned_is_charged_once(): void
    {
        $order = $this->unpaidOrder(200.0);

        $this->assertTrue(OrderStatusChanger::claimStatus($order, $this->paid->id, 'Paid.'));

        $this->assertSame(300.0, $this->balance());
        $this->assertSame(1, CustomerTransaction::where('order_id', $order->id)->count());
    }

    /** Only a confirmed payment takes the credit again, never a status the merchant sets. */
    public function test_cancelling_an_abandoned_order_by_hand_does_not_spend_the_credit_again(): void
    {
        $canceled = OrderStatus::create(['store_id' => 1, 'name' => 'Canceled', 'sort_order' => 7]);
        $order = $this->unpaidOrder(200.0);
        $this->webhook($order, 'expired')->assertOk();

        OrderStatusChanger::apply($order->fresh(), $canceled->id, 'Canceled by the store.');

        $this->assertSame(500.0, $this->balance());
        $this->assertSame(0, CustomerTransaction::where('order_id', $order->id)
            ->where('description', 'like', '%applied again%')->count());
    }

    public function test_deleting_an_unpaid_order_returns_its_credit(): void
    {
        $order = $this->unpaidOrder(200.0);
        $admin = User::factory()->create();

        $this->actingAs($admin)->delete(route('admin.orders.destroy', $order));

        $this->assertNull(Order::find($order->id));
        $this->assertSame(500.0, $this->balance());
    }

    public function test_deleting_a_placed_order_leaves_the_credit_to_the_merchant(): void
    {
        $order = $this->unpaidOrder(200.0);
        OrderStatusChanger::apply($order, $this->paid->id, 'Paid.');
        $admin = User::factory()->create();

        $this->actingAs($admin)->delete(route('admin.orders.destroy', $order));

        $this->assertNull(Order::find($order->id));
        $this->assertSame(300.0, $this->balance());
    }

    public function test_an_abandoned_order_stays_out_of_the_customers_view(): void
    {
        $order = $this->unpaidOrder(200.0);

        $this->webhook($order, 'failed')->assertOk();

        $fresh = $order->fresh();
        $this->assertNull($fresh->order_status_id);
        $this->assertNull($fresh->order_status_name);
        $this->assertSame(0, Order::visibleToCustomer()->count());
    }

    public function test_a_payment_the_gateway_refused_at_checkout_returns_the_credit(): void
    {
        $manager = app(ExtensionManager::class);
        $manager->install('shipping-flat-rate');
        $manager->bootEnabled();
        $rate = \Extensions\ShippingFlatRate\Models\FlatShippingRate::create(['name' => 'Standard Delivery', 'cost' => 150, 'is_active' => true]);

        $ph = Country::create(['iso2' => 'PH', 'iso3' => 'PHL', 'name' => 'Philippines', 'phone_code' => '63', 'is_active' => true]);
        $address = $this->customer->addresses()->create([
            'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'address_1' => '1 Demo Street',
            'country_id' => $ph->id, 'state' => 'Metro Manila', 'city' => 'Manila',
            'district' => 'Sampaloc', 'barangay' => 'Barangay 556', 'postcode' => '1008', 'phone' => '9170000000',
            'is_default' => true,
        ]);
        $product = Product::create([
            'store_id' => 1, 'name' => 'Demo Cable', 'slug' => 'demo-cable', 'sku' => 'DEMO-001',
            'price' => 500, 'quantity' => 20, 'weight' => 1, 'is_active' => true, 'track_stock' => true,
        ]);
        $cart = Cart::create(['store_id' => 1, 'customer_id' => $this->customer->id]);
        $cart->items()->create(['product_id' => $product->id, 'name' => 'Demo Cable', 'price' => 500, 'quantity' => 2]);

        Http::fake(['*/payment-requests' => Http::response(['message' => 'Temporarily unavailable'], 503)]);

        $this->actingAs($this->customer, 'customer')
            ->from(route('checkout.index'))
            ->post(route('checkout.place'), [
                'shipping_address_id' => $address->id,
                'payment_method' => 'hitpay',
                'shipping_method' => 'flat_rate_' . $rate->id,
            ])
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHasErrors('checkout');

        $order = Order::sole();
        $this->assertNull($order->order_status_id);
        $this->assertSame(-500.0, (float) collect($order->total_lines)->firstWhere('code', 'store_credit')['amount']);
        $this->assertSame(500.0, $this->balance(), 'The refused order spent the credit and gave it back.');
        $this->assertCount(1, $this->returnsFor($order));
        $this->assertNull($cart->fresh()->placed_order_id);
    }
}
