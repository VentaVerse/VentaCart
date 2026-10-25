<?php

namespace Tests\Feature\Storefront;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Store;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The wishlist: kept in the session for guests, stored for signed-in
 * customers, merged into the account at sign-in, with a page, a count for
 * the header and add and remove endpoints.
 */
class WishlistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
    }

    private function product(string $name, array $extra = []): Product
    {
        return Product::create($extra + ['store_id' => 1, 'name' => $name, 'slug' => \Illuminate\Support\Str::slug($name), 'price' => 100, 'quantity' => 5, 'is_active' => true]);
    }

    private function customer(): Customer
    {
        return Customer::create(['store_id' => 1, 'first_name' => 'Maria', 'last_name' => 'Santos', 'email' => 'maria@example.com', 'password' => 'secret-password-1', 'is_active' => true]);
    }

    public function test_a_guest_keeps_a_wishlist_in_the_session(): void
    {
        $lamp = $this->product('Demo Lamp');

        $this->postJson(route('wishlist.add'), ['product_id' => $lamp->id])->assertOk()->assertJson(['in' => true, 'count' => 1]);
        $this->postJson(route('wishlist.add'), ['product_id' => $lamp->id])->assertOk()->assertJson(['count' => 1], 'Adding twice keeps one.');
        $this->getJson(route('wishlist.state'))->assertOk()->assertJson(['ids' => [$lamp->id], 'count' => 1]);
        $this->assertSame(0, WishlistItem::count(), 'Nothing is stored for a guest.');

        $this->get(route('wishlist.index'))->assertOk()->assertSee('Demo Lamp');

        $this->deleteJson(route('wishlist.remove', $lamp))->assertOk()->assertJson(['in' => false, 'count' => 0]);
        $this->get(route('wishlist.index'))->assertOk()->assertDontSee('Demo Lamp');
    }

    public function test_a_signed_in_customer_has_a_stored_wishlist(): void
    {
        $lamp = $this->product('Demo Lamp');
        $customer = $this->customer();
        $this->actingAs($customer, 'customer');

        $this->postJson(route('wishlist.add'), ['product_id' => $lamp->id])->assertOk()->assertJson(['count' => 1]);
        $this->assertDatabaseHas('wishlist_items', ['customer_id' => $customer->id, 'product_id' => $lamp->id]);

        $this->deleteJson(route('wishlist.remove', $lamp))->assertOk();
        $this->assertSame(0, WishlistItem::count());
    }

    public function test_signing_in_merges_the_guest_wishlist_into_the_account(): void
    {
        $lamp = $this->product('Demo Lamp');
        $chair = $this->product('Demo Chair');
        $customer = $this->customer();
        WishlistItem::create(['customer_id' => $customer->id, 'product_id' => $chair->id]);

        $this->postJson(route('wishlist.add'), ['product_id' => $lamp->id])->assertOk();
        $this->postJson(route('wishlist.add'), ['product_id' => $chair->id])->assertOk();

        $this->post(route('customer.login.submit'), ['email' => 'maria@example.com', 'password' => 'secret-password-1'])->assertRedirect();

        $this->assertSame([$lamp->id, $chair->id], WishlistItem::where('customer_id', $customer->id)->orderBy('product_id')->pluck('product_id')->all());
        $this->getJson(route('wishlist.state'))->assertJson(['count' => 2]);
    }

    public function test_only_real_live_products_can_be_added(): void
    {
        $off = $this->product('Demo Retired', ['is_active' => false]);

        $this->postJson(route('wishlist.add'), ['product_id' => $off->id])->assertUnprocessable();
        $this->postJson(route('wishlist.add'), ['product_id' => 999999])->assertUnprocessable();
        $this->postJson(route('wishlist.add'), ['product_id' => 'one'])->assertUnprocessable();
        $this->getJson(route('wishlist.state'))->assertJson(['count' => 0]);
    }

    public function test_the_header_count_is_shared_with_every_page(): void
    {
        $lamp = $this->product('Demo Lamp');
        $this->postJson(route('wishlist.add'), ['product_id' => $lamp->id]);

        $response = $this->get('/')->assertOk();
        $this->assertSame(1, $response->viewData('wishlistCount'));
        $this->assertSame([$lamp->id], $response->viewData('wishlistIds'));
    }

    public function test_writes_are_rate_limited(): void
    {
        $lamp = $this->product('Demo Lamp');
        for ($i = 0; $i < 30; $i++) {
            $this->postJson(route('wishlist.add'), ['product_id' => $lamp->id]);
        }
        $this->postJson(route('wishlist.add'), ['product_id' => $lamp->id])->assertStatus(429);
    }
}
