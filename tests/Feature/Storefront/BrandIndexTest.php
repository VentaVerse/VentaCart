<?php

namespace Tests\Feature\Storefront;

use App\Models\Brand;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The brand index, on a database that is not MySQL.
 *
 * It filtered with ->having('products_count', '>', 0) on a query with no GROUP
 * BY. MySQL allows that; SQLite refuses it with "HAVING clause on a
 * non-aggregate query", so the page returned 500 on any SQLite install while
 * passing every day on the MySQL the store happens to run. Venta is meant to
 * be self-hosted by people who will not all choose MySQL.
 */
class BrandIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_and_lists_only_brands_that_have_products(): void
    {
        $withStock = Brand::create(['name' => 'Loud Amps', 'slug' => 'loud-amps', 'is_active' => true, 'store_id' => 1]);
        Brand::create(['name' => 'Silent Co', 'slug' => 'silent-co', 'is_active' => true, 'store_id' => 1]);

        Product::create([
            'name' => 'A Loud Amp', 'slug' => 'a-loud-amp', 'brand_id' => $withStock->id,
            'price' => 100, 'quantity' => 1, 'is_active' => true, 'store_id' => 1,
        ]);

        $this->get('/brand')
            ->assertOk()
            ->assertSee('Loud Amps', false)
            ->assertDontSee('Silent Co', false);
    }

    public function test_it_renders_when_no_brand_has_a_product(): void
    {
        Brand::create(['name' => 'Silent Co', 'slug' => 'silent-co', 'is_active' => true, 'store_id' => 1]);

        $this->get('/brand')->assertOk();
    }
}
