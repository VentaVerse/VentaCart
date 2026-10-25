<?php

namespace Tests\Feature\AdminV2;

use App\Models\Brand;
use App\Models\Category;
use App\Models\LayoutPlacement;
use App\Models\NavMenu;
use App\Models\NavMenuItem;
use App\Models\Page;
use App\Services\ThemeService;
use App\Support\NavTree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The menu composer's data (Plaza plan, phase 4a): menus are records,
 * items nest, every type resolves to a URL and a dropdown, and the header
 * draws the tree.
 */
class NavTreeTest extends TestCase
{
    use RefreshDatabase;

    private NavMenu $main;

    protected function setUp(): void
    {
        parent::setUp();
        $this->main = NavMenu::main();
    }

    private function item(array $attrs): NavMenuItem
    {
        return NavMenuItem::create($attrs + ['menu_id' => $this->main->id, 'type' => 'custom', 'sort_order' => NavMenuItem::max('sort_order') + 1, 'is_enabled' => true]);
    }

    public function test_the_main_menu_exists_once_and_existing_items_belong_to_it(): void
    {
        $this->assertSame($this->main->id, NavMenu::main()->id);
        $item = $this->item(['label' => 'Blog', 'url' => '/blog']);
        $this->assertSame($this->main->id, $item->menu_id);
    }

    public function test_every_item_type_resolves_where_it_leads(): void
    {
        $cat = Category::create(['store_id' => 1, 'name' => 'Pedals', 'slug' => 'pedals', 'is_active' => true]);
        $page = Page::create(['store_id' => 1, 'title' => 'About', 'slug' => 'about', 'body' => 'x', 'is_active' => true]);

        // Relative, so the cached menu works on whatever address the store is reached by.
        $this->assertSame('/category/pedals', $this->item(['label' => 'Pedals', 'type' => 'category', 'category_id' => $cat->id])->resolved_url);
        $this->assertSame('/about', $this->item(['label' => 'About', 'type' => 'page', 'page_id' => $page->id])->resolved_url);
        $this->assertSame('/', $this->item(['label' => 'Home', 'type' => 'home'])->resolved_url);
        $this->assertSame(route('brand.index', absolute: false), $this->item(['label' => 'Brands', 'type' => 'brands'])->resolved_url);
        $this->assertSame(route('cart.index', absolute: false), $this->item(['label' => 'Cart', 'type' => 'system', 'config' => ['page' => 'cart']])->resolved_url);
        $this->assertSame('https://shopee.ph/x', $this->item(['label' => 'Shopee', 'type' => 'custom', 'url' => 'https://shopee.ph/x'])->resolved_url);
        $this->assertNull($this->item(['label' => 'More', 'type' => 'title'])->resolved_url);
        $this->assertNull($this->item(['label' => '', 'type' => 'separator'])->resolved_url);
    }

    public function test_a_menu_cached_under_one_address_still_works_on_another(): void
    {
        $parent = Category::create(['store_id' => 1, 'name' => 'Guitars', 'slug' => 'guitars', 'is_active' => true]);
        Category::create(['store_id' => 1, 'name' => 'Electric', 'slug' => 'electric', 'parent_id' => $parent->id, 'is_active' => true]);
        $this->item(['label' => 'Guitars', 'type' => 'category', 'category_id' => $parent->id]);

        // The menu is cached forever, so whichever address builds it first
        // must not end up in every visitor's links.
        \Illuminate\Support\Facades\URL::forceRootUrl('http://127.0.0.1:8002');
        NavTree::forget();
        $tree = NavTree::for();
        \Illuminate\Support\Facades\URL::forceRootUrl(null);

        $this->assertSame('/category/guitars', $tree[0]['url']);
        $this->assertSame('/category/electric', $tree[0]['dropdown']['columns'][0]['url']);
        $this->assertStringNotContainsString('127.0.0.1', json_encode($tree));
    }

