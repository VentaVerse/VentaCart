<?php

namespace Tests\Feature\Media;

use App\Models\Brand;
use App\Models\Category;
use App\Models\NavMenu;
use App\Models\NavMenuItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Services\ExtensionManager;
use Extensions\Blog\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * An image field accepts a changed value only when it names a picture in the
 * media library. A value the record already held still saves, so art that
 * predates the library never blocks an unrelated edit.
 */
class MediaImageRuleTest extends TestCase
{
    use RefreshDatabase;

    private const IMAGE = 'media/products/demo-001.jpg';

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Demo Store']);
        Storage::fake('public');
        Storage::disk('public')->put(self::IMAGE, 'bytes');
        Storage::disk('public')->put('identity/logo.png', 'bytes');
        Storage::disk('public')->put('bank-qr/a.png', 'bytes');
        $this->actingAs(User::factory()->create());
    }

    private function product(array $attributes = []): Product
    {
        return Product::create($attributes + [
            'store_id' => 1, 'name' => 'Demo Product', 'slug' => 'demo-product',
            'sku' => 'DEMO-001', 'price' => 100, 'is_active' => true,
        ]);
    }

    private function productForm(array $extra = []): array
    {
        return $extra + ['store_id' => 1, 'name' => 'Demo Product', 'price' => 100, 'is_active' => '1'];
    }

    private function gallery(string ...$paths): string
    {
        return json_encode(array_map(fn ($p) => ['path' => $p], $paths));
    }

    public function test_category_image_accepts_a_library_image(): void
    {
        $this->post(route('admin.categories.store'), [
            'store_id' => 1, 'name' => 'Phones', 'image' => self::IMAGE,
        ])->assertSessionHasNoErrors();

        $this->assertSame(self::IMAGE, Category::where('name', 'Phones')->value('image'));
    }

    public function test_brand_image_accepts_a_library_image(): void
    {
        $this->post(route('admin.brands.store'), [
            'store_id' => 1, 'name' => 'Demo Brand', 'image' => self::IMAGE,
        ])->assertSessionHasNoErrors();

        $this->assertSame(self::IMAGE, Brand::where('name', 'Demo Brand')->value('image'));
    }

    public function test_images_json_accepts_library_images(): void
    {
        $this->post(route('admin.products.store'), $this->productForm([
            'images_json' => $this->gallery(self::IMAGE),
        ]))->assertSessionHasNoErrors();

        $this->assertSame(self::IMAGE, Product::where('name', 'Demo Product')->value('image'));
    }

    public function test_variant_image_accepts_a_library_image(): void
    {
        $product = $this->product();

        $this->put(route('admin.products.update', $product), $this->productForm([
            'variant_types' => [['name' => 'Color', 'values' => ['Red']]],
            'variants' => [['sku' => 'DEMO-001-R', 'quantity' => 1, 'image' => self::IMAGE, 'value_keys' => ['0_0']]],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(self::IMAGE, ProductVariant::where('sku', 'DEMO-001-R')->value('image'));
    }

    public function test_menu_banner_accepts_a_library_image(): void
    {
        $menu = NavMenu::firstOrCreate(['key' => NavMenu::MAIN], ['name' => 'Main menu']);
        $category = Category::create(['store_id' => 1, 'name' => 'Phones', 'slug' => 'phones', 'is_active' => true]);

        $this->post(route('admin.menus.items.store', $menu), [
            'type' => 'category', 'label' => 'Phones', 'category_id' => $category->id, 'banner' => self::IMAGE,
        ])->assertSessionHasNoErrors();

        $this->assertSame(self::IMAGE, NavMenuItem::first()->config['banner']);
    }

    public function test_blog_featured_image_accepts_a_library_image(): void
    {
        app(ExtensionManager::class)->install('blog');
        app(ExtensionManager::class)->bootEnabled();

        $this->post(route('admin.blog.store'), [
            'title' => 'Demo Post', 'content' => 'Hello', 'featured_image' => self::IMAGE,
        ])->assertSessionHasNoErrors();

        $this->assertSame(self::IMAGE, BlogPost::first()->featured_image);

        $this->put(route('admin.blog.update', BlogPost::first()), [
            'title' => 'Demo Post', 'content' => 'Hello', 'featured_image' => 'identity/logo.png',
        ])->assertSessionHasErrors('featured_image');
    }

    public function test_a_path_that_no_longer_exists_is_refused_with_a_plain_message(): void
    {
        $this->post(route('admin.categories.store'), [
            'store_id' => 1, 'name' => 'Phones', 'image' => 'media/products/gone.jpg',
        ])->assertSessionHasErrors(['image' => 'That image was moved or deleted. Choose it again.']);

        $this->assertSame(0, Category::count());
    }

    public function test_a_changed_path_outside_the_library_is_refused(): void
    {
        foreach (['identity/logo.png', 'bank-qr/a.png', '../.env', 'media', 'media/products/demo-001.php'] as $path) {
            $this->post(route('admin.categories.store'), [
                'store_id' => 1, 'name' => 'Phones', 'image' => $path,
            ])->assertSessionHasErrors(['image' => 'Choose an image from the media library.']);
        }

        $this->assertSame(0, Category::count());
    }

    public function test_an_unchanged_image_outside_the_library_does_not_block_a_save(): void
    {
        $product = $this->product(['image' => 'products/demo-001.jpg']);
        $category = Category::create([
            'store_id' => 1, 'name' => 'Phones', 'slug' => 'phones', 'is_active' => true,
            'image' => 'categories/demo-phones.jpg',
        ]);

        $this->put(route('admin.products.update', $product), $this->productForm([
            'name' => 'Renamed Product', 'images_json' => $this->gallery('products/demo-001.jpg'),
        ]))->assertSessionHasNoErrors();
        $this->put(route('admin.categories.update', $category), [
            'store_id' => 1, 'name' => 'Mobile Phones', 'is_active' => 1, 'image' => 'categories/demo-phones.jpg',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Renamed Product', $product->fresh()->name);
        $this->assertSame('Mobile Phones', $category->fresh()->name);
    }

    public function test_a_product_save_with_one_bad_new_gallery_path_saves_nothing(): void
    {
        $product = $this->product(['image' => self::IMAGE]);

        $this->put(route('admin.products.update', $product), $this->productForm([
            'name' => 'Renamed Product',
            'images_json' => $this->gallery(self::IMAGE, 'identity/logo.png'),
        ]))->assertSessionHasErrors('images_json');

        $this->assertSame('Demo Product', $product->fresh()->name);
        $this->assertSame(self::IMAGE, $product->fresh()->image);
        $this->assertSame(0, $product->images()->count());
    }
}
