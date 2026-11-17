<?php

namespace Themes\Xelga\Tests\Feature;

use App\Models\Cart;
use App\Models\Country;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesSampleExtension;
use Tests\TestCase;
use Themes\Xelga\Tests\XelgaExtensionHelpers;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';
require_once __DIR__ . '/../XelgaExtensionHelpers.php';

/**
 * Pages an extension draws itself inside Xelga: its account page in the
 * account frame with its menu link, its checkout in the page frame, the
 * panels it adds to the order pages, and the stable snippets.
 */
class XelgaExtensionAccountTest extends TestCase
{
    use RefreshDatabase;
    use UsesSampleExtension;
    use XelgaExtensionHelpers;
    use XelgaTestHelpers;

    private Customer $customer;

    private function signIn(): void
    {
        $this->customer = Customer::create([
            'store_id' => $this->store->id, 'first_name' => 'Maria', 'last_name' => 'Santos',
            'email' => 'maria@example.test', 'password' => bcrypt('secret'), 'is_active' => true,
        ]);
        $this->actingAs($this->customer, 'customer');
    }

    private function order(): Order
    {
        $status = OrderStatus::create(['store_id' => $this->store->id, 'name' => 'Pending', 'is_default' => true, 'sort_order' => 1]);

        return Order::create([
            'store_id' => $this->store->id, 'customer_id' => $this->customer->id, 'order_status_id' => $status->id,
            'first_name' => 'Maria', 'last_name' => 'Santos', 'email' => 'maria@example.test', 'total' => 500,
        ]);
    }

    public function test_the_account_page_draws_in_the_account_frame_with_its_menu_link(): void
    {
        $this->setUpXelgaWithExtension();
        $this->signIn();

        $this->page('/account/sample-things')
            ->assertSee('data-fixed-page="extension-account"', false)
            ->assertSee('<h1 class="acct__title">Sample things</h1>', false)
            ->assertSee('Nothing here yet.')
            ->assertSee('<a href="' . url('/account/sample-things') . '"  aria-current="page" ><svg class="icon icon--sm" aria-hidden="true"><use href="#i-bag"></use></svg><span>Sample things</span></a>', false);

        $this->page('/account/orders')
            ->assertSee('<span>Sample things</span>', false)
            ->assertDontSee('href="' . url('/account/sample-things') . '"  aria-current', false);
    }

    public function test_switched_off_the_menu_link_is_gone(): void
    {
        $this->setUpXelgaWithExtension();
        $this->signIn();
        $this->switchOffSampleExtension();

        $this->page('/account/orders')->assertDontSee('Sample things');
    }

    public function test_the_frames_exist_only_while_xelga_draws_the_store(): void
    {
        $this->setUpXelgaWithExtension();
        $this->page('/');
        $this->assertTrue(view()->exists('extension.page'));
        $this->assertTrue(view()->exists('extension.account'));
        $this->assertTrue(view()->exists('extension-page'));
    }

    public function test_without_xelga_live_the_extension_uses_its_own_frame(): void
    {
        $this->installXelga(live: false);
        $this->installSampleExtension();
        $this->store = \App\Models\Store::firstOrCreate(['code' => 'demo'], ['name' => 'Demo Store']);
        $this->signIn();

        $this->get('/account/sample-things')->assertOk()->assertSee('data-sample-frame="plain"', false);
        $this->assertFalse(view()->exists('extension.account'));
    }

    public function test_the_checkout_the_extension_draws_sits_in_the_page_frame(): void
    {
        $this->setUpXelgaWithExtension();
        $this->signIn();
        $cart = Cart::create(['store_id' => (int) config('app.store_id', 1), 'customer_id' => $this->customer->id]);
        $product = $this->product('Demo Mug');
        $cart->items()->create(['product_id' => $product->id, 'name' => 'Demo Mug', 'price' => 1000, 'quantity' => 1]);

        $this->page('/checkout')
            ->assertSee('data-fixed-page="extension"', false)
            ->assertSee('<h1 class="acct__title">Checkout</h1>', false)
            ->assertSee('<section data-group="demo-store">', false)
            ->assertSee('action="' . route('checkout.place') . '"', false);
    }

    public function test_the_order_pages_draw_the_extensions_panels_in_their_places(): void
    {
        $this->setUpXelgaWithExtension();
        $this->signIn();
        $order = $this->order();

        $this->page('/account/orders')
            ->assertSee('<tr class="orders__panel"><td colspan="6"><p data-sample-row="' . $order->id . '">Sold by Demo Store</p></td></tr>', false);

        $html = $this->page('/account/orders/' . $order->id)
            ->assertSee('<div class="order-panel"><p data-sample-summary>Order ' . $order->id . ' is sold by Demo Store</p></div>', false)
            ->assertSee('data-sample-action', false)
            ->getContent();
        $this->assertTrue(strpos($html, 'data-sample-action') < strpos($html, 'Back to your orders'), 'With the actions, before the way back.');
    }

    public function test_without_panels_the_order_pages_are_as_before(): void
    {
        $this->setUpXelgaWithExtension(install: false);
        $this->signIn();
        $order = $this->order();

        $this->page('/account/orders')->assertDontSee('orders__panel', false);
        $this->page('/account/orders/' . $order->id)->assertDontSee('order-panel', false);
    }

    public function test_the_snippets_draw_their_inputs_as_text(): void
    {
        $this->setUpXelgaWithExtension();
        $ph = Country::create(['iso2' => 'PH', 'iso3' => 'PHL', 'name' => 'Philippines', 'phone_code' => '63', 'is_active' => true]);
        $customer = Customer::create(['store_id' => $this->store->id, 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'email' => 'juan@example.test', 'password' => bcrypt('x')]);
        $address = CustomerAddress::create([
            'customer_id' => $customer->id, 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'address_1' => '1 Demo Street',
            'city' => 'Manila', 'state' => 'Metro Manila', 'postcode' => '1008', 'country_id' => $ph->id, 'phone' => '9170000000',
        ]);

        $status = view('xelga::extension.status', ['name' => '<b>Shipped</b>', 'tone' => 'success'])->render();
        $this->assertStringContainsString('status--ok', $status);
        $this->assertStringContainsString('&lt;b&gt;Shipped&lt;/b&gt;', $status);

        $empty = view('xelga::extension.empty', ['title' => 'No returns', 'text' => 'None yet.', 'link' => ['label' => 'Shop', 'url' => 'javascript:alert(1)']])->render();
        $this->assertStringContainsString('No returns', $empty);
        $this->assertStringNotContainsString('javascript:', $empty);

        $this->assertStringContainsString('1 Demo Street', view('xelga::extension.address', ['address' => $address])->render());
    }
}
