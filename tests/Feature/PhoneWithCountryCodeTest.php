<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phone numbers are stored as typed, with the country kept beside them; a
 * person reads them with the country's dialling code in front (owner
 * request, 2026-08-29): on the customer's order page, the admin's order
 * page, the address book and the account dashboard.
 */
class PhoneWithCountryCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_code_is_added_only_when_the_number_does_not_carry_one(): void
    {
        $this->assertSame('+63 9171234567', Phone::display('9171234567', '63'));
        $this->assertSame('+63 9171234567', Phone::display(' 9171234567 ', '+63'));
        $this->assertSame('+639171234567', Phone::display('+639171234567', '63'));
        $this->assertSame('9171234567', Phone::display('9171234567', null));
        $this->assertNull(Phone::display('', '63'));
        $this->assertNull(Phone::display(null, '63'));
    }

    public function test_the_address_book_and_dashboard_show_the_phone_with_code_and_the_postal_code(): void
    {
        $ph = Country::create(['iso2' => 'PH', 'iso3' => 'PHL', 'name' => 'Philippines', 'phone_code' => '63', 'is_active' => true]);
        $customer = Customer::create(['store_id' => 1, 'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com', 'password' => bcrypt('x')]);
        CustomerAddress::create(['customer_id' => $customer->id, 'first_name' => 'Jane', 'last_name' => 'Doe', 'address_1' => '1 Rizal St', 'city' => 'Cebu City', 'state' => 'Cebu', 'postcode' => '6000', 'country_id' => $ph->id, 'phone' => '9171234567', 'is_default' => true]);

        $this->actingAs($customer, 'customer')->get(route('account.addresses'))->assertOk()
            ->assertSee('+63 9171234567')->assertSee('Postal code 6000')->assertSee('Philippines');
        $this->actingAs($customer, 'customer')->get(route('account.dashboard'))->assertOk()
            ->assertSee('+63 9171234567')->assertSee('Postal code 6000');
    }

    public function test_the_order_pages_show_the_shipping_phone_with_its_countrys_code(): void
    {
        $ph = Country::create(['iso2' => 'PH', 'iso3' => 'PHL', 'name' => 'Philippines', 'phone_code' => '63', 'is_active' => true]);
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Pending', 'is_default' => true, 'sort_order' => 1]);
        $customer = Customer::create(['store_id' => 1, 'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com', 'password' => bcrypt('x')]);
        $order = Order::create([
            'store_id' => 1, 'customer_id' => $customer->id, 'order_status_id' => $status->id,
            'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com', 'phone' => '9171234567',
            'shipping_first_name' => 'Jane', 'shipping_last_name' => 'Doe', 'shipping_address_1' => '1 Rizal St',
            'shipping_city' => 'Cebu City', 'shipping_postcode' => '6000', 'shipping_country_id' => $ph->id, 'shipping_phone' => '9171234567',
            'subtotal' => 100, 'total' => 100,
        ]);

        $this->actingAs($customer, 'customer')->get(route('account.orders.show', $order))->assertOk()->assertSee('+63 9171234567');
        $this->actingAs(User::factory()->create())->get(route('admin.orders.show', $order))->assertOk()->assertSee('+63 9171234567');
    }
}
