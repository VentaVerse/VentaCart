<?php

namespace Tests\Feature\Storefront;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The search box sends one string. A query string that sends a list
 * instead (q[]=...) is treated as no search, never as a server error, on
 * the results page, in autocomplete, and in the header's search field that
 * every page renders.
 */
class SearchQueryShapeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        Product::create([
            'store_id' => 1, 'name' => 'Demo Cable', 'slug' => 'demo-cable', 'sku' => 'DEMO-001',
            'price' => 500, 'quantity' => 5, 'is_active' => true,
        ]);
    }

    public function test_a_list_query_on_the_results_page_reads_as_no_search(): void
    {
        $page = $this->get('/search?q[]=cable')->assertOk();

        $this->assertSame('', $page->viewData('query'));
    }

    public function test_a_nested_list_query_reads_as_no_search(): void
    {
        $this->get('/search?q[a][b]=cable&sort[]=price_asc')->assertOk();
    }

    public function test_a_list_query_in_autocomplete_returns_nothing(): void
    {
        $this->getJson('/search/autocomplete?q[]=cable')->assertOk()->assertExactJson([]);
    }

    public function test_a_list_query_on_any_page_does_not_break_the_header_search_field(): void
    {
        $this->get('/?q[]=cable')->assertOk();
    }

    public function test_a_string_query_still_searches(): void
    {
        $page = $this->get('/search?q=' . urlencode('  cable '))->assertOk();

        $this->assertSame('cable', $page->viewData('query'));
        $this->assertSame(['Demo Cable'], $page->viewData('products')->pluck('name')->all());
    }
}
