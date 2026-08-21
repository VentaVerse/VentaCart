<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What the product list can be asked.
 *
 * It answered exactly one question, "what exists, in creation order", over
 * ninety-four pages of Previous and Next. A packing desk asks what is nearly
 * out and what costs the most, and neither had a way in.
 */
class ProductListNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        $this->actingAs(User::factory()->create());
    }

    private function make(string $name, float $price, int $qty): Product
    {
        return Product::create([
            'store_id' => 1, 'name' => $name, 'slug' => \Illuminate\Support\Str::slug($name),
            'sku' => strtoupper($name), 'price' => $price, 'quantity' => $qty,
        ]);
    }

    private function names($response): array
    {
        preg_match_all('/class="v-rowlink">([^<]+)</', $response->getContent(), $m);

        return array_map('trim', $m[1]);
    }

    public function test_it_sorts_by_price(): void
    {
        $this->make('Cheap', 10, 5);
        $this->make('Dear', 900, 5);
        $this->make('Middle', 100, 5);

        $response = $this->get(route('admin.products.index', ['sort' => 'price', 'dir' => 'asc']))->assertOk();

        $this->assertSame(['Cheap', 'Middle', 'Dear'], $this->names($response));
    }

    public function test_it_sorts_by_stock_so_the_nearly_out_come_first(): void
    {
        $this->make('Plenty', 10, 500);
        $this->make('Gone', 10, 0);
        $this->make('Few', 10, 3);

        $response = $this->get(route('admin.products.index', ['sort' => 'stock', 'dir' => 'asc']))->assertOk();

        $this->assertSame(['Gone', 'Few', 'Plenty'], $this->names($response));
    }

    /**
     * An order-by taken from the querystring is a column name taken from a
     * stranger.
     */
    public function test_an_unknown_sort_is_ignored_rather_than_run(): void
    {
        $this->make('One', 10, 1);

        $this->get(route('admin.products.index', ['sort' => 'password', 'dir' => 'asc']))->assertOk();
        $this->get(route('admin.products.index', ['sort' => '(select 1)', 'dir' => 'asc']))->assertOk();
    }

    public function test_the_page_size_is_bounded(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->make('P' . $i, 10, 1);
        }

        $this->assertCount(20, $this->names($this->get(route('admin.products.index'))->assertOk()));
        $this->assertCount(25, $this->names($this->get(route('admin.products.index', ['per' => 50]))->assertOk()));
        // Not a number a stranger chose: 100,000 rows is a denial of service
        // with a friendly name.
        $this->assertCount(20, $this->names($this->get(route('admin.products.index', ['per' => 100000]))->assertOk()));
        $this->assertCount(20, $this->names($this->get(route('admin.products.index', ['per' => 0]))->assertOk()));
    }

    public function test_the_pager_offers_numbered_pages(): void
    {
        for ($i = 0; $i < 45; $i++) {
            $this->make('P' . $i, 10, 1);
        }

        $this->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('v-pager__n', false)
            ->assertSee('aria-label="Page 3"', false);
    }

    public function test_a_sorted_header_says_so_without_arrows(): void
    {
        $this->make('One', 10, 1);

        $this->get(route('admin.products.index', ['sort' => 'price', 'dir' => 'asc']))
            ->assertOk()
            ->assertSee('aria-sort="ascending"', false);
    }

    public function test_sorting_keeps_the_filters_it_was_given(): void
    {
        $this->make('Kept', 10, 1);

        $html = $this->get(route('admin.products.index', ['search' => 'Kept', 'sort' => 'price', 'dir' => 'asc']))
            ->assertOk()->getContent();

        $this->assertStringContainsString('search=Kept', $html);
    }
}
