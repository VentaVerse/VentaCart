<?php

namespace Tests\Feature\AdminV2;

use App\Models\Category;
use App\Models\NavMenu;
use App\Models\NavMenuItem;
use App\Models\Page;
use App\Models\User;
use App\Support\NavTree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The menu composer (Plaza plan, phase 4b): menus as trees on a core page,
 * the arrangement saved as one page, each item its own record.
 */
class MenuComposerTest extends TestCase
{
    use RefreshDatabase;

    private NavMenu $main;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->main = NavMenu::main();
    }

    private function item(array $attrs): NavMenuItem
    {
        return NavMenuItem::create($attrs + ['menu_id' => $this->main->id, 'type' => 'custom', 'url' => '/x', 'sort_order' => NavMenuItem::max('sort_order') + 1, 'is_enabled' => true]);
    }

    public function test_the_composer_lists_the_menus_and_draws_the_main_menu_as_a_tree(): void
    {
        $top = $this->item(['label' => 'Guitars']);
        $this->item(['label' => 'Used', 'parent_id' => $top->id]);
        NavMenu::create(['key' => 'footer', 'name' => 'Footer menu']);

        $this->get(route('admin.menus'))->assertOk()
            ->assertSee('Main menu')->assertSee('Footer menu')
            ->assertSee('Guitars', false)->assertSee('Used', false)->assertSee('Add an item');
    }

    public function test_a_menu_is_created_with_a_key_of_its_own_and_the_main_one_cannot_go(): void
    {
        $this->post(route('admin.menus.store'), ['name' => 'Footer links'])->assertRedirect();
        $this->post(route('admin.menus.store'), ['name' => 'Footer links'])->assertRedirect();
        $this->assertSame(['footer-links', 'footer-links-2'], NavMenu::where('key', '!=', 'main')->orderBy('id')->pluck('key')->all());

        $this->delete(route('admin.menus.destroy', $this->main))->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseHas('nav_menus', ['key' => 'main']);

        $footer = NavMenu::where('key', 'footer-links')->sole();
        $this->delete(route('admin.menus.destroy', $footer))->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseMissing('nav_menus', ['id' => $footer->id]);
    }

    public function test_every_kind_of_item_is_added_with_the_fields_it_needs(): void
    {
        $cat = Category::create(['store_id' => 1, 'name' => 'Pedals', 'slug' => 'pedals', 'is_active' => true]);
        $page = Page::create(['store_id' => 1, 'title' => 'About', 'slug' => 'about', 'body' => 'x', 'is_active' => true]);
        $add = fn (array $data) => $this->post(route('admin.menus.items.store', $this->main), $data);

        $add(['type' => 'category', 'label' => 'Pedals', 'category_id' => $cat->id, 'mega' => 'yes', 'brands' => 'yes', 'depth' => 3, 'accent' => '#ff0000', 'banner' => '/banners/p.jpg'])->assertRedirect()->assertSessionHas('success');
        $add(['type' => 'page', 'label' => 'About', 'page_id' => $page->id])->assertRedirect();
        $add(['type' => 'custom', 'label' => 'Shopee', 'url' => 'https://shopee.ph/x', 'new_tab' => 1])->assertRedirect();
        $add(['type' => 'system', 'label' => 'Cart', 'system_page' => 'cart'])->assertRedirect();
        $add(['type' => 'html', 'label' => 'Deals', 'html' => '<p>hi</p>'])->assertRedirect();
        $add(['type' => 'brands', 'label' => 'Brands'])->assertRedirect();
        $add(['type' => 'categories', 'label' => 'Shop'])->assertRedirect();
        $add(['type' => 'title', 'label' => 'More'])->assertRedirect();
        $add(['type' => 'separator'])->assertRedirect();

        $this->assertSame(9, NavMenuItem::count());
        $pedals = NavMenuItem::where('label', 'Pedals')->sole();
        $this->assertSame('yes', $pedals->config['mega']);
        $this->assertSame('#FF0000', $pedals->config['accent']);
        $this->assertSame('banners/p.jpg', $pedals->config['banner']);
        $this->assertSame(3, $pedals->config['depth']);
        $this->assertSame('cart', NavMenuItem::where('label', 'Cart')->sole()->config['page']);
        $this->assertTrue(NavMenuItem::where('label', 'Shopee')->sole()->new_tab);
        $this->assertSame('', NavMenuItem::where('type', 'separator')->sole()->label);

        // The kinds that point at something refuse an empty pointer.
        $add(['type' => 'category', 'label' => 'Nowhere'])->assertSessionHasErrors('category_id');
        $add(['type' => 'page', 'label' => 'Nowhere'])->assertSessionHasErrors('page_id');
        $add(['type' => 'custom', 'label' => 'Nowhere'])->assertSessionHasErrors('url');
        $add(['type' => 'link', 'label' => 'Bad kind'])->assertSessionHasErrors('type');
        $add(['type' => 'custom', 'label' => 'Bad accent', 'url' => '/x', 'accent' => 'red'])->assertSessionHasErrors('accent');
    }

    public function test_the_arrangement_saves_order_and_nesting_as_one_page(): void
    {
        $a = $this->item(['label' => 'A']);
        $b = $this->item(['label' => 'B']);
        $c = $this->item(['label' => 'C']);
        $other = NavMenuItem::create(['menu_id' => NavMenu::create(['key' => 'o', 'name' => 'O'])->id, 'label' => 'Foreign', 'type' => 'custom', 'url' => '/f', 'sort_order' => 1, 'is_enabled' => true]);

        $this->post(route('admin.menus.arrange', $this->main), ['payload' => json_encode(['items' => [
            ['id' => $c->id, 'parent_id' => null],
            ['id' => $a->id, 'parent_id' => null],
            ['id' => $b->id, 'parent_id' => $a->id],
            ['id' => $other->id, 'parent_id' => $a->id],
            ['id' => 999999, 'parent_id' => null],
        ]])])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(['C', 'A', 'B'], NavMenuItem::where('menu_id', $this->main->id)->orderBy('sort_order')->pluck('label')->all());
        $this->assertSame($a->id, $b->fresh()->parent_id);
        $this->assertNull($other->fresh()->parent_id, 'Another menu\'s item is never touched.');

        $labels = array_column(NavTree::for(), 'label');
        $this->assertSame(['C', 'A'], $labels);
        $this->assertSame('B', NavTree::for()[1]['children'][0]['label']);
    }

    public function test_an_item_is_edited_moved_under_another_and_deleted(): void
    {
        $host = $this->item(['label' => 'Host']);
        $item = $this->item(['label' => 'Guest']);

        $this->get(route('admin.menus.items.edit', $item))->assertOk()->assertSee('Guest')->assertSee('Top level');

        $this->put(route('admin.menus.items.update', $item), ['type' => 'custom', 'label' => 'Guest renamed', 'url' => '/g', 'parent_id' => $host->id, 'is_enabled' => 0])
            ->assertRedirect()->assertSessionHas('success');

        $item->refresh();
        $this->assertSame('Guest renamed', $item->label);
        $this->assertSame($host->id, $item->parent_id);
        $this->assertFalse($item->is_enabled);

        // An item cannot be its own parent.
        $this->put(route('admin.menus.items.update', $host), ['type' => 'custom', 'label' => 'Host', 'url' => '/h', 'parent_id' => $host->id]);
        $this->assertNull($host->fresh()->parent_id);

        $this->delete(route('admin.menus.items.destroy', $host))->assertRedirect();
        $this->assertDatabaseMissing('nav_menu_items', ['id' => $host->id]);
        $this->assertNull($item->fresh()->parent_id, 'Children of a deleted item move up, they do not vanish.');
    }

    public function test_the_composer_is_behind_admin_auth(): void
    {
        auth()->logout();
        $this->get(route('admin.menus'))->assertRedirect();
    }
}
