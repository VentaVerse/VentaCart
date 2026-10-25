<?php

namespace Tests\Feature\Admin\Permissions;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\AdminPermissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The migration that grows the permission catalog, run against a store that
 * has the original set, custom roles and users with no role.
 */
class ExtendAdminPermissionsMigrationTest extends TestCase
{
    use RefreshDatabase;

    /** The set the store had before routes checked permissions. */
    private const ORIGINAL = [
        'dashboard' => ['view'],
        'products' => ['view', 'create', 'edit', 'delete'],
        'categories' => ['view', 'create', 'edit', 'delete'],
        'orders' => ['view', 'create', 'edit', 'delete'],
        'customers' => ['view', 'create', 'edit', 'delete'],
        'reports' => ['view'],
        'settings' => ['view', 'edit'],
        'users' => ['view', 'create', 'edit', 'delete'],
    ];

    private function migrate(): void
    {
        (require database_path('migrations/2026_10_25_230000_extend_admin_permissions.php'))->up();
    }

    /** A store as the original seeder left it. */
    private function originalStore(): void
    {
        Permission::query()->delete();
        Role::query()->delete();

        foreach (self::ORIGINAL as $group => $actions) {
            foreach ($actions as $action) {
                Permission::create(['slug' => "{$group}.{$action}", 'name' => ucfirst($action) . ' ' . ucfirst($group), 'group' => $group]);
            }
        }

        $grant = fn (string $slug, string $name, callable $filter) => Role::create(['slug' => $slug, 'name' => $name])
            ->permissions()->sync(Permission::all()->filter($filter)->pluck('id'));

        $grant('super-admin', 'Super Admin', fn () => true);
        $grant('store-manager', 'Store Manager', fn ($p) => ! in_array($p->group, ['users', 'settings'], true));
        $grant('staff', 'Staff', fn ($p) => in_array($p->slug, ['dashboard.view', 'products.view', 'customers.view'], true)
            || $p->group === 'orders');
        $grant('catalog-editor', 'Catalog Editor', fn ($p) => in_array($p->slug, ['products.view', 'products.edit'], true));
    }

    /** @return list<string> */
    private function slugsOf(string $role): array
    {
        return Role::where('slug', $role)->firstOrFail()->permissions()->pluck('slug')->sort()->values()->all();
    }

    public function test_it_adds_the_whole_catalog_and_shipped_role_defaults(): void
    {
        $this->originalStore();
        $this->migrate();

        $this->assertEqualsCanonicalizing(AdminPermissions::slugs(), Permission::pluck('slug')->all());
        $this->assertSame(Permission::count(), Role::where('slug', 'super-admin')->first()->permissions()->count());

        $manager = $this->slugsOf('store-manager');
        $this->assertEqualsCanonicalizing(AdminPermissions::defaultsFor('store-manager'), $manager);

        $this->assertEqualsCanonicalizing(AdminPermissions::defaultsFor('staff'), $this->slugsOf('staff'));
    }

    public function test_a_custom_role_keeps_what_its_old_permissions_covered(): void
    {
        $this->originalStore();
        $this->migrate();

        $editor = $this->slugsOf('catalog-editor');

        foreach (['brands.view', 'brands.edit', 'tags.edit', 'attributes.edit', 'media.view', 'reviews.edit'] as $slug) {
            $this->assertContains($slug, $editor);
        }
        foreach (['brands.delete', 'marketing.view', 'themes.edit', 'extensions.edit'] as $slug) {
            $this->assertNotContains($slug, $editor);
        }
    }

    public function test_users_with_no_role_keep_full_access_as_super_admins(): void
    {
        $this->originalStore();
        $owner = User::factory()->withoutRole()->create();
        $staff = User::factory()->role('staff')->create();

        $this->migrate();

        $this->assertTrue($owner->fresh()->isSuperAdmin());
        $this->assertFalse($staff->fresh()->isSuperAdmin());
    }

    public function test_it_creates_super_admin_when_a_roleless_user_has_nowhere_to_go(): void
    {
        Permission::query()->delete();
        Role::query()->delete();
        $owner = User::factory()->withoutRole()->create();

        $this->migrate();

        $this->assertTrue($owner->fresh()->isSuperAdmin());
    }

    public function test_running_twice_changes_nothing_and_respects_removed_grants(): void
    {
        $this->originalStore();
        $this->migrate();

        $manager = Role::where('slug', 'store-manager')->firstOrFail();
        $manager->permissions()->detach(Permission::where('slug', 'brands.delete')->value('id'));
        $count = Permission::count();

        $this->migrate();
        $this->seed(RolePermissionSeeder::class);

        $this->assertSame($count, Permission::count());
        $this->assertNotContains('brands.delete', $this->slugsOf('store-manager'));
    }

    public function test_on_a_fresh_install_the_seeder_grants_the_shipped_roles(): void
    {
        // RefreshDatabase already ran the migration on an empty database.
        $this->assertSame(0, Role::count());

        $this->seed(RolePermissionSeeder::class);

        $this->assertEqualsCanonicalizing(AdminPermissions::slugs(), Permission::pluck('slug')->all());
        $this->assertEqualsCanonicalizing(AdminPermissions::defaultsFor('store-manager'), $this->slugsOf('store-manager'));
        $this->assertEqualsCanonicalizing(AdminPermissions::defaultsFor('staff'), $this->slugsOf('staff'));
    }
}
