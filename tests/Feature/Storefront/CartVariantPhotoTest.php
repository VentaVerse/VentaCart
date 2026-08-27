<?php

namespace Tests\Feature\Storefront;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The storefront line shows the colourway the shopper actually picked.
 *
 * The cart page, the mini cart and the two checkout summaries each answered
 * "which picture" with their own expression, and not one of them asked the
 * variant. All four now read CartItem::image, resolved in one place:
 * the variant's own photo, then the product's primary, then its first
 * gallery image.
 */
class CartVariantPhotoTest extends TestCase
{
    use RefreshDatabase;

    private function product(?string $primary = 'catalog/parent.jpg'): Product
    {
        return Product::create([
            'store_id'     => 1,
            'name'         => 'Mogami 2524 1/4 TS Straight to Angled Cable',
            'slug'         => 'mogami-2524',
            'sku'          => 'MOG-2524',
            'price'        => 1200,
            'quantity'     => 10,
            'is_active'    => true,
            'has_variants' => true,
            'image'        => $primary,
        ]);
    }

    private function line(Product $product, ?int $variantId): CartItem
    {
        return new CartItem([
            'product_id'         => $product->id,
            'product_variant_id' => $variantId,
            'name'               => $product->name,
            'price'              => 2000,
            'quantity'           => 1,
        ]);
    }

    public function test_the_variants_own_photo_wins(): void
    {
        $product = $this->product();
        $variant = $product->variants()->create([
            'sku' => 'MOG-15', 'name' => 'Cable Length: 15 Ft',
            'price' => 2000, 'quantity' => 4, 'is_active' => true,
            'image' => 'catalog/variants/15ft.jpg',
        ]);

        $item = $this->line($product, $variant->id);
        $item->setRelation('productVariant', $variant);
        $item->setRelation('product', $product);

        $this->assertSame('catalog/variants/15ft.jpg', $item->image);
    }

    public function test_a_variant_with_no_photo_falls_back_to_the_primary(): void
    {
        $product = $this->product();
        $variant = $product->variants()->create([
            'sku' => 'MOG-3', 'name' => 'Cable Length: 3 Ft',
            'price' => 900, 'quantity' => 6, 'is_active' => true,
        ]);

        $item = $this->line($product, $variant->id);
        $item->setRelation('productVariant', $variant);
        $item->setRelation('product', $product);

        $this->assertSame('catalog/parent.jpg', $item->image);
    }

    public function test_no_primary_falls_back_to_the_first_gallery_image(): void
    {
        $product = $this->product(null);
        ProductImage::create(['product_id' => $product->id, 'path' => 'catalog/gallery/one.jpg', 'sort_order' => 0]);

        $item = $this->line($product, null);
        $item->setRelation('productVariant', null);
        $item->setRelation('product', $product->fresh('images'));

        $this->assertSame('catalog/gallery/one.jpg', $item->image);
    }

    public function test_a_line_with_nothing_at_all_resolves_to_null(): void
    {
        $product = $this->product(null);

        $item = $this->line($product, null);
        $item->setRelation('productVariant', null);
        $item->setRelation('product', $product->fresh('images'));

        $this->assertNull($item->image);
    }

    public function test_the_photo_is_serialized_into_the_alpine_payload(): void
    {
        // The cart page and both checkout summaries read item.image straight
        // off the serialized model, so it has to be appended.
        $product = $this->product();
        $variant = $product->variants()->create([
            'sku' => 'MOG-15', 'name' => 'Cable Length: 15 Ft',
            'price' => 2000, 'quantity' => 4, 'is_active' => true,
            'image' => 'catalog/variants/15ft.jpg',
        ]);

        $item = $this->line($product, $variant->id);
        $item->setRelation('productVariant', $variant);
        $item->setRelation('product', $product);

        $this->assertArrayHasKey('image', $item->toArray());
        $this->assertSame('catalog/variants/15ft.jpg', $item->toArray()['image']);
    }
}
