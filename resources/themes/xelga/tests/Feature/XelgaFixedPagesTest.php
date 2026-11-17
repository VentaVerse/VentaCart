<?php

namespace Themes\Xelga\Tests\Feature;

use App\Models\Country;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Services\ExtensionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';

/**
 * Sign-in, account, checkout and review pages keep a fixed layout styled by
 * the theme, and keep every core form, field name, route, check and flow.
 */
class XelgaFixedPagesTest extends TestCase
{
    use RefreshDatabase;
    use XelgaTestHelpers;

    private Customer $customer;

    private Country $country;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installXelga();

        $group = CustomerGroup::firstOrCreate(['store_id' => 1, 'name' => 'Retail'], ['is_default' => true]);
        $this->customer = Customer::create([
            'store_id' => 1, 'customer_group_id' => $group->id, 'first_name' => 'Maria', 'last_name' => 'Santos',
            'email' => 'maria@example.test', 'password' => bcrypt('secret-pass'),
        ]);
        $this->country = Country::firstOrCreate(['iso2' => 'XT'], ['name' => 'Testland', 'iso3' => 'XTL', 'phone_code' => '99', 'is_active' => true]);
    }

    private function address(array $overrides = []): CustomerAddress
    {
        return CustomerAddress::create([
            'customer_id' => $this->customer->id, 'first_name' => 'Maria', 'last_name' => 'Santos',
            'address_1' => '12 Demo Street', 'city' => 'Demo City', 'state' => 'Demo Region', 'postcode' => '1000',
            'country_id' => $this->country->id, 'phone' => '9001234567', 'is_default' => true, ...$overrides,
        ]);
    }

    private function product(string $name = 'Demo Shirt', float $price = 450): Product
    {
        return Product::create([
            'store_id' => 1, 'name' => $name, 'slug' => str($name)->slug(), 'sku' => 'DEMO-' . str($name)->slug(),
            'price' => $price, 'quantity' => 10, 'is_active' => true, 'track_stock' => true,
        ]);
    }

    private function order(array $overrides = []): Order
    {
        $status = OrderStatus::firstOrCreate(['store_id' => 1, 'name' => 'Pending'], ['is_default' => true, 'sort_order' => 1]);

        return Order::create([
            'store_id' => 1, 'customer_id' => $this->customer->id, 'order_status_id' => $status->id,
            'first_name' => 'Maria', 'last_name' => 'Santos', 'email' => $this->customer->email, 'phone' => '1',
            'shipping_first_name' => 'Maria', 'shipping_last_name' => 'Santos', 'shipping_address_1' => '12 Demo Street',
            'shipping_city' => 'Demo City', 'shipping_postcode' => '1000', 'subtotal' => 900, 'total' => 900, ...$overrides,
        ]);
    }

    private function asCustomer(): self
    {
        return $this->actingAs($this->customer, 'customer');
    }

    public function test_sign_in_keeps_core_form_and_fields(): void
    {
        $this->get(route('customer.login', ['redirect' => 'checkout']))
            ->assertOk()
            ->assertSee('data-fixed-page="login"', false)
            ->assertSee('action="' . route('customer.login.submit') . '"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="remember"', false)
            ->assertSee('name="redirect" value="checkout"', false)
            ->assertSee(route('customer.password.request'), false)
            ->assertSee(route('customer.register', ['redirect' => 'checkout']), false);
    }

    public function test_wrong_password_shows_core_message_on_the_page(): void
    {
        $this->from(route('customer.login'))
            ->post(route('customer.login.submit'), ['email' => 'maria@example.test', 'password' => 'wrong-pass'])
            ->assertRedirect(route('customer.login'));

        $this->get(route('customer.login'))->assertOk()
            ->assertSee('These credentials do not match our records.')
            ->assertSee('value="maria@example.test"', false);
    }

    public function test_an_account_without_a_password_is_offered_the_reset_link(): void
    {
        Customer::create(['store_id' => 1, 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'email' => 'juan@example.test', 'password' => null]);

        $this->from(route('customer.login'))
            ->post(route('customer.login.submit'), ['email' => 'juan@example.test', 'password' => 'anything'])
            ->assertRedirect(route('customer.login'));

        $this->get(route('customer.login'))->assertOk()
            ->assertSee('data-migration-reset', false)
            ->assertSee('data-url="' . route('customer.password.migration') . '"', false)
            ->assertSee('data-email="juan@example.test"', false);
    }

    public function test_signing_in_still_goes_back_to_checkout(): void
    {
        $this->post(route('customer.login.submit'), ['email' => 'maria@example.test', 'password' => 'secret-pass', 'redirect' => 'checkout'])
            ->assertRedirect(route('checkout.index'));
        $this->assertAuthenticatedAs($this->customer, 'customer');
    }

    public function test_register_keeps_core_fields_and_shows_validation_errors(): void
    {
        $this->get(route('customer.register'))->assertOk()
            ->assertSee('action="' . route('customer.register.submit') . '"', false)
            ->assertSee('name="first_name"', false)
            ->assertSee('name="last_name"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertDontSee('name="newsletter"', false);

        $this->from(route('customer.register'))
            ->post(route('customer.register.submit'), ['first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'email' => 'not-an-email', 'password' => 'short', 'password_confirmation' => 'other'])
            ->assertRedirect(route('customer.register'))
            ->assertSessionHasErrors(['email', 'password']);

        $this->get(route('customer.register'))->assertOk()
            ->assertSee('form-status--error', false)
            ->assertSee('aria-invalid="true"', false);
    }

    public function test_password_pages_keep_core_forms(): void
    {
        $this->get(route('customer.password.request'))->assertOk()
            ->assertSee('action="' . route('customer.password.email') . '"', false)
            ->assertSee('name="email"', false);

        $this->get(route('customer.password.reset', ['token' => 'abc123', 'email' => 'maria@example.test']))->assertOk()
            ->assertSee('action="' . route('customer.password.update') . '"', false)
            ->assertSee('name="token" value="abc123"', false)
            ->assertSee('value="maria@example.test"', false)
            ->assertSee('name="password_confirmation"', false);
    }

    public function test_account_pages_send_guests_to_sign_in(): void
    {
        foreach (['account.dashboard', 'account.orders', 'account.addresses', 'account.profile', 'checkout.index'] as $name) {
            $this->get(route($name))->assertRedirect();
            $this->assertGuest('customer');
        }
    }

    public function test_dashboard_shows_core_orders_address_and_account_menu(): void
    {
        $this->address();
        $order = $this->order();

        $this->asCustomer()->get(route('account.dashboard'))->assertOk()
            ->assertSee('data-fixed-page="account"', false)
            ->assertSee('Hello, Maria')
            ->assertSee('#' . $order->id)
            ->assertSee(route('account.orders.show', $order), false)
            ->assertSee('12 Demo Street')
            ->assertSee(route('account.orders'), false)
            ->assertSee(route('account.addresses'), false)
            ->assertSee(route('account.profile'), false)
            ->assertSee('action="' . route('customer.logout') . '"', false)
            ->assertSee('maria@example.test');
    }

    public function test_dashboard_without_orders_or_address_has_empty_states(): void
    {
        $this->asCustomer()->get(route('account.dashboard'))->assertOk()
            ->assertSee('No orders yet')
            ->assertSee('No address saved yet');
    }

    public function test_profile_keeps_both_core_forms_and_their_errors(): void
    {
        $this->asCustomer()->get(route('account.profile'))->assertOk()
            ->assertSee('action="' . route('account.profile.update') . '"', false)
            ->assertSee('action="' . route('account.password.update') . '"', false)
            ->assertSee('name="first_name" value="Maria"', false)
            ->assertSee('name="current_password"', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertSee('name="newsletter"', false);

        $this->asCustomer()->from(route('account.profile'))
            ->post(route('account.password.update'), ['current_password' => 'nope', 'password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'])
            ->assertRedirect(route('account.profile'));

        $this->asCustomer()->get(route('account.profile'))->assertOk()
            ->assertSee('Current password is incorrect.')
            ->assertSee('id="pw-current-error"', false);
    }

    public function test_profile_save_flashes_core_message(): void
    {
        $this->asCustomer()->from(route('account.profile'))
            ->post(route('account.profile.update'), ['first_name' => 'Maria', 'last_name' => 'Reyes'])
            ->assertRedirect(route('account.profile'));

        $this->asCustomer()->get(route('account.profile'))->assertOk()->assertSee('Profile updated successfully.');
        $this->assertSame('Reyes', $this->customer->fresh()->last_name);
    }

    public function test_addresses_list_keeps_edit_default_and_delete(): void
    {
        $home = $this->address();
        $office = $this->address(['address_1' => '5 Office Road', 'is_default' => false]);

        $this->asCustomer()->get(route('account.addresses'))->assertOk()
            ->assertSee('12 Demo Street')
            ->assertSee('5 Office Road')
            ->assertSee(route('account.addresses.edit', $home), false)
            ->assertSee('action="' . route('account.addresses.default', $office) . '"', false)
            ->assertDontSee('action="' . route('account.addresses.default', $home) . '"', false)
            ->assertSee('action="' . route('account.addresses.destroy', $office) . '"', false)
            ->assertSee('name="_method" value="DELETE"', false);
    }

    public function test_address_form_keeps_core_field_names_and_errors(): void
    {
        $this->asCustomer()->get(route('account.addresses.create'))->assertOk()
            ->assertSee('action="' . route('account.addresses.store') . '"', false)
            ->assertSee('data-address-fields', false)
            ->assertSee('id="xelga-address-data"', false);

        $html = $this->asCustomer()->get(route('account.addresses.create'))->getContent();
        foreach (['first_name', 'last_name', 'country_id', 'state', 'city', 'district', 'barangay', 'address_1', 'postcode', 'phone', 'is_default'] as $field) {
            $this->assertStringContainsString('name="' . $field . '"', $html, "The form keeps core's {$field} field.");
        }

        $this->asCustomer()->from(route('account.addresses.create'))
            ->post(route('account.addresses.store'), ['first_name' => 'Maria'])
            ->assertRedirect(route('account.addresses.create'))
            ->assertSessionHasErrors(['last_name', 'address_1', 'city', 'country_id', 'phone']);

        $address = $this->address();
        $this->asCustomer()->get(route('account.addresses.edit', $address))->assertOk()
            ->assertSee('action="' . route('account.addresses.update', $address) . '"', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('value="Demo City"', false);
    }

    public function test_a_posted_address_is_saved_by_core(): void
    {
        $this->asCustomer()->post(route('account.addresses.store'), [
            'first_name' => 'Maria', 'last_name' => 'Santos', 'address_1' => '7 Sample Lane', 'city' => 'Demo City',
            'country_id' => $this->country->id, 'phone' => '9001234567',
        ])->assertRedirect(route('account.addresses'));

        $this->asCustomer()->get(route('account.addresses'))->assertOk()
            ->assertSee('Address added successfully.')
            ->assertSee('7 Sample Lane');
    }

    public function test_orders_list_and_detail_show_core_order_data(): void
    {
        $order = $this->order(['tracking_number' => 'TRACK-001', 'shipping_cost' => 0]);
        $product = $this->product();
        $order->products()->create(['product_id' => $product->id, 'name' => 'Demo Shirt', 'sku' => 'DEMO-001', 'price' => 450, 'quantity' => 2, 'total' => 900]);

        $this->asCustomer()->get(route('account.orders'))->assertOk()
            ->assertSee('data-fixed-page="orders"', false)
            ->assertSee('#' . $order->id)
            ->assertSee('1 item')
            ->assertSee('Pending');

        $this->asCustomer()->get(route('account.orders.show', $order))->assertOk()
            ->assertSee('Order #' . $order->id)
            ->assertSee('Demo Shirt')
            ->assertSee('TRACK-001')
            ->assertSee('data-copy="TRACK-001"', false)
            ->assertSee(store_money(900))
            ->assertSee('12 Demo Street')
            ->assertSee(route('product.show', $product->slug), false);
    }

    public function test_a_tracking_link_that_is_not_a_web_address_is_not_linked(): void
    {
        $unsafe = $this->order(['tracking_number' => 'TRACK-002', 'tracking_link' => 'javascript:alert(document.cookie)']);
        $this->asCustomer()->get(route('account.orders.show', $unsafe))->assertOk()
            ->assertSee('<span class="track__num tabular">TRACK-002</span>', false)
            ->assertDontSee('javascript:alert', false);

        $safe = $this->order(['tracking_number' => 'TRACK-003', 'tracking_link' => 'https://track.example.com/?n=TRACK-003']);
        $this->asCustomer()->get(route('account.orders.show', $safe))->assertOk()
            ->assertSee('href="https://track.example.com/?n=TRACK-003"', false);
    }

    public function test_another_customers_order_stays_closed(): void
    {
        $other = Customer::create(['store_id' => 1, 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'email' => 'juan@example.test', 'password' => bcrypt('x')]);
        $order = $this->order(['customer_id' => $other->id]);

        $this->asCustomer()->get(route('account.orders.show', $order))->assertForbidden();
    }

    public function test_cancel_is_offered_only_where_core_allows_it(): void
    {
        $canceled = OrderStatus::create(['store_id' => 1, 'name' => 'Canceled', 'sort_order' => 7]);
        $pending = OrderStatus::firstOrCreate(['store_id' => 1, 'name' => 'Pending'], ['is_default' => true, 'sort_order' => 1]);
        $pending->update(['customer_can_cancel' => true, 'customer_cancel_status_id' => $canceled->id]);
        $order = $this->order();

        $this->asCustomer()->get(route('account.orders.show', $order))->assertOk()
            ->assertSee('action="' . route('account.orders.cancel', $order) . '"', false)
            ->assertSee('name="reason"', false);
        $this->asCustomer()->get(route('account.orders'))->assertOk()
            ->assertSee('action="' . route('account.orders.cancel', $order) . '"', false);

        $closed = $this->order(['order_status_id' => $canceled->id]);
        $this->asCustomer()->get(route('account.orders.show', $closed))->assertOk()
            ->assertDontSee('action="' . route('account.orders.cancel', $closed) . '"', false);
    }

    public function test_wishlist_draws_saved_products_with_the_theme_card(): void
    {
        $product = $this->product('Saved Jacket', 1200);
        $this->asCustomer()->postJson(route('wishlist.add'), ['product_id' => $product->id])->assertOk();

        $this->asCustomer()->get(route('wishlist.index'))->assertOk()
            ->assertSee('data-fixed-page="wishlist"', false)
            ->assertSee('Saved Jacket')
            ->assertSee('class="card', false)
            ->assertSee(money(1200));

        $this->asCustomer()->get(route('account.dashboard'))->assertOk()->assertSee('Saved Jacket');
    }

    public function test_empty_wishlist_for_a_guest(): void
    {
        $this->get(route('wishlist.index'))->assertOk()->assertSee('Nothing saved yet');
    }

    public function test_checkout_draws_core_data_and_posts_core_fields(): void
    {
        $address = $this->address();
        $product = $this->product();
        $this->asCustomer()->postJson(route('cart.add'), ['product_id' => $product->id, 'quantity' => 2])->assertOk();

        $response = $this->asCustomer()->get(route('checkout.index'))->assertOk()
            ->assertSee('data-fixed-page="checkout"', false)
            ->assertSee('Demo Shirt')
            ->assertSee('12 Demo Street')
            ->assertSee('name="checkout_address" value="' . $address->id . '"', false)
            ->assertSee('action="' . route('checkout.place') . '"', false)
            ->assertSee('Signed in as Maria Santos');

        $html = $response->getContent();
        foreach (['shipping_address_id', 'payment_method', 'shipping_method', 'comment', 'courier_comment', 'different_billing',
            'billing_first_name', 'billing_last_name', 'billing_address_1', 'billing_city', 'billing_district', 'billing_barangay', 'billing_state', 'billing_postcode', 'billing_country_id'] as $field) {
            $this->assertStringContainsString('name="' . $field . '"', $html, "Checkout posts core's {$field} field.");
        }
        $this->assertStringContainsString('name="shipping_address_id" value="' . $address->id . '"', $html);
        // Core prices the chosen method from its own quote; the page never posts a cost or a name for it.
        $this->assertStringNotContainsString('name="shipping_cost"', $html);
        $this->assertStringNotContainsString('name="shipping_method_name"', $html);

        $data = $this->checkoutData($html);
        $this->assertSame($address->id, $data['selectedAddressId']);
        $this->assertSame('Demo Shirt', $data['items'][0]['name']);
        $this->assertSame(2, $data['items'][0]['quantity']);
        $this->assertEquals(900, $data['subtotal']);
        $this->assertSame(route('checkout.shipping-rates'), $data['routes']['shippingRates']);
        $this->assertSame(route('checkout.address.save'), $data['routes']['saveAddress']);
    }

    public function test_checkout_lists_payment_and_shipping_methods_core_offers(): void
    {
        $manager = app(ExtensionManager::class);
        foreach (['payment-cod', 'shipping-flat-rate'] as $extension) {
            try {
                $manager->install($extension);
            } catch (\Throwable) {
                $this->markTestSkipped("The {$extension} extension could not be installed here.");
            }
        }
        $manager->bootEnabled();

        $this->address();
        $product = $this->product();
        $this->asCustomer()->postJson(route('cart.add'), ['product_id' => $product->id])->assertOk();

        $html = $this->asCustomer()->get(route('checkout.index'))->assertOk()->getContent();
        $data = $this->checkoutData($html);
        $view = $this->asCustomer()->get(route('checkout.index'));
        $methods = collect($view->viewData('paymentMethods'))->map(fn ($m) => $m->getKey())->values()->all();
        $shipping = collect($view->viewData('shippingMethods'))->pluck('key')->values()->all();

        $this->assertSame($methods, collect($data['paymentMethods'])->pluck('key')->all());
        $this->assertSame($shipping, collect($data['shippingMethods'])->pluck('key')->all());
        foreach ($methods as $key) {
            $this->assertStringContainsString('name="checkout_payment" value="' . $key . '"', $html);
        }
    }

    public function test_checkout_with_an_empty_cart_goes_back_to_the_cart(): void
    {
        $this->asCustomer()->get(route('checkout.index'))->assertRedirect(route('cart.index'));
    }

    public function test_checkout_without_addresses_opens_the_new_address_form(): void
    {
        $this->asCustomer()->postJson(route('cart.add'), ['product_id' => $this->product()->id])->assertOk();

        $html = $this->asCustomer()->get(route('checkout.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-address-panel\s*>/', $html, 'The new address form is open.');
        $this->assertStringContainsString('Add your delivery address to see delivery options.', $html);
    }

    public function test_confirmation_shows_core_order(): void
    {
        $order = $this->order(['payment_method' => 'cod']);
        $order->products()->create(['product_id' => null, 'name' => 'Demo Shirt', 'sku' => 'DEMO-001', 'price' => 450, 'quantity' => 2, 'total' => 900]);

        $this->asCustomer()->get(route('checkout.confirmation', $order))->assertOk()
            ->assertSee('data-fixed-page="confirmation"', false)
            ->assertSee('#' . $order->id)
            ->assertSee('Demo Shirt')
            ->assertSee(store_money(900))
            ->assertSee(route('account.orders.show', $order), false);
    }

    public function test_review_page_keeps_the_tokened_form(): void
    {
        try {
            app(ExtensionManager::class)->install('reviews');
            app(ExtensionManager::class)->bootEnabled();
        } catch (\Throwable) {
            $this->markTestSkipped('The reviews extension could not be installed here.');
        }
        if (! class_exists(\Extensions\Reviews\Models\Review::class) || ! \Illuminate\Support\Facades\Route::has('review.submit')) {
            $this->markTestSkipped('The reviews routes are not registered in this run.');
        }

        $product = $this->product();
        $review = \Extensions\Reviews\Models\Review::forceCreate([
            'product_id' => $product->id, 'customer_id' => $this->customer->id, 'token' => 'demo-token-001', 'name' => 'Maria Santos', 'email' => 'maria@example.test',
        ]);

        $this->get(route('review.submit', $review->token))->assertOk()
            ->assertSee('action="' . route('review.store', $review->token) . '"', false)
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="rating" value="5"', false)
            ->assertSee('name="title"', false)
            ->assertSee('name="body"', false)
            ->assertSee('name="images[]"', false)
            ->assertSee('Demo Shirt');

        $this->from(route('review.submit', $review->token))->post(route('review.store', $review->token), [])
            ->assertSessionHasErrors('rating');

        $this->post(route('review.store', $review->token), ['rating' => 4])->assertRedirect(route('review.submit', $review->token));
        $this->get(route('review.submit', $review->token))->assertOk()->assertSee('Thank you for your review');
    }

    private function checkoutData(string $html): array
    {
        $this->assertSame(1, preg_match('/<script type="application\/json" data-checkout-data>(.*?)<\/script>/s', $html, $m));

        return json_decode(html_entity_decode($m[1]), true, 512, JSON_THROW_ON_ERROR);
    }
}
