<?php

namespace Tests\Feature\Media;

use App\Models\Brand;
use App\Models\Category;
use App\Models\NavMenu;
use App\Models\NavMenuItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\ExtensionManager;
use Extensions\Blog\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Every admin image field opens the shared media library. Each field says
 * which kind of image it holds, so the library can reopen the folder last
 * used for that kind, and a page with several fields prints the library's
 * settings once.
 */
class MediaFieldsTest extends TestCase
{
    use RefreshDatabase;

    private const IMAGE = 'media/products/demo-001.jpg';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Demo Store']);
        Storage::fake('public');
        Storage::disk('public')->put(self::IMAGE, 'bytes');
        $this->actingAs(User::factory()->create());
    }

    private function assertSharedPicker(string $html, string $kind, int $fields = 1): void
    {
        $this->assertSame(1, substr_count($html, 'id="media-library-config"'), 'the library settings print once');
        $this->assertSame($fields, substr_count($html, 'data-kind="' . $kind . '"'), "the {$kind} field names its kind");
    }

    public function test_each_image_field_renders_the_shared_picker(): void
    {
        $product = Product::create([
            'store_id' => 1, 'name' => 'Demo Product', 'slug' => 'demo-product',
            'sku' => 'DEMO-001', 'price' => 100, 'is_active' => true, 'image' => self::IMAGE,
        ]);
        // The gallery and the variant photo are both product images.
        $html = $this->get(route('admin.products.edit', $product))->assertOk()->getContent();
        $this->assertSharedPicker($html, 'products', 2);
        // The gallery's button says what the window it opens is called.
        $this->assertStringContainsString('@click="openPicker($event)">Add images</button>', $html);
        $this->assertStringNotContainsString('Image manager', $html);

        $category = Category::create(['store_id' => 1, 'name' => 'Phones', 'slug' => 'phones', 'is_active' => true, 'image' => self::IMAGE]);
        $html = $this->get(route('admin.categories.edit', $category))->assertOk()->getContent();
        $this->assertSharedPicker($html, 'categories');
        $this->assertStringContainsString('value="' . self::IMAGE . '"', $html, 'the hidden input carries the library path');
        $this->assertStringContainsString('demo-001.jpg', $html, 'the preview names the chosen image');

        $brand = Brand::create(['store_id' => 1, 'name' => 'Demo Brand', 'slug' => 'demo-brand']);
        $this->assertSharedPicker($this->get(route('admin.brands.edit', $brand))->assertOk()->getContent(), 'brands');

        $menu = NavMenu::firstOrCreate(['key' => NavMenu::MAIN], ['name' => 'Main menu']);
        $item = NavMenuItem::create([
            'menu_id' => $menu->id, 'type' => 'category', 'label' => 'Phones',
            'category_id' => $category->id, 'sort_order' => 1, 'is_enabled' => true, 'config' => ['banner' => self::IMAGE],
        ]);
        $this->assertSharedPicker($this->get(route('admin.menus.items.edit', $item))->assertOk()->getContent(), 'banners');

        app(ExtensionManager::class)->install('blog');
        app(ExtensionManager::class)->bootEnabled();
        $post = BlogPost::create(['title' => 'Demo Post', 'slug' => 'demo-post', 'content' => 'Hello']);
        $this->assertSharedPicker($this->get(route('admin.blog.edit', $post))->assertOk()->getContent(), 'blog');
    }

    public function test_the_picker_no_longer_ships_its_own_modal(): void
    {
        $category = Category::create(['store_id' => 1, 'name' => 'Phones', 'slug' => 'phones', 'is_active' => true]);
        $html = $this->get(route('admin.categories.edit', $category))->assertOk()->getContent();

        $this->assertStringNotContainsString('v-modal--media', $html);
        $this->assertStringNotContainsString('v-mpick__grid', $html);
        $this->assertStringNotContainsString('Choose an image from', $html);

        $component = file_get_contents(resource_path('views/components/admin/media-picker.blade.php'));
        $this->assertStringContainsString('<x-media-library />', $component);
        $this->assertStringNotContainsString('role="dialog"', $component);
        $this->assertStringNotContainsString("'commit'", $component);
        $this->assertStringNotContainsString("'can'", $component);

        // The field asks the library for files; it never talks to the server itself.
        $script = file_get_contents(resource_path('js/admin/media-picker.js'));
        $this->assertStringContainsString('VentaCart', $script);
        $this->assertStringNotContainsString('fetch(', $script);
        $this->assertStringNotContainsString('media:uploaded', $script);

        $css = file_get_contents(resource_path('css/admin/components/ui.css'));
        $this->assertStringNotContainsString('.v-modal--media', $css);
        $this->assertStringNotContainsString('.v-mpick__grid', $css);
        $this->assertStringNotContainsString('.v-mediatile', $css);
    }
}
