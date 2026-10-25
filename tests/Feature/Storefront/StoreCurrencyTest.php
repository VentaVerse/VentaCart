<?php

namespace Tests\Feature\Storefront;

use App\Models\Currency;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Amounts print in the store's own currency, never a hardcoded one, and an
 * order's amounts are not restated in the shopper's display currency: they
 * are what was charged.
 */
class StoreCurrencyTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'rate' => 1, 'is_default' => true, 'is_enabled' => true]);
        Currency::create(['code' => 'EUR', 'name' => 'Euro', 'symbol' => 'EUR ', 'rate' => 0.5, 'is_default' => false, 'is_enabled' => true]);

        $this->customer = Customer::create([
            'store_id' => 1, 'first_name' => 'Maria', 'last_name' => 'Santos', 'email' => 'maria@example.com',
            'password' => bcrypt('secret-password'),
        ]);
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Pending', 'sort_order' => 1, 'is_default' => true]);
        $this->order = Order::create([
            'order_status_id' => $status->id,
            'store_id' => 1, 'customer_id' => $this->customer->id, 'first_name' => 'Maria', 'last_name' => 'Santos',
            'email' => 'maria@example.com', 'payment_method' => 'cod',
            'subtotal' => 1234.5, 'shipping_cost' => 150, 'total' => 1384.5,
        ]);
        OrderProduct::create([
            'order_id' => $this->order->id, 'name' => 'Demo Kettle', 'sku' => 'DEMO-001',
            'price' => 617.25, 'quantity' => 2, 'total' => 1234.5,
        ]);
    }

    public function test_the_order_pages_print_the_store_currency_unconverted(): void
    {
        $this->actingAs($this->customer, 'customer')->withUnencryptedCookie('currency', 'EUR');

        foreach ([
            route('checkout.confirmation', $this->order->id),
            route('account.orders.show', $this->order->id),
            route('account.orders'),
        ] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('$1,384.50', $html, $url);
            $this->assertStringNotContainsString('₱', $html, $url);
            $this->assertStringNotContainsString('EUR 692.25', $html, $url);
        }

        $this->assertStringContainsString('$1,234.50', $this->get(route('checkout.confirmation', $this->order->id))->getContent());
    }

    public function test_the_default_theme_and_core_views_name_no_currency(): void
    {
        $files = array_merge(
            File::allFiles(resource_path('themes/venta')),
            File::allFiles(resource_path('views')),
        );

        foreach ($files as $file) {
            $text = $file->getContents();
            $this->assertStringNotContainsString('₱', $text, $file->getRelativePathname() . ' prints a hardcoded peso.');
            $this->assertDoesNotMatchRegularExpression('/[\'"]PHP[\'"]/', $text, $file->getRelativePathname() . ' names a hardcoded currency code.');
        }
    }
}
