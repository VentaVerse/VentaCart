<?php

namespace Tests\Feature\Storefront;

use App\Models\Cart;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Services\OrderTotals\OrderTotals;
use App\Services\PaymentManager;
use App\Services\PaymentMethods\PaymentMethodInterface;
use App\Services\PaymentMethods\PaymentResult;
use App\Services\Shipping\ShippingProviderInterface;
use App\Services\Shipping\ShippingQuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingCostTestPayment implements PaymentMethodInterface
{
    public function getKey(): string { return 'shipping_cost_test_pay'; }
    public function getBaseKey(): string { return 'shipping_cost_test_pay'; }
    public function getName(): string { return 'Test Pay'; }
    public function getDescription(): string { return ''; }
    public function getImage(): ?string { return null; }
    public function isEnabled(): bool { return true; }
    public function processPayment(Order $order): PaymentResult { return new PaymentResult(true); }
}

class ShippingCostTestProvider implements ShippingProviderInterface
{
    public static bool $down = false;

    public function getKey(): string { return 'testship'; }
    public function getName(): string { return 'Test Shipping'; }
    public function isEnabled(): bool { return true; }

    public function getRates(array $address, array $dimensions, float $weight, float $subtotal): array
    {
        if (self::$down) {
            throw new \RuntimeException('Rate service unreachable.');
        }

        return [['key' => 'testship_standard', 'name' => 'Standard Delivery', 'cost' => 150.0, 'service_type' => 'standard']];
    }

    public function buildShippingMeta(string $quoteKey, array $quoteData): ?array
    {
        return ['provider' => 'testship', 'provider_label' => 'Test Shipping', 'courier_label' => $quoteData['name'], 'quote_key' => $quoteKey];
    }
}

/**
 * The shipping cost on an order always comes from a live quote worked out
 * on the server. The browser posts the cost it showed the shopper, but a
 * posted figure is never charged: a method the live quotes do not offer
 * (unknown, withdrawn, or its provider unreachable) is refused.
 */
class CheckoutShippingCostTest extends TestCase
{
    use RefreshDatabase;

    private array $shippingRegistry;

    private array $paymentRegistry;

    private array $totalsRegistry;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        OrderStatus::create(['store_id' => 1, 'name' => 'Pending', 'is_default' => true, 'sort_order' => 1]);

        $this->shippingRegistry = $this->registry(ShippingQuoteService::class);
        $this->paymentRegistry = $this->registry(PaymentManager::class);
        // Totals providers registered by extensions another test booted would
        // read tables this database does not have; this test needs none.
        $this->totalsRegistry = $this->registry(OrderTotals::class);
        OrderTotals::reset();
        ShippingQuoteService::reset();
        ShippingQuoteService::register(ShippingCostTestProvider::class, 10);
        PaymentManager::reset();
        PaymentManager::register(ShippingCostTestPayment::class);
        ShippingCostTestProvider::$down = false;
    }

    protected function tearDown(): void
    {
        $this->registry(ShippingQuoteService::class, $this->shippingRegistry);
        OrderTotals::reset();
        $this->registry(OrderTotals::class, $this->totalsRegistry);
        PaymentManager::reset();
        $this->registry(PaymentManager::class, $this->paymentRegistry);
        ShippingCostTestProvider::$down = false;
        parent::tearDown();
    }

    public function test_a_method_with_no_live_quote_is_refused_whatever_cost_is_posted(): void
    {
        [$customer, $address] = $this->shopper();

        $this->actingAs($customer, 'customer')
            ->post(route('checkout.place'), [
                'shipping_address_id' => $address->id, 'payment_method' => 'shipping_cost_test_pay',
                'shipping_method' => 'x', 'shipping_cost' => '0',
            ])
            ->assertSessionHasErrors(['checkout' => 'Shipping for this address changed. Choose a delivery option again.']);

        $this->assertSame(0, Order::count());
    }

    public function test_an_unreachable_provider_refuses_instead_of_charging_the_posted_cost(): void
    {
        [$customer, $address] = $this->shopper();
        ShippingCostTestProvider::$down = true;

        $this->actingAs($customer, 'customer')
            ->post(route('checkout.place'), [
                'shipping_address_id' => $address->id, 'payment_method' => 'shipping_cost_test_pay',
                'shipping_method' => 'testship_standard', 'shipping_cost' => '0',
            ])
            ->assertSessionHasErrors(['checkout' => 'Shipping for this address changed. Choose a delivery option again.']);

        $this->assertSame(0, Order::count());
    }

    public function test_the_order_charges_the_live_quote_not_the_posted_cost_or_name(): void
    {
        [$customer, $address] = $this->shopper();

        $this->actingAs($customer, 'customer')
            ->post(route('checkout.place'), [
                'shipping_address_id' => $address->id, 'payment_method' => 'shipping_cost_test_pay',
                'shipping_method' => 'testship_standard', 'shipping_cost' => '0',
                'shipping_method_name' => 'Free Shipping',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $order = Order::sole();
        $this->assertEquals(150.0, (float) $order->shipping_cost);
        $this->assertEquals(650.0, (float) $order->total);
        $this->assertSame('Standard Delivery', $order->shipping_method);
    }

    /** @return array{0: Customer, 1: CustomerAddress} */
    private function shopper(): array
    {
        $customer = Customer::create([
            'store_id' => 1, 'first_name' => 'Juan', 'last_name' => 'Dela Cruz',
            'email' => 'juan@example.test', 'password' => bcrypt('secret'),
        ]);
        $address = CustomerAddress::create([
            'customer_id' => $customer->id, 'first_name' => 'Juan', 'last_name' => 'Dela Cruz',
            'address_1' => '1 Demo St', 'city' => 'Demo City', 'postcode' => '1000', 'phone' => '9170000000',
        ]);
        $product = Product::create([
            'store_id' => 1, 'name' => 'Demo Cable', 'slug' => 'demo-cable', 'sku' => 'DEMO-001',
            'price' => 500, 'quantity' => 5, 'is_active' => true, 'track_stock' => true,
        ]);
        Cart::create(['store_id' => 1, 'customer_id' => $customer->id])->items()->create([
            'product_id' => $product->id, 'name' => 'Demo Cable', 'price' => 500, 'quantity' => 1,
        ]);

        return [$customer, $address];
    }

    /** Reads, or with $value restores, a manager's static registration list. */
    private function registry(string $class, ?array $value = null): array
    {
        $property = new \ReflectionProperty($class, 'registered');
        if ($value !== null) {
            $property->setValue(null, $value);
        }

        return $property->getValue();
    }
}
