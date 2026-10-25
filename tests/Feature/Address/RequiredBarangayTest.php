<?php

namespace Tests\Feature\Address;

use App\Models\Cart;
use App\Models\Country;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\User;
use App\Services\Address\DeepAddressProvider;
use App\Services\Address\DeepAddressRules;
use App\Services\AddressProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Owner ruling 2026-10-21: every PH address names its barangay, and a Manila
 * address its district (Manila's barangays hang off its districts). Reported
 * after a live Manila customer skipped the optional district, so the barangay
 * dropdown never appeared and the order left with neither.
 */
class RequiredBarangayTest extends TestCase
{
    use RefreshDatabase;

    private Country $ph;

    private Country $sg;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);

        $this->ph = Country::create(['iso2' => 'PH', 'iso3' => 'PHL', 'name' => 'Philippines', 'phone_code' => '63', 'is_active' => true]);
        $this->sg = Country::create(['iso2' => 'SG', 'iso3' => 'SGP', 'name' => 'Singapore', 'phone_code' => '65', 'is_active' => true]);
        app(AddressProviderRegistry::class)->register(new DeepAddressProvider('PH', config('localization.deep_address.PH')));
    }

    private function manila(array $overrides = []): array
    {
        return array_merge([
            'country_id' => $this->ph->id, 'state' => 'Metro Manila', 'city' => 'Manila',
            'district' => 'Sampaloc', 'barangay' => 'Barangay 556',
        ], $overrides);
    }

    private function missing(array $address): array
    {
        return app(DeepAddressRules::class)->missing($address['country_id'], $address);
    }

    public function test_a_complete_manila_address_owes_nothing(): void
    {
        $this->assertSame([], $this->missing($this->manila()));
    }

    public function test_manila_without_a_district_owes_district_and_barangay(): void
    {
        $this->assertSame(['district', 'barangay'], $this->missing($this->manila(['district' => '', 'barangay' => ''])));
    }

    public function test_manila_with_a_district_but_no_barangay_owes_the_barangay(): void
    {
        $this->assertSame(['barangay'], $this->missing($this->manila(['barangay' => ''])));
    }

    public function test_a_city_without_districts_owes_only_its_barangay(): void
    {
        $this->assertSame(['barangay'], $this->missing([
            'country_id' => $this->ph->id, 'state' => 'Bohol', 'city' => 'Danao', 'district' => '', 'barangay' => '',
        ]));
    }

    public function test_a_country_without_deep_levels_owes_nothing(): void
    {
        $this->assertSame([], $this->missing(['country_id' => $this->sg->id, 'city' => 'Singapore']));
    }

    public function test_switching_the_ph_address_extension_off_lifts_the_rule(): void
    {
        $this->app->forgetInstance(AddressProviderRegistry::class);
        $this->app->singleton(AddressProviderRegistry::class);

        $this->assertSame([], $this->missing($this->manila(['district' => '', 'barangay' => ''])));
    }

    public function test_the_account_form_refuses_manila_without_a_district(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer, 'customer')
            ->post(route('account.addresses.store'), $this->addressForm(['district' => '', 'barangay' => '']))
            ->assertSessionHasErrors(['district' => 'Choose a district.', 'barangay' => 'Choose a barangay.']);

        $this->assertSame(0, $customer->addresses()->count());
    }

    public function test_the_account_form_saves_a_complete_address(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer, 'customer')
            ->post(route('account.addresses.store'), $this->addressForm())
            ->assertSessionHasNoErrors();

        $this->assertSame('Barangay 556', $customer->addresses()->sole()->barangay);
    }

    public function test_checkouts_new_address_refuses_a_missing_barangay(): void
    {
        $this->actingAs($this->customer(), 'customer')
            ->postJson(route('checkout.address.save'), $this->addressForm(['barangay' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['barangay' => 'Choose a barangay.']);
    }

    /** The saved address that predates the rule: refused out loud at the last step. */
    public function test_checkout_refuses_a_saved_manila_address_with_no_district(): void
    {
        $customer = $this->customer();
        $address = $customer->addresses()->create($this->addressForm(['district' => null, 'barangay' => null]));
        $this->cartFor($customer);

        $this->actingAs($customer, 'customer')
            ->post(route('checkout.place'), [
                'shipping_address_id' => $address->id, 'payment_method' => 'cod', 'shipping_method' => 'flat',
            ])
            ->assertSessionHasErrors([
                'checkout' => 'Your delivery address is missing its district and barangay. Edit the address and choose them to continue.',
            ]);

        $this->assertSame(0, Order::count());
    }

    public function test_checkout_names_a_lone_missing_barangay(): void
    {
        $customer = $this->customer();
        $address = $customer->addresses()->create($this->addressForm(['barangay' => null]));
        $this->cartFor($customer);

        $this->actingAs($customer, 'customer')
            ->post(route('checkout.place'), [
                'shipping_address_id' => $address->id, 'payment_method' => 'cod', 'shipping_method' => 'flat',
            ])
            ->assertSessionHasErrors([
                'checkout' => 'Your delivery address is missing its barangay. Edit the address and choose one to continue.',
            ]);
    }

    public function test_checkout_refuses_an_incomplete_billing_address(): void
    {
        $customer = $this->customer();
        $address = $customer->addresses()->create($this->addressForm());
        $this->cartFor($customer);

        $this->actingAs($customer, 'customer')
            ->post(route('checkout.place'), [
                'shipping_address_id' => $address->id, 'payment_method' => 'cod', 'shipping_method' => 'flat',
                'different_billing' => '1', 'billing_first_name' => 'Jane', 'billing_last_name' => 'Doe',
                'billing_address_1' => '1 Test St', 'billing_country_id' => $this->ph->id,
                'billing_state' => 'Metro Manila', 'billing_city' => 'Manila', 'billing_district' => 'Sampaloc',
            ])
            ->assertSessionHasErrors([
                'checkout' => 'Your billing address is missing its barangay. Edit the address and choose one to continue.',
            ]);
    }

    public function test_the_admin_order_form_refuses_a_new_order_without_a_barangay(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.orders.store'), $this->adminOrder(['shipping_barangay' => '']))
            ->assertSessionHasErrors(['shipping_barangay' => 'Choose a barangay.']);
    }

    /** Imported orders never had a barangay; editing something else on one must still save. */
    public function test_the_admin_order_form_leaves_an_untouched_old_address_alone(): void
    {
        $this->actingAs(User::factory()->create());
        $form = $this->adminOrder(['shipping_district' => '', 'shipping_barangay' => '']);
        $order = Order::create(collect($form)->except(['items', 'payment_method', 'shipping_method'])->all());

        $this->put(route('admin.orders.update', $order), array_merge($form, ['comment' => 'Called the customer']))
            ->assertSessionDoesntHaveErrors(['shipping_district', 'shipping_barangay']);
    }

    public function test_the_admin_customer_form_refuses_a_new_incomplete_address(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = $this->customer();

        $this->put(route('admin.customers.update', $customer), $this->adminCustomer($customer, [
            array_merge($this->addressForm(['barangay' => '']), ['id' => '']),
        ]))->assertSessionHasErrors(['addresses.0.barangay' => 'Choose a barangay.']);

        $this->assertSame(0, $customer->addresses()->count());
    }

    public function test_the_admin_customer_form_leaves_an_untouched_old_address_alone(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = $this->customer();
        $old = $customer->addresses()->create($this->addressForm(['district' => null, 'barangay' => null]));

        $this->put(route('admin.customers.update', $customer), $this->adminCustomer($customer, [
            array_merge($this->addressForm(['district' => '', 'barangay' => '']), ['id' => $old->id]),
        ], ['last_name' => 'Santos']))->assertSessionHasNoErrors();

        $this->assertSame('Santos', $customer->fresh()->last_name);
    }

    private function customer(): Customer
    {
        return Customer::create([
            'store_id' => 1, 'first_name' => 'Jane', 'last_name' => 'Doe',
            'email' => 'jane@example.test', 'password' => bcrypt('secret'),
        ]);
    }

    private function addressForm(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Jane', 'last_name' => 'Doe', 'address_1' => '1046C Panay Street',
            'postcode' => '1008', 'phone' => '9171234567',
        ], $this->manila($overrides));
    }

    private function cartFor(Customer $customer): void
    {
        $product = Product::create([
            'store_id' => 1, 'name' => 'Cable', 'slug' => 'cable', 'sku' => 'CAB',
            'price' => 500, 'quantity' => 5, 'is_active' => true, 'track_stock' => true,
        ]);
        Cart::create(['store_id' => 1, 'customer_id' => $customer->id])->items()->create([
            'product_id' => $product->id, 'name' => 'Cable', 'price' => 500, 'quantity' => 1,
        ]);
    }

    private function adminOrder(array $overrides = []): array
    {
        $status = OrderStatus::firstOrCreate(['store_id' => 1, 'name' => 'Pending'], ['sort_order' => 1]);

        return array_merge([
            'store_id' => 1, 'order_status_id' => $status->id,
            'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.test',
            'shipping_first_name' => 'Jane', 'shipping_last_name' => 'Doe', 'shipping_address_1' => '1046C Panay Street',
            'shipping_country_id' => $this->ph->id, 'shipping_state' => 'Metro Manila', 'shipping_city' => 'Manila',
            'shipping_district' => 'Sampaloc', 'shipping_barangay' => 'Barangay 556',
            'payment_method' => 'cod', 'shipping_method' => 'flat',
            'items' => [['name' => 'Cable', 'price' => 500, 'quantity' => 1]],
        ], $overrides);
    }

    private function adminCustomer(Customer $customer, array $addresses, array $overrides = []): array
    {
        return array_merge([
            'store_id' => 1, 'first_name' => $customer->first_name, 'last_name' => $customer->last_name,
            'email' => $customer->email, 'is_active' => '1', 'newsletter' => '0',
            'addresses' => $addresses,
        ], $overrides);
    }
}