    public function test_the_tree_nests_children_and_attaches_a_categorys_subcategories(): void
    {
        $parent = Category::create(['store_id' => 1, 'name' => 'Guitars', 'slug' => 'guitars', 'is_active' => true]);
        $child = Category::create(['store_id' => 1, 'name' => 'Electric', 'slug' => 'electric', 'parent_id' => $parent->id, 'is_active' => true]);
        Category::create(['store_id' => 1, 'name' => 'Strat style', 'slug' => 'strat', 'parent_id' => $child->id, 'is_active' => true]);
        Category::create(['store_id' => 1, 'name' => 'Hidden', 'slug' => 'hidden', 'parent_id' => $parent->id, 'is_active' => false]);

        $top = $this->item(['label' => 'Guitars', 'type' => 'category', 'category_id' => $parent->id, 'config' => ['accent' => '#ff0000']]);
        $this->item(['label' => 'Used', 'url' => '/used', 'parent_id' => $top->id]);
        $this->item(['label' => 'Off', 'url' => '/off', 'parent_id' => $top->id, 'is_enabled' => false]);

        $tree = NavTree::for();

        $this->assertCount(1, $tree);
        $node = $tree[0];
        $this->assertSame('#FF0000', $node['accent']);
        $this->assertSame('list', $node['dropdown']['kind']);
        $this->assertSame(['Electric'], array_column($node['dropdown']['columns'], 'name'), 'Inactive subcategories are left out.');
        $this->assertSame('Strat style', $node['dropdown']['columns'][0]['children'][0]['name']);
        $this->assertSame(['Used'], array_column($node['children'], 'label'), 'A disabled child is left out.');
    }

    public function test_a_mega_menu_carries_columns_brands_and_a_banner_and_the_header_draws_it(): void
    {
        $parent = Category::create(['store_id' => 1, 'name' => 'Guitars', 'slug' => 'guitars', 'is_active' => true]);
        Category::create(['store_id' => 1, 'name' => 'Electric', 'slug' => 'electric', 'parent_id' => $parent->id, 'is_active' => true]);
        Brand::create(['store_id' => 1, 'name' => 'Fender', 'slug' => 'fender', 'is_active' => true]);
        $this->item(['label' => 'Guitars', 'type' => 'category', 'category_id' => $parent->id,
            'config' => ['mega' => 'yes', 'brands' => 'yes', 'banner' => 'banners/guitars.jpg', 'banner_url' => '/category/guitars']]);

        $node = NavTree::for()[0];
        $this->assertSame('mega', $node['dropdown']['kind']);
        $this->assertSame('Fender', $node['dropdown']['brands'][0]['name']);
        $this->assertSame('banners/guitars.jpg', $node['dropdown']['banner']['image']);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('nav-mega', $html);
        $this->assertStringContainsString('Fender', $html);
        $this->assertStringContainsString('banners/guitars.jpg', $html);
        $this->assertStringContainsString('Electric', $html);
    }

    public function test_brands_categories_html_title_and_separator_items_draw_as_themselves(): void
    {
        Category::create(['store_id' => 1, 'name' => 'Cables', 'slug' => 'cables', 'is_active' => true]);
        Brand::create(['store_id' => 1, 'name' => 'Boss', 'slug' => 'boss', 'is_active' => true]);
        $this->item(['label' => 'Shop', 'type' => 'categories']);
        $this->item(['label' => 'Brands', 'type' => 'brands']);
        $this->item(['label' => 'Deals', 'type' => 'html', 'config' => ['html' => '<p class="deal-note">Free shipping this week</p>']]);
        $this->item(['label' => 'More', 'type' => 'title']);
        $this->item(['label' => '', 'type' => 'separator']);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('Cables', $html);
        $this->assertStringContainsString('nav-dropdown--brands', $html);
        $this->assertStringContainsString('Boss', $html);
        $this->assertStringContainsString('deal-note', $html);
        $this->assertStringContainsString('nav-title', $html);
        $this->assertStringContainsString('nav-sep', $html);
    }

    public function test_the_tree_is_cached_and_the_theme_cache_clear_drops_it(): void
    {
        $this->item(['label' => 'Before', 'url' => '/before']);
        $this->assertSame(['Before'], array_column(NavTree::for(), 'label'));

        $this->item(['label' => 'After', 'url' => '/after']);
        $this->assertSame(['Before'], array_column(NavTree::for(), 'label'), 'Cached until cleared.');

        app(ThemeService::class)->clearCache();
        $this->assertSame(['Before', 'After'], array_column(NavTree::for(), 'label'));
    }

    public function test_the_menu_widget_places_any_composed_menu_anywhere(): void
    {
        $footer = NavMenu::create(['key' => 'footer', 'name' => 'Footer menu']);
        NavMenuItem::create(['menu_id' => $footer->id, 'label' => 'Returns policy', 'type' => 'custom', 'url' => '/returns', 'sort_order' => 1, 'is_enabled' => true]);
        LayoutPlacement::create(['theme' => 'venta', 'page_type' => 'home', 'region' => 'footer_top', 'widget' => 'nav.menu', 'title' => 'Help', 'config' => ['menu' => 'footer', 'direction' => 'vertical'], 'sort_order' => 0, 'is_enabled' => true]);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('wmenu--vertical', $html);
        $this->assertStringContainsString('Returns policy', $html);
    }
}
