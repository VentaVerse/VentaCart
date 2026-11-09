<?php

namespace Tests\Feature\AdminV2;

use App\Models\Setting;
use App\Models\User;
use App\Services\ExtensionManager;
use App\Services\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The default theme's look is its own and cannot be edited: its colors and
 * fonts live in its stylesheet, not in settings. What a merchant does
 * decide is the store's rules (Settings > Catalog, Blog settings) and the
 * arrangement of its pages (Layout).
 */
class DefaultThemeLookIsFixedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_the_theme_declares_no_editable_look(): void
    {
        $meta = json_decode((string) file_get_contents(resource_path('themes/'.ThemeService::THEME.'/theme.json')), true);

        foreach (['colors', 'fonts', 'settings', 'vars'] as $key) {
            $this->assertArrayNotHasKey($key, $meta);
        }
        $this->assertDirectoryDoesNotExist(resource_path('themes/'.ThemeService::THEME.'/presets'));
    }

    public function test_stored_colors_and_fonts_do_not_reach_the_storefront(): void
    {
        Setting::create(['group' => 'colors', 'key' => 'primary', 'value' => '#ABCDEF']);
        Setting::create(['group' => 'fonts', 'key' => 'heading', 'value' => 'Lobster']);
        app(ThemeService::class)->clearCache();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('#ABCDEF', $html);
        $this->assertStringNotContainsString('Lobster', $html);
        $this->assertStringContainsString('family=Figtree', $html);
    }

    public function test_the_catalog_settings_reach_the_category_page(): void
    {
        $category = \App\Models\Category::create(['store_id' => 1, 'name' => 'Cables', 'slug' => 'cables', 'is_active' => true]);
        foreach (range(1, 6) as $i) {
            $product = \App\Models\Product::create(['store_id' => 1, 'name' => "Demo cable {$i}", 'slug' => "demo-cable-{$i}", 'sku' => "DEMO-00{$i}", 'price' => 100, 'quantity' => 5, 'is_active' => true]);
            $product->categories()->attach($category->id);
        }
        Setting::updateOrCreate(['group' => 'category_page', 'key' => 'products_per_page'], ['value' => '4']);
        app(ThemeService::class)->clearCache();

        $html = $this->get('/category/cables')->assertOk()->getContent();
        $this->assertStringContainsString('<div class="flex flex-col lg:flex-row gap-6">', $html, 'The filters sit beside the grid.');
        preg_match_all('/Demo cable (\d)/', $html, $m);

        $this->assertCount(4, array_unique($m[1]), 'Products per page comes from Settings > Catalog.');
    }

    public function test_blog_settings_save_and_refuse_nonsense(): void
    {
        app(ExtensionManager::class)->install('blog');
        app(ExtensionManager::class)->bootEnabled();
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.blog.settings'))->assertOk()->assertSee('Posts per page');

        $this->post(route('admin.blog.settings.update'), [
            'posts_per_page' => '9', 'default_view' => 'list', 'excerpt_length' => '120',
            'show_view_count' => '1', 'show_related_articles' => '0',
        ])->assertRedirect(route('admin.blog.settings'));

        $this->assertSame('9', Setting::where('group', 'blog')->where('key', 'posts_per_page')->value('value'));
        $this->assertSame('list', Setting::where('group', 'blog')->where('key', 'default_view')->value('value'));
        $this->assertSame('0', Setting::where('group', 'blog')->where('key', 'show_related_articles')->value('value'));

        $this->post(route('admin.blog.settings.update'), [
            'posts_per_page' => '500', 'default_view' => 'carousel', 'excerpt_length' => '120',
        ])->assertSessionHasErrors(['posts_per_page', 'default_view']);
        $this->assertSame('9', Setting::where('group', 'blog')->where('key', 'posts_per_page')->value('value'));
    }
}
