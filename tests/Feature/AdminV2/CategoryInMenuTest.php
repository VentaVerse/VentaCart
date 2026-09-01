<?php

namespace Tests\Feature\AdminV2;

use App\Models\Category;
use App\Models\NavMenu;
use App\Models\NavMenuItem;
use App\Models\Store;
use App\Models\User;
use App\Support\MainMenu;
use App\Support\NavTree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Putting a department in the menu from the category form.
 *
 * The composer could already build any menu and could not be found: its only
 * entrance was a button on the theme page, and the trail out of it led
 * somewhere that does not link back. This is the fast path for the one thing
 * nearly every shop wants, said where the category already is.
 */
class CategoryInMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        NavMenu::firstOrCreate(['key' => NavMenu::MAIN], ['name' => 'Main menu']);
        $this->actingAs(User::factory()->create());
    }

    private function category(string $name, ?int $parent = null, int $sort = 0): Category
    {
        return Category::create([
            'store_id' => 1, 'parent_id' => $parent, 'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name), 'sort_order' => $sort, 'is_active' => true,
        ]);
    }

    public function test_saving_a_category_with_the_tick_puts_it_in_the_menu(): void
    {
        $category = $this->category('Phones');
        $this->assertFalse(MainMenu::holds($category));

        $this->put(route('admin.categories.update', $category), [
            'store_id' => 1, 'name' => 'Phones', 'is_active' => 1, 'in_menu' => 1,
        ])->assertRedirect();

        $this->assertTrue(MainMenu::holds($category->fresh()));

        $item = NavMenuItem::where('category_id', $category->id)->first();
        $this->assertSame('category', $item->type);
        $this->assertSame('Phones', $item->label);
        $this->assertTrue((bool) $item->is_enabled);
    }

    public function test_clearing_the_tick_takes_it_out_again(): void
    {
        $category = $this->category('Phones');
        MainMenu::set($category, true);
        $this->assertTrue(MainMenu::holds($category));

        $this->put(route('admin.categories.update', $category), [
            'store_id' => 1, 'name' => 'Phones', 'is_active' => 1, 'in_menu' => 0,
        ])->assertRedirect();

        $this->assertFalse(MainMenu::holds($category->fresh()));
        $this->assertSame(0, NavMenuItem::where('category_id', $category->id)->count());
    }

    /**
     * The composer's arrangement is the menu's order. A category saved for an
     * unrelated reason must not quietly shuffle the menu bar underneath it.
     */
    public function test_saving_again_does_not_move_an_item_the_merchant_arranged(): void
    {
        $category = $this->category('Phones', null, 0);
        MainMenu::set($category, true);

        NavMenuItem::where('category_id', $category->id)->update(['sort_order' => 7]);

        $this->put(route('admin.categories.update', $category), [
            'store_id' => 1, 'name' => 'Phones', 'is_active' => 1, 'in_menu' => 1, 'sort_order' => 0,
        ])->assertRedirect();

        $this->assertSame(7, (int) NavMenuItem::where('category_id', $category->id)->value('sort_order'));
    }

    /** A category in the menu opens onto its own children, with nobody arranging it. */
    public function test_a_category_in_the_menu_brings_its_subcategories(): void
    {
        $computers = $this->category('Computers');
        $this->category('Laptops', $computers->id, 0);
        $this->category('Monitors', $computers->id, 1);

        MainMenu::set($computers, true);
        NavTree::forget();

        $node = collect(NavTree::for(NavMenu::MAIN))->firstWhere('label', 'Computers');

        $this->assertNotNull($node);
        $this->assertSame('list', $node['dropdown']['kind']);
        $this->assertSame(['Laptops', 'Monitors'], array_column($node['dropdown']['columns'], 'name'));
    }

    /**
     * A department with nothing under it opens onto nothing.
     *
     * The tree used to build a dropdown for every category in the menu
     * regardless, so a top level category with no children wore a chevron and
     * dropped an empty box under itself.
     */
    public function test_a_category_with_no_children_has_no_dropdown(): void
    {
        $lonely = $this->category('Phones');
        $parent = $this->category('Computers');
        $this->category('Laptops', $parent->id);

        MainMenu::set($lonely, true);
        MainMenu::set($parent, true);
        NavTree::forget();

        $tree = collect(NavTree::for(NavMenu::MAIN));

        $this->assertNull($tree->firstWhere('label', 'Phones')['dropdown'],
            'A category with no subcategories must not offer a dropdown.');
        $this->assertNotNull($tree->firstWhere('label', 'Computers')['dropdown'],
            'One that has children still does.');
    }

    /** One door. The theme page must not offer a second way in. */
    public function test_the_theme_page_no_longer_links_to_the_composer(): void
    {
        $view = file_get_contents(base_path('extensions/theme/resources/views/index.blade.php'));

        $this->assertStringNotContainsString("route('admin.menus')", $view,
            'Menus live under Content. A second entrance from the theme page is the teleport we removed.');
        $this->assertStringNotContainsString("tab === 'navigation'", $view);
    }

    /** And the one door is actually in the sidebar. */
    public function test_menus_has_an_entry_in_the_admin_nav(): void
    {
        $content = collect(config('admin-nav'))->firstWhere('segment', 'content');

        $this->assertNotNull($content);
        $this->assertContains('menus', array_column($content['items'], 'key'),
            'The composer had no entry at all, which is why it could only be reached by teleport.');
    }
}
