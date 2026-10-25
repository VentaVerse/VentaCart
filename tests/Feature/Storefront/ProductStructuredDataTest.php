<?php

namespace Tests\Feature\Storefront;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The product page's structured data is valid JSON whatever the product is
 * called, and leaves the internal SKU out.
 */
class ProductStructuredDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_structured_data_survives_quotes_and_backslashes_and_hides_the_sku(): void
    {
        $product = Product::create([
            'store_id' => 1,
            'slug' => 'demo-deluxe',
            'price' => 100,
            'quantity' => 3,
            'name' => 'R&B "Deluxe" 12\\ </script><b>',
            'sku' => 'DEMO-001',
            'description' => '<p>Rock &amp; roll</p>',
            'is_active' => true,
        ]);

        $html = $this->get(route('product.show', $product->slug))->assertOk()->getContent();

        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $data = json_decode($m[1] ?? '', true);

        $this->assertIsArray($data, 'The structured data is valid JSON.');
        $this->assertSame('R&B "Deluxe" 12\\ </script><b>', $data['name']);
        $this->assertSame('Rock & roll', $data['description']);
        $this->assertArrayNotHasKey('sku', $data);
        $this->assertStringNotContainsString('DEMO-001', $m[1]);
    }
}
