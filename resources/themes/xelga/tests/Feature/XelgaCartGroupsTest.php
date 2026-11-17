<?php

namespace Themes\Xelga\Tests\Feature;

use App\Models\Cart;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesSampleExtension;
use Tests\TestCase;
use Themes\Xelga\Tests\XelgaExtensionHelpers;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';
require_once __DIR__ . '/../XelgaExtensionHelpers.php';

/**
 * The cart page draws the groups core hands it (App\Support\CartGroups)
 * under their headers; without them, the one plain list as before.
 */
class XelgaCartGroupsTest extends TestCase
{
    use RefreshDatabase;
    use UsesSampleExtension;
    use XelgaExtensionHelpers;
    use XelgaTestHelpers;

    private function fillCart(): void
    {
        $customer = Customer::create([
            'store_id' => $this->store->id, 'first_name' => 'Juan', 'last_name' => 'Dela Cruz',
            'email' => 'juan@example.test', 'password' => bcrypt('secret'), 'is_active' => true,
        ]);
        $this->actingAs($customer, 'customer');
        $cart = Cart::create(['store_id' => (int) config('app.store_id', 1), 'customer_id' => $customer->id]);
        foreach (['Demo Mug', 'Plain Cup', 'Demo Bowl'] as $name) {
            $product = $this->product($name);
            if ($name === 'Plain Cup') {
                $product->update(['sku' => 'OTHER-1']);
            }
            $cart->items()->create(['product_id' => $product->id, 'name' => $name, 'price' => 1000, 'quantity' => 1]);
        }
    }

    public function test_without_a_grouper_the_cart_is_one_list(): void
    {
        $this->setUpXelgaWithExtension(install: false);
        $this->fillCart();

        $html = $this->page('/cart')->getContent();

        $this->assertSame(1, substr_count($html, '<ul class="lines lines--page" aria-label="Items in your cart">'));
        $this->assertStringNotContainsString('cart-group', $html);
    }

    public function test_with_the_fixture_grouper_each_group_gets_its_header_and_lines(): void
    {
        $this->setUpXelgaWithExtension();
        $this->fillCart();

        $html = $this->page('/cart')
            ->assertSee('<section class="cart-group" data-cart-group="demo-store"  aria-label="Demo Store" >', false)
            ->assertSee('<a href="https://example.com/shops/demo-store">Demo Store</a>', false)
            ->assertSee('Shipping from this shop')
            ->assertSee(money(120))
            ->assertSee('Ships in 2 days')
            ->assertSee('aria-label="Other items in your cart"', false)
            ->getContent();

        $demo = strpos($html, 'data-cart-group="demo-store"');
        $other = strpos($html, 'data-cart-group="other"');
        $this->assertTrue($demo < strpos($html, 'Demo Mug') && strpos($html, 'Demo Bowl') < $other && $other < strpos($html, 'Plain Cup'));
        $this->assertSame(1, substr_count($html, 'data-cart-group="other"'));
        $this->assertStringNotContainsString('<ul class="lines lines--page" aria-label="Items in your cart">', $html);
    }

    public function test_switched_off_the_cart_is_one_list_again(): void
    {
        $this->setUpXelgaWithExtension();
        $this->fillCart();
        $this->switchOffSampleExtension();

        $this->page('/cart')->assertDontSee('cart-group', false)->assertSee('Demo Mug');
    }
}
