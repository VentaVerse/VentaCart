<?php

namespace Tests\Feature\Storefront;

use App\Models\Country;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Services\Shipping\ShippingQuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Shipping quotes ask every shipping provider, some of them a courier's
 * API, so the cart's estimate and checkout's delivery options are rate
 * limited (guests by IP, customers by account) and refuse oversized
 * address fields. A shopper using the page normally never notices.
 */
class ShippingQuoteThrottleTest extends TestCase
{
    use RefreshDatabase;

    private Country $country;

    protected function setUp(): void
    {
        parent::setUp();
        config(['shipping.quotes_per_minute' => 3]);
        $this->country = Country::create(['iso2' => 'PH', 'iso3' => 'PHL', 'name' => 'Philippines', 'phone_code' => '63', 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        ShippingQuoteService::reset();
        parent::tearDown();
    }

    private function estimate(array $override = [], string $ip = '192.0.2.10')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->postJson(route('cart.estimate-shipping'), $override + [
            'country_id' => $this->country->id, 'state' => 'Metro Manila', 'city' => 'Manila', 'barangay' => 'Barangay 556',
        ]);
    }

    private function customer(string $email): Customer
    {
        return Customer::create(['store_id' => 1, 'first_name' => 'Maria', 'last_name' => 'Santos', 'email' => $email, 'password' => bcrypt('secret')]);
    }

    private function address(Customer $customer): CustomerAddress
    {
        return $customer->addresses()->create([
            'first_name' => 'Maria', 'last_name' => 'Santos', 'address_1' => '1 Demo Street', 'country_id' => $this->country->id,
            'state' => 'Metro Manila', 'city' => 'Manila', 'phone' => '9170000000', 'is_default' => true,
        ]);
    }

    public function test_normal_use_gets_its_estimate(): void
    {
        $this->estimate()->assertOk()->assertJsonStructure(['methods']);
        $this->estimate()->assertOk();
    }

    public function test_a_guest_looping_the_estimate_is_stopped_with_a_clear_answer(): void
    {
        foreach (range(1, 3) as $ignored) {
            $this->estimate()->assertOk();
        }

        $this->estimate()->assertStatus(429)
            ->assertJson(['success' => false, 'message' => 'Too many shipping lookups in a short time. Wait a minute, then try again.'])
            ->assertHeader('Retry-After');

        // Another shopper is not held up by the first.
        $this->estimate([], '192.0.2.20')->assertOk();
    }

    public function test_oversized_address_fields_are_refused(): void
    {
        // Refused requests count against the limit too; leave room for these.
        config(['shipping.quotes_per_minute' => 20]);
        $long = str_repeat('a', 101);

        $this->estimate(['city' => $long])->assertStatus(422)->assertJsonValidationErrors('city');
        $this->estimate(['state' => $long])->assertStatus(422)->assertJsonValidationErrors('state');
        $this->estimate(['barangay' => $long])->assertStatus(422)->assertJsonValidationErrors('barangay');
        $this->estimate(['postcode' => str_repeat('1', 21)])->assertStatus(422)->assertJsonValidationErrors('postcode');
        $this->estimate(['city' => str_repeat('a', 100)])->assertOk();
    }

    public function test_checkout_delivery_options_are_limited_per_customer(): void
    {
        $juan = $this->customer('juan@example.test');
        $address = $this->address($juan);

        foreach (range(1, 3) as $ignored) {
            $this->actingAs($juan, 'customer')->postJson(route('checkout.shipping-rates'), ['address_id' => $address->id])->assertOk();
        }
        $this->actingAs($juan, 'customer')->postJson(route('checkout.shipping-rates'), ['address_id' => $address->id])->assertStatus(429);

        // Counted by account: another customer behind the same address is unaffected.
        $maria = $this->customer('maria@example.test');
        $this->actingAs($maria, 'customer')->postJson(route('checkout.shipping-rates'), ['address_id' => $this->address($maria)->id])->assertOk();
    }

    public function test_checkout_quotes_only_the_customer_s_own_address(): void
    {
        $other = $this->address($this->customer('other@example.test'));

        $this->actingAs($this->customer('juan@example.test'), 'customer')
            ->postJson(route('checkout.shipping-rates'), ['address_id' => $other->id])->assertNotFound();
    }

    public function test_a_saved_address_has_the_same_limits(): void
    {
        $juan = $this->customer('juan@example.test');

        $this->actingAs($juan, 'customer')->postJson(route('checkout.address.save'), [
            'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'address_1' => '1 Demo Street', 'country_id' => $this->country->id,
            'city' => str_repeat('a', 101), 'phone' => '9170000000',
        ])->assertStatus(422)->assertJsonValidationErrors('city');
    }
}
