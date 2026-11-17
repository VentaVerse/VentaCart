<?php

namespace Tests\Feature\Storefront;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The store frame (header cart count and subtotal, menu, currencies,
 * wishlist) is worked out once per page, not once for the shared view data
 * and again for the layout.
 */
class StoreLayoutQueriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_page_reads_the_shopper_s_cart_once_and_shows_its_count_and_subtotal(): void
    {
        $store = Store::firstOrCreate(['code' => 'demo'], ['name' => 'Demo Store']);
        $customer = Customer::create(['store_id' => 1, 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'email' => 'juan@example.com', 'password' => bcrypt('secret')]);
        $this->actingAs($customer, 'customer');
        foreach ([1, 2] as $i) {
            $product = Product::create([
                'store_id' => $store->id, 'name' => "Demo {$i}", 'slug' => "demo-{$i}", 'sku' => "DEMO-00{$i}",
                'price' => 150, 'quantity' => 20, 'is_active' => true,
            ]);
            $this->postJson('/cart/add', ['product_id' => $product->id, 'quantity' => $i])->assertOk();
        }

        $carts = 0;
        $lines = 0;
        DB::listen(function (QueryExecuted $q) use (&$carts, &$lines) {
            if (preg_match('/^select .* from "carts"/', $q->sql)) {
                $carts++;
            }
            if (preg_match('/^select .* from "cart_items"/', $q->sql) || str_contains($q->sql, 'sum("quantity")')) {
                $lines++;
            }
        });

        $this->get('/')->assertOk()->assertViewHas('cartCount', 3)->assertViewHas('cartSubtotal', 450.0);

        $this->assertSame(1, $carts, 'the cart is looked up once');
        $this->assertSame(1, $lines, 'its lines are read once');
    }
}
