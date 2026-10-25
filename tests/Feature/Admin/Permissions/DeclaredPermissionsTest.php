<?php

namespace Tests\Feature\Admin\Permissions;

use App\Models\User;
use App\Services\ExtensionManager;
use App\Support\AdminPermissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * How an extension or theme says what its own admin routes need, and the
 * limits on what such a declaration can change.
 */
class DeclaredPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Route::middleware(['web', 'auth'])->prefix('admin/demo-module')->name('admin.demo-module.')->group(function () {
            Route::get('/', fn () => 'list')->name('index');
            Route::post('/', fn () => 'saved')->name('store');
        });
        app('router')->getRoutes()->refreshNameLookups();
    }

    protected function tearDown(): void
    {
        AdminPermissions::flushRegistered();

        parent::tearDown();
    }

    public function test_an_unmapped_extension_route_is_super_admin_only(): void
    {
        $this->actingAs(User::factory()->role('store-manager')->create());
        $this->get('/admin/demo-module')->assertForbidden();

        $this->actingAs(User::factory()->create());
        $this->get('/admin/demo-module')->assertOk();
    }

    public function test_a_provider_maps_its_routes_to_a_permission_group(): void
    {
        AdminPermissions::map(['admin.demo-module.*' => 'marketing']);

        $this->actingAs(User::factory()->role('store-manager')->create());
        $this->get('/admin/demo-module')->assertOk();
        $this->post('/admin/demo-module')->assertOk();

        $this->actingAs(User::factory()->role('staff')->create());
        $this->get('/admin/demo-module')->assertForbidden();
    }

    public function test_a_manifest_maps_its_routes_while_the_extension_is_enabled(): void
    {
        $this->app->instance(ExtensionManager::class, new class extends ExtensionManager
        {
            public function discovered(): Collection
            {
                return collect(['demo-module' => [
                    'name' => 'demo-module',
                    'admin_permissions' => ['admin.demo-module.*' => 'orders'],
                ]]);
            }

            public function state(string $name): string
            {
                return 'enabled';
            }

            public function bootEnabled(): void
            {
            }
        });

        $this->actingAs(User::factory()->role('staff')->create());
        $this->get('/admin/demo-module')->assertOk();
    }

    public function test_a_declaration_cannot_loosen_core_routes(): void
    {
        AdminPermissions::map([
            'admin.settings.*' => '@admin',
            'admin.users.store' => '@admin',
        ]);

        $this->actingAs(User::factory()->role('staff')->create());
        $this->get(route('admin.settings.index'))->assertForbidden();
        $this->post(route('admin.users.store'), [])->assertForbidden();
    }

    public function test_theme_editors_need_themes_edit(): void
    {
        $permissions = app(AdminPermissions::class);

        $this->assertSame('themes.edit', $permissions->requirementFor('admin.plaza.panel', 'GET'));
        $this->assertSame('themes.edit', $permissions->requirementFor('admin.plaza.editor.save', 'POST'));
        $this->assertSame('themes.edit', $permissions->requirementFor('admin.xelga.panel', 'GET'));
    }

    public function test_the_action_follows_the_method_and_the_route_name(): void
    {
        $permissions = app(AdminPermissions::class);

        $this->assertSame('products.view', $permissions->requirementFor('admin.products.index', 'GET'));
        $this->assertSame('products.create', $permissions->requirementFor('admin.products.store', 'POST'));
        $this->assertSame('products.edit', $permissions->requirementFor('admin.products.update', 'PUT'));
        $this->assertSame('products.delete', $permissions->requirementFor('admin.products.destroy', 'DELETE'));
        $this->assertSame('categories.edit', $permissions->requirementFor('admin.categories.reorder', 'POST'));
        // Settings has no create or delete: any write is an edit.
        $this->assertSame('settings.edit', $permissions->requirementFor('admin.settings.identity.destroy', 'DELETE'));
        $this->assertSame(AdminPermissions::SUPER_ADMIN, $permissions->requirementFor('admin.users.update', 'PUT'));
        $this->assertSame('users.view', $permissions->requirementFor('admin.users.index', 'GET'));
        $this->assertNull($permissions->requirementFor('admin.nothing-maps-this', 'GET'));
    }
}
