<?php

namespace Tests\Feature\AdminV2;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A variant's own photo has to reach the order surfaces: the order view, the
 * add and edit form, and the confirmation email.
 *
 * It never did. order_products.image was written from the PARENT product at
 * every write site, so a "Cable Length: 15 Ft" line wore the same picture as
 * every other length. The three read surfaces were innocent; each already
 * rendered the line's image and was simply handed the wrong path.
 *
 * The order LIST is deliberately not covered here. It carries no product
 * information at all, so photos there would be decoration (owner ruling).
 */
class OrderVariantPhotoTest extends TestCase
{
    use RefreshDatabase;

    private function productWithPhotographedVariant(): Product
    {
        $product = Product::create([
            'store_id' => 1,
            'name'     => 'Mogami 2524 1/4 TS Straight to Angled Cable',
            'slug'     => 'mogami-2524-straight-angled',
            'sku'      => 'MOG-2524',
            'price'    => 1200,
            'quantity'     => 10,
            'is_active'    => true,
            'has_variants' => true,
            'image'        => 'catalog/parent.jpg',
        ]);

        $product->variants()->create([
            'sku'       => 'MOG-2524-15',
            'name'      => 'Cable Length: 15 Ft',
            'price'     => 2000,
            'quantity'  => 4,
            'is_active' => true,
            'image'     => 'catalog/variants/15ft.jpg',
        ]);

        $product->variants()->create([
            'sku'       => 'MOG-2524-3',
            'name'      => 'Cable Length: 3 Ft',
            'price'     => 900,
            'quantity'  => 6,
            'is_active' => true,
            // Deliberately no photo of its own.
        ]);

        return $product->fresh('variants');
    }

    private function orderLine(string $image, string $label): Order
    {
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Processing', 'sort_order' => 2]);

        $order = Order::create([
            'store_id'            => 1,
            'order_status_id'     => $status->id,
            'first_name'          => 'June',
            'last_name'           => 'Sillacos',
            'email'               => 'june@example.com',
            'phone'               => '09171234567',
            'shipping_first_name' => 'June',
            'shipping_last_name'  => 'Sillacos',
            'shipping_address_1'  => '123 Mango St',
            'shipping_city'       => 'Cebu City',
            'shipping_postcode'   => '6000',
            'subtotal'            => 2000,
            'total'               => 2000,
        ]);

        $order->products()->create([
            'name'          => 'Mogami 2524 1/4 TS Straight to Angled Cable',
            'sku'           => 'MOG-2524-15',
            'variant_label' => $label,
            'image'         => $image,
            'price'         => 2000,
            'quantity'      => 1,
            'total'         => 2000,
        ]);

        return $order;
    }

    // ---------------------------------------------------------- order view

    public function test_the_order_view_shows_the_variants_own_photo(): void
    {
        $order = $this->orderLine('catalog/variants/15ft.jpg', 'Cable Length: 15 Ft');

        $this->actingAs(User::factory()->create())
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('catalog/variants/15ft.jpg', false)
            ->assertSee('Cable Length: 15 Ft', false)
            ->assertDontSee('catalog/parent.jpg', false);
    }

    public function test_a_line_with_no_photo_still_renders_a_tile(): void
    {
        // Without a placeholder the row height jumps between lines.
        $order = $this->orderLine('', 'Cable Length: 3 Ft');

        $this->actingAs(User::factory()->create())
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('v-thumb--blank', false);
    }

    // ------------------------------------------------- order add and edit

    public function test_the_product_search_offers_each_variants_own_photo(): void
    {
        $this->productWithPhotographedVariant();

        $rows = $this->actingAs(User::factory()->create())
            ->getJson(route('admin.orders.search-products', ['store_id' => 1, 'q' => 'Mogami']))
            ->assertOk()
            ->json();

        $fifteen = collect($rows)->firstWhere('sku', 'MOG-2524-15');
        $this->assertNotNull($fifteen, 'The 15 Ft variant was not offered at all.');
        $this->assertStringContainsString('catalog/variants/15ft.jpg', $fifteen['image']);
    }

    public function test_a_variant_with_no_photo_falls_back_to_the_product(): void
    {
        $this->productWithPhotographedVariant();

        $rows = $this->actingAs(User::factory()->create())
            ->getJson(route('admin.orders.search-products', ['store_id' => 1, 'q' => 'Mogami']))
            ->assertOk()
            ->json();

        $three = collect($rows)->firstWhere('sku', 'MOG-2524-3');
        $this->assertNotNull($three);
        $this->assertStringContainsString('catalog/parent.jpg', $three['image']);
    }

    public function test_saving_an_order_snapshots_the_variants_photo(): void
    {
        $product = $this->productWithPhotographedVariant();
        $variant = $product->variants->firstWhere('sku', 'MOG-2524-15');
        $order = $this->orderLine('catalog/parent.jpg', 'Cable Length: 15 Ft');

        $response = $this->actingAs(User::factory()->create())
            ->put(route('admin.orders.update', $order), [
                'store_id'            => 1,
                'first_name'          => 'June',
                'last_name'           => 'Sillacos',
                'email'               => 'june@example.com',
                'phone'               => '09171234567',
                'shipping_first_name' => 'June',
                'shipping_last_name'  => 'Sillacos',
                'shipping_address_1'  => '123 Mango St',
                'shipping_city'       => 'Cebu City',
                'shipping_postcode'   => '6000',
                'order_status_id'     => $order->order_status_id,
                'items'               => [[
                    'product_id'         => $product->id,
                    'product_variant_id' => $variant->id,
                    'name'               => $product->name,
                    'sku'                => $variant->sku,
                    'variant_label'      => $variant->name,
                    'price'              => 2000,
                    'quantity'           => 1,
                ]],
            ]);

        $response->assertSessionHasNoErrors();

        $this->assertSame(
            'catalog/variants/15ft.jpg',
            $order->fresh()->products->first()->image,
            'The saved line kept the parent photo instead of the colourway that was sold.'
        );
    }

    // ------------------------------------------------------------- email

    public function test_the_confirmation_email_shows_the_variants_photo(): void
    {
        $order = $this->orderLine('catalog/variants/15ft.jpg', 'Cable Length: 15 Ft');

        $html = view('emails.order-confirmation', [
            'order'    => $order->fresh('products'),
            'settings' => collect(),
        ])->render();

        $this->assertStringContainsString('catalog/variants/15ft.jpg', $html);
        $this->assertStringContainsString('Cable Length: 15 Ft', $html);
    }
}
