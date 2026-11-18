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
 * The menu composer: menus as trees on a core page,
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

    public function test_the_composer_draws_the_main_menu_as_a_tree(): void
    {
        $top = $this->item(['label' => 'Guitars']);
        $this->item(['label' => 'Used', 'parent_id' => $top->id]);

        $this->get(route('admin.menus'))->assertOk()
            ->assertSee('Guitars', false)->assertSee('Used', false)->assertSee('Add an item');
    }

    public function test_the_page_lists_every_menu_and_edits_the_one_chosen(): void
    {
        $footer = NavMenu::create(['key' => 'footer', 'name' => 'Footer menu']);
        NavMenuItem::create(['menu_id' => $footer->id, 'type' => 'custom', 'label' => 'Returns policy', 'url' => '/returns', 'sort_order' => 1, 'is_enabled' => true]);
        $this->item(['label' => 'Guitars']);

        // Without a choice the page edits the main menu, and lists both.
        $this->get(route('admin.menus'))->assertOk()
            ->assertSee('Guitars')->assertDontSee('Returns policy')
            ->assertSee('href="' . route('admin.menus', ['menu' => 'footer']) . '"', false)
            ->assertSee(route('admin.menus.arrange', $this->main), false);

        $this->get(route('admin.menus', ['menu' => 'footer']))->assertOk()
            ->assertSee('Returns policy')->assertDontSee('Guitars')
            ->assertSee(route('admin.menus.arrange', $footer), false)
            ->assertSee(route('admin.menus.items.store', $footer), false)
            ->assertSee('Delete menu');

        // A key that matches no menu falls back to the main menu.
        $this->get(route('admin.menus', ['menu' => 'nope']))->assertOk()->assertSee('Guitars');
        $this->get(route('admin.menus', ['menu' => ['x']]))->assertOk()->assertSee('Guitars');
    }

    public function test_item_changes_return_to_the_menu_they_were_made_in(): void
    {
        $footer = NavMenu::create(['key' => 'footer', 'name' => 'Footer menu']);
        $back = route('admin.menus', ['menu' => 'footer']);

        $this->post(route('admin.menus.items.store', $footer), ['type' => 'custom', 'label' => 'Returns', 'url' => '/returns'])->assertRedirect($back);
        $item = NavMenuItem::where('label', 'Returns')->sole();
        $this->assertSame($footer->id, $item->menu_id);

        $this->get(route('admin.menus.items.edit', $item))->assertOk()->assertSee('href="' . $back . '"', false);
        $this->put(route('admin.menus.items.update', $item), ['type' => 'custom', 'label' => 'Returns', 'url' => '/returns', 'is_enabled' => '1'])->assertRedirect($back);
        $this->delete(route('admin.menus.items.destroy', $item))->assertRedirect($back);
    }

    public function test_a_menu_is_added_with_a_key_made_from_its_name(): void
    {
        $this->post(route('admin.menus.store'), ['name' => '  Footer   menu '])
            ->assertRedirect(route('admin.menus', ['menu' => 'footer-menu']))->assertSessionHas('success');
        $this->assertDatabaseHas('nav_menus', ['key' => 'footer-menu', 'name' => 'Footer menu']);

        // A taken key gets a number; a name with nothing to slug becomes "menu".
        NavMenu::create(['key' => 'mobile', 'name' => 'Mobile']);
        $this->post(route('admin.menus.store'), ['name' => 'Mobile!'])->assertRedirect(route('admin.menus', ['menu' => 'mobile-2']));
        $this->post(route('admin.menus.store'), ['name' => '***'])->assertRedirect(route('admin.menus', ['menu' => 'menu']));
        $this->post(route('admin.menus.store'), ['name' => '!!!'])->assertRedirect(route('admin.menus', ['menu' => 'menu-2']));

        // The key is never taken from the request.
        $this->post(route('admin.menus.store'), ['name' => 'Sidebar', 'key' => 'main']);
        $this->assertDatabaseHas('nav_menus', ['key' => 'sidebar', 'name' => 'Sidebar']);
        $this->assertSame(1, NavMenu::where('key', 'main')->count());
    }

    public function test_a_menu_name_is_required_and_unique(): void
    {
        $this->from(route('admin.menus'))->post(route('admin.menus.store'), ['name' => ''])
            ->assertRedirect(route('admin.menus'))->assertSessionHasErrors('name', null, 'addMenu');
        $this->post(route('admin.menus.store'), ['name' => 'Main menu'])->assertSessionHasErrors('name', null, 'addMenu');
        $this->post(route('admin.menus.store'), ['name' => str_repeat('a', 121)])->assertSessionHasErrors('name', null, 'addMenu');
        $this->assertSame(1, NavMenu::count());

        // The add form opens again with the error in it.
        $this->withSession(['errors' => (new \Illuminate\Support\ViewErrorBag)->put('addMenu', new \Illuminate\Support\MessageBag(['name' => 'Give the menu a name.']))])
            ->get(route('admin.menus'))->assertOk()->assertSee('Give the menu a name.');
    }

    public function test_a_menu_is_renamed_and_keeps_its_key(): void
    {
        $footer = NavMenu::create(['key' => 'footer', 'name' => 'Footer menu']);

        $this->put(route('admin.menus.update', $footer), ['name' => 'Help links'])
            ->assertRedirect(route('admin.menus', ['menu' => 'footer']))->assertSessionHas('success');
        $this->assertDatabaseHas('nav_menus', ['id' => $footer->id, 'key' => 'footer', 'name' => 'Help links']);

        // Keeping its own name is fine; taking another menu's is not.
        $this->put(route('admin.menus.update', $footer), ['name' => 'Help links'])->assertSessionHasNoErrors();
        $this->put(route('admin.menus.update', $footer), ['name' => 'Main menu'])->assertSessionHasErrors('name', null, 'renameMenu');

        $this->put(route('admin.menus.update', $this->main), ['name' => 'Header'])->assertRedirect(route('admin.menus'));
        $this->assertDatabaseHas('nav_menus', ['key' => 'main', 'name' => 'Header']);
    }

    public function test_a_menu_is_deleted_with_its_items_and_leaves_the_storefront(): void
    {
        $footer = NavMenu::create(['key' => 'footer', 'name' => 'Footer menu']);
        $top = NavMenuItem::create(['menu_id' => $footer->id, 'type' => 'custom', 'label' => 'Help', 'url' => '/help', 'sort_order' => 1, 'is_enabled' => true]);
        NavMenuItem::create(['menu_id' => $footer->id, 'parent_id' => $top->id, 'type' => 'custom', 'label' => 'Returns', 'url' => '/returns', 'sort_order' => 2, 'is_enabled' => true]);
        $kept = $this->item(['label' => 'Guitars']);
        $this->assertCount(1, NavTree::for('footer'));

        $this->delete(route('admin.menus.destroy', $footer))->assertRedirect(route('admin.menus'))->assertSessionHas('success');

        $this->assertDatabaseMissing('nav_menus', ['id' => $footer->id]);
        $this->assertSame(0, NavMenuItem::where('menu_id', $footer->id)->count());
        $this->assertTrue($kept->fresh()->exists);
        $this->assertSame([], NavTree::for('footer'));
    }

    public function test_the_main_menu_cannot_be_deleted(): void
    {
        $this->item(['label' => 'Guitars']);

        $this->delete(route('admin.menus.destroy', $this->main))->assertRedirect(route('admin.menus'))->assertSessionHas('error');
        $this->assertDatabaseHas('nav_menus', ['key' => 'main']);
        $this->assertSame(1, NavMenuItem::count());

        $this->get(route('admin.menus'))->assertOk()->assertDontSee('Delete menu')->assertSee('Rename');
    }

    /** Each menu write follows its own menus.* permission. */
    public function test_menu_writes_follow_the_menus_permissions(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $footer = NavMenu::create(['key' => 'footer', 'name' => 'Footer menu']);
        $role = \App\Models\Role::create(['name' => 'Menu viewer', 'slug' => 'menu-viewer']);
        $role->permissions()->sync(\App\Models\Permission::whereIn('slug', ['dashboard.view', 'menus.view'])->pluck('id'));
        $viewer = User::factory()->role('menu-viewer')->create();
        $this->actingAs($viewer);

        $this->get(route('admin.menus', ['menu' => 'footer']))->assertOk()
            ->assertDontSee('Add a menu')->assertDontSee('Delete menu')->assertDontSee('>Rename<', false);
        $this->post(route('admin.menus.store'), ['name' => 'Mobile'])->assertForbidden();
        $this->put(route('admin.menus.update', $footer), ['name' => 'Help'])->assertForbidden();
        $this->delete(route('admin.menus.destroy', $footer))->assertForbidden();
        $this->assertSame(['footer', 'main'], NavMenu::orderBy('key')->pluck('key')->all());
        $this->assertSame('Footer menu', $footer->fresh()->name);

        // Each permission opens only its own write.
        $role->permissions()->syncWithoutDetaching(\App\Models\Permission::whereIn('slug', ['menus.create', 'menus.edit'])->pluck('id'));
        $this->actingAs($viewer->fresh());
        $this->post(route('admin.menus.store'), ['name' => 'Mobile'])->assertRedirect();
        $this->put(route('admin.menus.update', $footer), ['name' => 'Help'])->assertRedirect();
        $this->delete(route('admin.menus.destroy', $footer))->assertForbidden();
        $this->get(route('admin.menus', ['menu' => 'footer']))->assertOk()->assertSee('Add a menu')->assertDontSee('Delete menu');

        $role->permissions()->syncWithoutDetaching(\App\Models\Permission::where('slug', 'menus.delete')->pluck('id'));
        $this->actingAs($viewer->fresh());
        $this->delete(route('admin.menus.destroy', $footer))->assertRedirect();
        $this->assertDatabaseMissing('nav_menus', ['key' => 'footer']);
    }

    public function test_every_kind_of_item_is_added_with_the_fields_it_needs(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        \Illuminate\Support\Facades\Storage::disk('public')->put('media/banners/p.jpg', 'bytes');
        $cat = Category::create(['store_id' => 1, 'name' => 'Pedals', 'slug' => 'pedals', 'is_active' => true]);
        $page = Page::create(['store_id' => 1, 'title' => 'About', 'slug' => 'about', 'body' => 'x', 'is_active' => true]);
        $add = fn (array $data) => $this->post(route('admin.menus.items.store', $this->main), $data);

        $add(['type' => 'category', 'label' => 'Pedals', 'category_id' => $cat->id, 'mega' => 'yes', 'brands' => 'yes', 'depth' => 3, 'accent' => '#ff0000', 'banner' => 'media/banners/p.jpg'])->assertRedirect()->assertSessionHas('success');
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
        $this->assertSame('media/banners/p.jpg', $pedals->config['banner']);
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

    /**
     * The storefront prints a link item in an href. A javascript: link would
     * run script for every visitor, which only custom HTML, a super admin
     * power, may do. Paths, anchors and http(s), mailto and tel addresses stay
     * accepted.
     */
    public function test_a_link_must_be_a_path_or_a_web_address(): void
    {
        $add = fn (array $data) => $this->post(route('admin.menus.items.store', $this->main), $data + ['type' => 'custom', 'label' => 'Demo']);

        foreach (['javascript:alert(1)', 'JavaScript:alert(1)', 'data:text/html,hi', '//example.com/x', 'java script:alert(1)'] as $bad) {
            $add(['url' => $bad])->assertSessionHasErrors('url');
        }
        $this->assertSame(0, NavMenuItem::count());

        foreach (['/sale', '#top', 'https://example.com/x', 'mailto:hello@example.com', 'tel:+639170000000', ' /trimmed '] as $good) {
            $add(['url' => $good])->assertSessionHasNoErrors();
        }
        $this->assertSame(6, NavMenuItem::count());
        $this->assertSame('/trimmed', NavMenuItem::orderByDesc('id')->first()->url);

        $item = NavMenuItem::first();
        $this->put(route('admin.menus.items.update', $item), ['type' => 'custom', 'label' => 'Demo', 'url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('url');
        $this->assertSame('/sale', $item->fresh()->url);

        $cat = Category::create(['store_id' => 1, 'name' => 'Demo', 'slug' => 'demo', 'is_active' => true]);
        $add(['type' => 'category', 'category_id' => $cat->id, 'banner_url' => 'javascript:alert(1)'])->assertSessionHasErrors('banner_url');
        $add(['type' => 'category', 'category_id' => $cat->id, 'banner_url' => '/sale'])->assertSessionHasNoErrors();
    }

    /**
     * The header menu is two levels deep. The tree's buttons keep it so, and
     * the item editor must too: an item with sub-items stays at the top level,
     * and an item can only go under a top-level one.
     */
    public function test_the_item_editor_keeps_the_menu_two_levels_deep(): void
    {
        $host = $this->item(['label' => 'Host']);
        $parent = $this->item(['label' => 'Parent']);
        $child = $this->item(['label' => 'Child', 'parent_id' => $parent->id]);
        $loose = $this->item(['label' => 'Loose']);

        $this->get(route('admin.menus.items.edit', $parent))->assertOk()
            ->assertSee('It has sub-items of its own, so it stays at the top level.');

        $this->put(route('admin.menus.items.update', $parent), ['type' => 'custom', 'label' => 'Parent', 'url' => '/p', 'parent_id' => $host->id])
            ->assertSessionHasNoErrors();
        $this->assertNull($parent->fresh()->parent_id, 'An item with sub-items stays at the top level.');

        $this->put(route('admin.menus.items.update', $loose), ['type' => 'custom', 'label' => 'Loose', 'url' => '/l', 'parent_id' => $child->id])
            ->assertSessionHasNoErrors();
        $this->assertNull($loose->fresh()->parent_id, 'A sub-item cannot host a third level.');

        $this->put(route('admin.menus.items.update', $loose), ['type' => 'custom', 'label' => 'Loose', 'url' => '/l', 'parent_id' => $host->id]);
        $this->assertSame($host->id, $loose->fresh()->parent_id);
    }

    /** The page has an empty state, and its styles and script come from the admin bundle. */
    public function test_an_empty_menu_says_how_to_start(): void
    {
        $this->get(route('admin.menus'))->assertOk()
            ->assertSee('No items in this menu yet')
            ->assertSee('vMenuTree', false)
            ->assertDontSee('layout-editor', false)
            ->assertDontSee('function menuTree', false);
    }

    public function test_the_composer_is_behind_admin_auth(): void
    {
        auth()->logout();
        $this->get(route('admin.menus'))->assertRedirect();
    }

    /** Menus are content: Content > Menus, at its own address, under its own trail. */
    public function test_the_menu_has_a_page_of_its_own_under_content(): void
    {
        $this->assertSame('/admin/content/menus', parse_url(route('admin.menus'), PHP_URL_PATH));
        $this->assertSame(['group' => 'Content', 'item' => 'menus'], \App\Support\AdminNav::activeItemKey('/admin/content/menus'));

        $this->get(route('admin.menus'))->assertOk()
            ->assertSee('Content')->assertSee('Menus')->assertSee($this->main->name);

        $item = $this->item(['label' => 'Guitars']);
        $this->get(route('admin.menus.items.edit', $item))->assertOk()->assertSee('href="' . route('admin.menus') . '"', false);
        $this->put(route('admin.menus.items.update', $item), ['label' => 'Guitars', 'type' => 'custom', 'url' => '/guitars', 'is_enabled' => '1'])
            ->assertRedirect(route('admin.menus'));
    }

    /** Bookmarks of the menu's old place on Header & footer still arrive. */
    public function test_the_old_menu_addresses_arrive_at_content_menus(): void
    {
        $item = $this->item(['label' => 'Guitars']);

        $this->get('/admin/storefront/header-footer/menu')->assertRedirect(route('admin.menus'))->assertStatus(301);
        $this->get('/admin/storefront/header-footer/menu/items/' . $item->id . '/edit')->assertRedirect(route('admin.menus.items.edit', $item));
    }
}
