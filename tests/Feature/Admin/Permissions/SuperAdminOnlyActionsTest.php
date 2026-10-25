<?php

namespace Tests\Feature\Admin\Permissions;

use App\Models\LayoutPlacement;
use App\Models\NavMenu;
use App\Models\NavMenuItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\AdminPermissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Some actions stay with super admins however much a role is granted:
 * managing users, installing code, revealing secrets, and writing HTML or
 * script the storefront prints.
 */
class SuperAdminOnlyActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    /** A role holding every permission there is, short of being Super Admin. */
    private function everythingButSuperAdmin(): User
    {
        $role = Role::create(['name' => 'Everything', 'slug' => 'everything']);
        $role->permissions()->sync(Permission::pluck('id'));

        return User::factory()->role('everything')->create();
    }

    public function test_every_permission_still_does_not_reach_the_reserved_writes(): void
    {
        $this->actingAs($this->everythingButSuperAdmin());

        $this->post(route('admin.users.store'), [])->assertForbidden();
        $this->post(route('admin.user-groups.store'), ['name' => 'Demo'])->assertForbidden();
        $this->post(route('admin.extensions.install', 'banners'))->assertForbidden();
        $this->post(route('admin.extensions.uninstall', 'blog'))->assertForbidden();
        $this->post(route('admin.settings.reveal-secret'), [])->assertForbidden();
        $this->post(route('admin.layout.templates.import'), [])->assertForbidden();
        $this->assertSame('not_installed', app(\App\Services\ExtensionManager::class)->state('banners'));

        // Reading still follows the role.
        $this->get(route('admin.users.index'))->assertOk();
        $this->get(route('admin.extensions.index'))->assertOk();
    }

    public function test_storefront_code_is_a_super_admin_gate(): void
    {
        $owner = User::factory()->create();
        $manager = $this->everythingButSuperAdmin();

        $this->assertTrue(Gate::forUser($owner)->allows(AdminPermissions::STOREFRONT_CODE));
        $this->assertFalse(Gate::forUser($manager)->allows(AdminPermissions::STOREFRONT_CODE));

        // Permission slugs answer as abilities too.
        $staff = User::factory()->role('staff')->create();
        $this->assertTrue(Gate::forUser($staff)->allows('orders.edit'));
        $this->assertFalse(Gate::forUser($staff)->allows('settings.edit'));
    }

    public function test_only_a_super_admin_writes_a_custom_html_menu_item(): void
    {
        $menu = NavMenu::main();
        $item = ['type' => 'html', 'label' => 'Demo', 'html' => '<script>alert(1)</script>'];

        $this->actingAs($this->everythingButSuperAdmin());
        $this->post(route('admin.menus.items.store', $menu), $item)->assertForbidden();
        $this->assertSame(0, NavMenuItem::count());

        $this->actingAs(User::factory()->create());
        $this->post(route('admin.menus.items.store', $menu), $item)->assertRedirect();
        $this->assertSame(1, NavMenuItem::count());
    }

    public function test_a_custom_html_menu_item_can_be_resaved_unchanged_by_anyone_with_menus(): void
    {
        $item = NavMenuItem::create([
            'menu_id' => NavMenu::main()->id, 'type' => 'html', 'label' => 'Demo',
            'config' => ['html' => '<p>Demo</p>'], 'sort_order' => 1, 'is_enabled' => true,
        ]);

        $this->actingAs($this->everythingButSuperAdmin());

        $this->put(route('admin.menus.items.update', $item), [
            'type' => 'html', 'label' => 'Renamed', 'html' => '<p>Demo</p>', 'is_enabled' => true,
        ])->assertRedirect();
        $this->assertSame('Renamed', $item->fresh()->label);

        $this->put(route('admin.menus.items.update', $item), [
            'type' => 'html', 'label' => 'Renamed', 'html' => '<p>Changed</p>', 'is_enabled' => true,
        ])->assertForbidden();
        $this->assertSame('<p>Demo</p>', $item->fresh()->config['html']);
    }

    public function test_only_a_super_admin_writes_an_html_block(): void
    {
        $payload = fn (?int $id, string $html) => ['payload' => json_encode([
            'page_type' => 'home', 'columns' => ['left' => 0, 'right' => 0],
            'rows' => [['id' => null, 'region' => 'content_top', 'columns' => [12], 'enabled' => true,
                'placements' => [['id' => $id, 'column' => 0, 'widget' => 'content.html', 'title' => 'Demo',
                    'config' => ['html' => $html], 'enabled' => true]]]],
        ])];

        $this->actingAs($this->everythingButSuperAdmin());
        $this->post(route('admin.layout.update'), $payload(null, '<script>alert(1)</script>'))->assertForbidden();
        $this->assertSame(0, LayoutPlacement::count());

        $this->actingAs(User::factory()->create());
        $this->post(route('admin.layout.update'), $payload(null, '<p>Demo</p>'))->assertRedirect();
        $placement = LayoutPlacement::firstOrFail();

        // Moving or re-saving it with the same markup is fine for anyone
        // who may edit the layout; changing the markup is not.
        $this->actingAs($this->everythingButSuperAdminAgain());
        $this->post(route('admin.layout.update'), $payload($placement->id, '<p>Demo</p>'))->assertRedirect();
        $this->post(route('admin.layout.update'), $payload($placement->id, '<p>Changed</p>'))->assertForbidden();
        $this->assertSame('<p>Demo</p>', $placement->fresh()->config['html']);
    }

    private function everythingButSuperAdminAgain(): User
    {
        return User::factory()->role('everything')->create();
    }
}
