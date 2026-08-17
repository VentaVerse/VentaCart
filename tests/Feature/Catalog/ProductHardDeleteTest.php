<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductSpecial;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\SeoSuggestion;
use App\Models\Setting;
use Extensions\StockAlerts\Models\StockNotification;
use App\Models\Store;
use App\Models\Tag;
use App\Models\User;
use App\Models\VariantType;
use App\Models\VariantTypeValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Deleting a product means the row is gone.
 *
 * The SKU is what the ERP and every other integration syncs on, so a product
 * that is "deleted" but still holding its SKU is a product that still blocks
 * the SKU. Delete has to be a hard delete for the SKU to be genuinely free.
 *
 * The other half of the rule pulls the opposite way: image FILES are never
 * removed. Catalog media is shared, the same path is routinely referenced by
 * several products, and deleting the file with one product would blank the
 * image on every other product using it. So no code path in the product
 * lifecycle, delete or edit, may touch the disk.
 */
class ProductHardDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(\App\Services\ExtensionManager::class)->install('stock-alerts');
        app(\App\Services\ExtensionManager::class)->bootEnabled();

        Store::create(['id' => 1, 'name' => 'Test Store', 'code' => 'test']);
        Storage::fake('public');
    }

    private function admin(): User
    {
        return User::factory()->create();
    }

    private function product(array $attributes = []): Product
    {
        $suffix = uniqid();

        return Product::create(array_merge([
            'store_id' => 1,
            'name' => 'Tube Screamer ' . $suffix,
            'slug' => 'ts-' . $suffix,
            'sku' => 'TS-' . $suffix,
            'price' => 1000,
            'quantity' => 5,
            'is_active' => true,
        ], $attributes));
    }

    /** Put a real file plus its thumbnail sibling on the fake public disk. */
    private function putImage(string $path): void
    {
        Storage::disk('public')->put($path, 'binary');

        $info = pathinfo($path);
        Storage::disk('public')->put(
            $info['dirname'] . '/' . $info['filename'] . '_thumb.' . ($info['extension'] ?? 'jpg'),
            'binary'
        );
    }

    private function assertImageOnDisk(string $path): void
    {
        $info = pathinfo($path);
        $thumb = $info['dirname'] . '/' . $info['filename'] . '_thumb.' . ($info['extension'] ?? 'jpg');

        $this->assertTrue(Storage::disk('public')->exists($path), "Image file {$path} was deleted from disk.");
        $this->assertTrue(Storage::disk('public')->exists($thumb), "Thumbnail {$thumb} was deleted from disk.");
    }

    /** The full payload the admin edit form posts, so update() validates. */
    private function updatePayload(Product $product, array $overrides = []): array
    {
        return array_merge([
            'store_id' => 1,
            'name' => $product->name,
            'sku' => $product->sku,
            'price' => 1000,
            'quantity' => 5,
            'is_active' => 1,
            'track_stock' => 1,
            'minimum_quantity' => 1,
            'images_json' => '[]',
        ], $overrides);
    }

    // ---------------------------------------------------------------- the row

    public function test_products_table_has_no_deleted_at_column(): void
    {
        $this->assertFalse(
            \Illuminate\Support\Facades\Schema::hasColumn('products', 'deleted_at'),
            'products.deleted_at still exists, so delete is not a hard delete.'
        );
    }

    public function test_deleting_a_product_removes_the_row_and_frees_the_sku(): void
    {
        $product = $this->product(['sku' => 'FREE-ME']);

        $this->actingAs($this->admin())
            ->delete(route('admin.products.destroy', $product))
            ->assertRedirect(route('admin.products.index'));

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('products', ['sku' => 'FREE-ME']);
        $this->assertNull(Product::withoutGlobalScopes()->find($product->id));

        // The SKU is genuinely available again, which is the whole point.
        $reused = $this->product(['sku' => 'FREE-ME', 'name' => 'Replacement', 'slug' => 'replacement']);

        $this->assertNotEquals($product->id, $reused->id);
        $this->assertDatabaseHas('products', ['id' => $reused->id, 'sku' => 'FREE-ME']);
    }

    public function test_admin_can_recreate_a_deleted_sku_through_the_form_validator(): void
    {
        $product = $this->product(['sku' => 'DUP-CHECK']);
        $admin = $this->admin();

        $this->actingAs($admin)->delete(route('admin.products.destroy', $product));

        $this->actingAs($admin)
            ->post(route('admin.products.store'), [
                'store_id' => 1,
                'name' => 'Brand New Thing',
                'sku' => 'DUP-CHECK',
                'price' => 500,
                'quantity' => 1,
                'is_active' => 1,
                'track_stock' => 1,
                'minimum_quantity' => 1,
                'images_json' => '[]',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', ['sku' => 'DUP-CHECK', 'name' => 'Brand New Thing']);
    }

    // -------------------------------------------------------------- the files

    public function test_deleting_a_product_does_not_delete_its_image_files(): void
    {
        $this->putImage('catalog/main.jpg');
        $this->putImage('catalog/gallery.jpg');

        $product = $this->product(['image' => 'catalog/main.jpg']);
        ProductImage::create(['product_id' => $product->id, 'path' => 'catalog/gallery.jpg', 'sort_order' => 0]);

        $this->actingAs($this->admin())->delete(route('admin.products.destroy', $product));

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertImageOnDisk('catalog/main.jpg');
        $this->assertImageOnDisk('catalog/gallery.jpg');
    }

    public function test_deleting_a_product_leaves_a_shared_image_intact_for_the_other_product(): void
    {
        $this->putImage('catalog/shared.jpg');
        $this->putImage('catalog/shared-gallery.jpg');

        $doomed = $this->product(['image' => 'catalog/shared.jpg']);
        ProductImage::create(['product_id' => $doomed->id, 'path' => 'catalog/shared-gallery.jpg', 'sort_order' => 0]);

        $survivor = $this->product(['name' => 'Survivor', 'slug' => 'survivor', 'image' => 'catalog/shared.jpg']);
        ProductImage::create(['product_id' => $survivor->id, 'path' => 'catalog/shared-gallery.jpg', 'sort_order' => 0]);

        $admin = $this->admin();
        $this->actingAs($admin)->delete(route('admin.products.destroy', $doomed));

        $this->assertDatabaseMissing('products', ['id' => $doomed->id]);

        // The survivor keeps both its rows and both its files.
        $this->assertSame('catalog/shared.jpg', $survivor->fresh()->image);
        $this->assertDatabaseHas('product_images', [
            'product_id' => $survivor->id,
            'path' => 'catalog/shared-gallery.jpg',
        ]);
        $this->assertImageOnDisk('catalog/shared.jpg');
        $this->assertImageOnDisk('catalog/shared-gallery.jpg');

        // And it still renders: the edit screen serves the same paths back.
        // The image manager hands them to Alpine as JSON, where json_encode
        // escapes the slashes, so unescape before looking.
        $response = $this->actingAs($admin)->get(route('admin.products.edit', $survivor));
        $response->assertOk();
        $html = str_replace('\\/', '/', $response->getContent());
        $this->assertStringContainsString('catalog/shared.jpg', $html);
        $this->assertStringContainsString('catalog/shared-gallery.jpg', $html);
    }

    public function test_editing_a_product_to_remove_an_image_does_not_delete_the_file(): void
    {
        $this->putImage('catalog/keep.jpg');
        $this->putImage('catalog/dropped.jpg');

        $edited = $this->product(['image' => 'catalog/keep.jpg']);
        ProductImage::create(['product_id' => $edited->id, 'path' => 'catalog/dropped.jpg', 'sort_order' => 0]);

        // A second product references the image being unlinked.
        $other = $this->product(['name' => 'Other', 'slug' => 'other', 'image' => 'catalog/dropped.jpg']);

        $this->actingAs($this->admin())
            ->put(route('admin.products.update', $edited), $this->updatePayload($edited, [
                'images_json' => json_encode([['path' => 'catalog/keep.jpg']]),
            ]))
            ->assertSessionHasNoErrors();

        // The link is gone from the edited product.
        $this->assertDatabaseMissing('product_images', [
            'product_id' => $edited->id,
            'path' => 'catalog/dropped.jpg',
        ]);
        $this->assertSame('catalog/keep.jpg', $edited->fresh()->image);

        // The file is not.
        $this->assertImageOnDisk('catalog/dropped.jpg');
        $this->assertImageOnDisk('catalog/keep.jpg');
        $this->assertSame('catalog/dropped.jpg', $other->fresh()->image);
    }

    public function test_clearing_every_image_on_edit_still_leaves_the_files_alone(): void
    {
        $this->putImage('catalog/orphan.jpg');

        $edited = $this->product(['image' => 'catalog/orphan.jpg']);

        $this->actingAs($this->admin())
            ->put(route('admin.products.update', $edited), $this->updatePayload($edited, ['images_json' => '[]']))
            ->assertSessionHasNoErrors();

        $this->assertNull($edited->fresh()->image);
        $this->assertImageOnDisk('catalog/orphan.jpg');
    }

    // ------------------------------------------------------------- the orders

    public function test_order_history_survives_a_product_delete(): void
    {
        $status = OrderStatus::create([
            'store_id' => 1,
            'name' => 'Pending',
            'deducts_stock' => false,
            'sort_order' => 1,
        ]);

        $product = $this->product(['sku' => 'HIST-1', 'image' => 'catalog/hist.jpg']);
        $this->putImage('catalog/hist.jpg');

        $order = Order::create([
            'store_id' => 1,
            'order_status_id' => $status->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'shipping_first_name' => 'Jane',
            'shipping_last_name' => 'Doe',
            'shipping_address_1' => '123 Mango St',
            'shipping_city' => 'Cebu City',
            'shipping_state' => 'Cebu',
            'payment_method' => 'bank_transfer',
            'subtotal' => 1000,
            'shipping_cost' => 100,
            'total' => 1100,
        ]);

        $line = OrderProduct::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name' => 'Tube Screamer TS-808',
            'sku' => 'HIST-1',
            'image' => 'catalog/hist.jpg',
            'variant_label' => 'Green',
            'price' => 500,
            'quantity' => 2,
            'total' => 1000,
        ]);

        $this->actingAs($this->admin())->delete(route('admin.products.destroy', $product));

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseHas('orders', ['id' => $order->id]);

        $line = $line->fresh();
        $this->assertNotNull($line, 'The order line was deleted with the product.');
        $this->assertNull($line->product_id, 'product_id should be nulled, not left dangling.');
        $this->assertSame('Tube Screamer TS-808', $line->name);
        $this->assertSame('HIST-1', $line->sku);
        $this->assertSame('catalog/hist.jpg', $line->image);
        $this->assertSame('Green', $line->variant_label);
        $this->assertSame('500.00', (string) $line->price);
        $this->assertSame(2, $line->quantity);
        $this->assertSame('1000.00', (string) $line->total);

        // The snapshot image is a file too, and it stays put.
        $this->assertImageOnDisk('catalog/hist.jpg');

        // And the page still prints the line, off the order's own columns.
        $this->actingAs($this->admin())
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Tube Screamer TS-808')
            ->assertSee('HIST-1')
            ->assertSee('Green')
            ->assertSee('catalog/hist.jpg');
    }

    // ------------------------------------------------------------ the cascade

    /** Build a product wearing one of everything that hangs off it. */
    private function productWithChildren(string $sku): array
    {
        $product = $this->product(['sku' => $sku, 'has_variants' => true]);

        $type = VariantType::create(['product_id' => $product->id, 'name' => 'Color', 'sort_order' => 0]);
        $value = VariantTypeValue::create(['variant_type_id' => $type->id, 'value' => 'Red', 'sort_order' => 0]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => $sku . '-RED',
            'price' => 1200,
            'quantity' => 3,
        ]);
        $variant->values()->attach($value->id);

        $image = ProductImage::create(['product_id' => $product->id, 'path' => 'catalog/casc.jpg', 'sort_order' => 0]);
        $special = ProductSpecial::create([
            'product_id' => $product->id,
            'price' => 800,
            'quantity' => 1,
            'display_mode' => 'sale',
            'priority' => 0,
        ]);

        $category = Category::create(['store_id' => 1, 'name' => 'Pedals', 'slug' => 'pedals-' . $sku]);
        $product->categories()->attach($category->id);

        $tag = Tag::create(['name' => 'Fuzz', 'slug' => 'fuzz-' . strtolower($sku)]);
        $product->tags()->attach($tag->id);

        $review = Review::create([
            'product_id' => $product->id,
            'token' => 'tok-' . $sku,
            'name' => 'Rey',
            'email' => 'rey@example.com',
            'rating' => 5,
            'status' => 'approved',
        ]);

        $notification = StockNotification::create([
            'store_id' => 1,
            'product_id' => $product->id,
            'name' => 'Rey',
            'email' => 'rey-' . $sku . '@example.com',
            'status' => 'waiting',
        ]);

        $suggestion = SeoSuggestion::create([
            'product_id' => $product->id,
            'suggested_meta_title' => 'Better title',
            'status' => 'pending',
            'generated_at' => now(),
        ]);

        return compact('product', 'type', 'value', 'variant', 'image', 'special', 'category', 'tag', 'review', 'notification', 'suggestion');
    }

    private function assertChildrenGone(array $bag): void
    {
        $product = $bag['product'];

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('product_variants', ['id' => $bag['variant']->id]);
        $this->assertDatabaseMissing('product_variant_values', ['product_variant_id' => $bag['variant']->id]);
        $this->assertDatabaseMissing('variant_types', ['id' => $bag['type']->id]);
        $this->assertDatabaseMissing('variant_type_values', ['id' => $bag['value']->id]);
        $this->assertDatabaseMissing('product_images', ['id' => $bag['image']->id]);
        $this->assertDatabaseMissing('product_specials', ['id' => $bag['special']->id]);
        $this->assertDatabaseMissing('category_product', ['product_id' => $product->id]);
        $this->assertDatabaseMissing('product_tag', ['product_id' => $product->id]);
        $this->assertDatabaseMissing('reviews', ['id' => $bag['review']->id]);
        $this->assertDatabaseMissing('stock_notifications', ['id' => $bag['notification']->id]);
        $this->assertDatabaseMissing('seo_suggestions', ['id' => $bag['suggestion']->id]);

        // The category and the tag themselves are shared vocabulary and stay.
        $this->assertDatabaseHas('categories', ['id' => $bag['category']->id]);
        $this->assertDatabaseHas('tags', ['id' => $bag['tag']->id]);
    }

    public function test_admin_delete_cascades_every_child_row(): void
    {
        $bag = $this->productWithChildren('CASC-ADMIN');

        $this->actingAs($this->admin())->delete(route('admin.products.destroy', $bag['product']));

        $this->assertChildrenGone($bag);
    }

    // ---------------------------------------------------------------- the ERP

    private function erpHeaders(): array
    {
        Setting::create(['group' => 'api', 'key' => 'erp_token', 'value' => Crypt::encryptString('erp-test-token')]);

        return ['Authorization' => 'Bearer erp-test-token', 'Accept' => 'application/json'];
    }

    public function test_erp_delete_hard_deletes_and_cascades(): void
    {
        $headers = $this->erpHeaders();
        $bag = $this->productWithChildren('CASC-ERP');

        $this->deleteJson('/api/v1/products/CASC-ERP', [], $headers)
            ->assertOk()
            ->assertJson(['status' => 'deleted', 'sku' => 'CASC-ERP']);

        $this->assertChildrenGone($bag);
    }

    public function test_erp_post_after_delete_creates_a_new_product_and_never_restores(): void
    {
        $headers = $this->erpHeaders();

        $original = $this->product(['sku' => 'ERP-REUSE', 'name' => 'Original Name']);

        $this->deleteJson('/api/v1/products/ERP-REUSE', [], $headers)->assertOk();
        $this->assertDatabaseMissing('products', ['id' => $original->id]);

        // Dimensions are sent explicitly: products.length/width/height are
        // NOT NULL with a default of 0, and store() passes an explicit null
        // when they are omitted, which is a pre-existing 500 unrelated to
        // hard delete.
        $response = $this->postJson('/api/v1/products', [
            'name' => 'Freshly Pushed',
            'sku' => 'ERP-REUSE',
            'price' => 2500,
            'length' => 0,
            'width' => 0,
            'height' => 0,
        ], $headers);

        $response->assertCreated();
        $this->assertNotSame('restored', $response->json('status'));

        $created = Product::withoutGlobalScopes()->where('sku', 'ERP-REUSE')->firstOrFail();
        $this->assertNotEquals($original->id, $created->id, 'The ERP resurrected the old row instead of creating a new one.');
        $this->assertSame('Freshly Pushed', $created->name);
    }

    public function test_erp_delete_of_an_unknown_sku_is_a_404(): void
    {
        $headers = $this->erpHeaders();

        $this->deleteJson('/api/v1/products/NOPE-404', [], $headers)->assertNotFound();
    }

    public function test_erp_delete_does_not_delete_image_files(): void
    {
        $headers = $this->erpHeaders();

        $this->putImage('catalog/erp-shared.jpg');

        $doomed = $this->product(['sku' => 'ERP-IMG', 'image' => 'catalog/erp-shared.jpg']);
        ProductImage::create(['product_id' => $doomed->id, 'path' => 'catalog/erp-shared.jpg', 'sort_order' => 0]);

        $survivor = $this->product(['name' => 'Survivor', 'slug' => 'erp-survivor', 'image' => 'catalog/erp-shared.jpg']);

        $this->deleteJson('/api/v1/products/ERP-IMG', [], $headers)->assertOk();

        $this->assertDatabaseMissing('products', ['id' => $doomed->id]);
        $this->assertImageOnDisk('catalog/erp-shared.jpg');
        $this->assertSame('catalog/erp-shared.jpg', $survivor->fresh()->image);
    }
}
