<?php

namespace Tests\Feature\Admin\Permissions;

use App\Models\Category;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\ExtensionManager;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What each shipped role can open, enforced at the route.
 */
class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([StoreSeeder::class, RolePermissionSeeder::class]);

        $manager = app(ExtensionManager::class);
        if ($manager->state('theme') === 'not_installed') {
            $manager->install('theme');
        }
        $manager->bootEnabled();
    }

    private function as(string $role): User
    {
        $user = User::factory()->role($role)->create();
        $this->actingAs($user);

        return $user;
    }

    public function test_staff_can_work_orders(): void
    {
        $this->as('staff');

        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.orders.index'))->assertOk();
        $this->get(route('admin.orders.create'))->assertOk();
        $this->get(route('admin.products.index'))->assertOk();
        $this->get(route('admin.customers.index'))->assertOk();
    }

    public function test_staff_is_refused_settings_themes_users_and_catalog_writes(): void
    {
        $this->as('staff');

        $this->get(route('admin.settings.index'))->assertForbidden();
        $this->get(route('admin.settings.section', 'general'))->assertForbidden();
        $this->post(route('admin.settings.update'), [])->assertForbidden();
        $this->get(route('admin.theme.index'))->assertForbidden();
        $this->get(route('admin.layout'))->assertForbidden();
        $this->get(route('admin.users.index'))->assertForbidden();
        $this->get(route('admin.user-groups.index'))->assertForbidden();
        $this->get(route('admin.extensions.index'))->assertForbidden();
        $this->get(route('admin.categories.index'))->assertForbidden();
        $this->post(route('admin.categories.store'), ['name' => 'Demo'])->assertForbidden();
        $this->assertSame(0, Category::count());
    }

    public function test_store_manager_can_manage_the_catalog(): void
    {
        $this->as('store-manager');

        $this->get(route('admin.products.index'))->assertOk();
        $this->get(route('admin.categories.index'))->assertOk();
        $this->get(route('admin.brands.index'))->assertOk();
        $this->get(route('admin.attributes.index'))->assertOk();

        $this->post(route('admin.categories.store'), [
            'store_id' => Store::query()->value('id'),
            'name' => 'Demo Category',
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('categories', ['name' => 'Demo Category']);
    }

    public function test_store_manager_is_refused_settings_users_extensions_and_themes(): void
    {
        $this->as('store-manager');

        $this->get(route('admin.settings.index'))->assertForbidden();
        $this->get(route('admin.users.index'))->assertForbidden();
        $this->get(route('admin.user-groups.index'))->assertForbidden();
        $this->get(route('admin.extensions.index'))->assertForbidden();
        $this->get(route('admin.theme.index'))->assertForbidden();
        $this->get(route('admin.layout'))->assertForbidden();
    }

    public function test_super_admin_can_open_everything(): void
    {
        $this->as(Role::SUPER_ADMIN);

        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.settings.index'))->assertOk();
        $this->get(route('admin.users.index'))->assertOk();
        $this->get(route('admin.user-groups.index'))->assertOk();
        $this->get(route('admin.extensions.index'))->assertOk();
        $this->get(route('admin.theme.index'))->assertOk();
        $this->get(route('admin.categories.index'))->assertOk();
    }

    public function test_a_super_admin_passes_a_route_nothing_maps(): void
    {
        \Illuminate\Support\Facades\Route::middleware(['web', 'auth'])
            ->get('admin/unmapped-demo', fn () => 'ok')->name('admin.unmapped-demo');
        app('router')->getRoutes()->refreshNameLookups();

        $this->as(Role::SUPER_ADMIN);
        $this->get('/admin/unmapped-demo')->assertOk();

        $this->as('store-manager');
        $this->get('/admin/unmapped-demo')->assertForbidden();
    }

    public function test_a_user_with_no_role_reaches_only_what_every_admin_can(): void
    {
        $this->actingAs(User::factory()->withoutRole()->create());

        $this->get(route('admin.profile.edit'))->assertOk();
        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->get(route('admin.orders.index'))->assertForbidden();
    }

    public function test_a_role_granted_users_view_can_read_users_but_never_change_them(): void
    {
        $role = Role::create(['name' => 'Auditor', 'slug' => 'auditor']);
        $role->permissions()->sync(\App\Models\Permission::whereIn('slug', ['users.view', 'users.create', 'users.edit', 'users.delete'])->pluck('id'));
        $this->actingAs(User::factory()->role('auditor')->create());

        $this->get(route('admin.users.index'))->assertOk();
        $this->post(route('admin.users.store'), [
            'name' => 'Maria Santos', 'username' => 'maria', 'email' => 'maria@example.com',
            'password' => 'password123', 'role_id' => Role::where('slug', Role::SUPER_ADMIN)->value('id'),
        ])->assertForbidden();
        $this->post(route('admin.user-groups.store'), ['name' => 'Everything'])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'maria@example.com']);
    }

    public function test_a_refusal_answers_json_to_a_json_request(): void
    {
        $this->as('staff');

        $this->getJson(route('admin.settings.index'))
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'You do not have permission to do this. Ask a super admin for access.',
                'data' => null,
            ]);
    }

    public function test_a_refusal_renders_the_error_page_otherwise(): void
    {
        $this->as('staff');

        $this->get(route('admin.settings.index'))
            ->assertForbidden()
            ->assertSee('ACCESS DENIED');
    }

    public function test_a_guest_is_still_sent_to_sign_in(): void
    {
        $this->get(route('admin.settings.index'))->assertRedirect(route('login'));
    }

    public function test_the_shipped_super_admin_holds_every_permission(): void
    {
        $role = Role::where('slug', Role::SUPER_ADMIN)->firstOrFail();

        $this->assertSame(
            \App\Models\Permission::count(),
            $role->permissions()->count()
        );
    }
}
