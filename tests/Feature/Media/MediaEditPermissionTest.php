<?php

namespace Tests\Feature\Media;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Renaming and moving images is its own permission, media.edit. A store that
 * already has roles gives it to every role that could add images, since
 * those are the people who file them.
 */
class MediaEditPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_11_19_100000_create_media_files_table.php');
    }

    /** @return list<string> */
    private function slugsOf(string $role): array
    {
        return Role::where('slug', $role)->firstOrFail()->permissions()->pluck('slug')->all();
    }

    public function test_media_edit_is_granted_to_roles_that_could_add_media(): void
    {
        // A store from before the permission existed.
        Permission::where('slug', 'media.edit')->delete();
        $view = Permission::firstOrCreate(['slug' => 'media.view'], ['name' => 'View Media', 'group' => 'media']);
        $create = Permission::firstOrCreate(['slug' => 'media.create'], ['name' => 'Create Media', 'group' => 'media']);

        Role::create(['slug' => 'catalog-editor', 'name' => 'Catalog Editor'])->permissions()->sync([$view->id, $create->id]);
        Role::create(['slug' => 'viewer', 'name' => 'Viewer'])->permissions()->sync([$view->id]);

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame(1, Permission::where('slug', 'media.edit')->count());
        $edit = Permission::where('slug', 'media.edit')->first();
        $this->assertSame(['Edit Media', 'media'], [$edit->name, $edit->group]);
        $this->assertContains('media.edit', $this->slugsOf('catalog-editor'));
        $this->assertSame(1, Role::where('slug', 'catalog-editor')->first()->permissions()->where('slug', 'media.edit')->count());
        $this->assertNotContains('media.edit', $this->slugsOf('viewer'));
    }

    public function test_a_fresh_store_manager_has_media_edit(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->assertContains('media.edit', $this->slugsOf('store-manager'));
        $this->assertNotContains('media.edit', $this->slugsOf('staff'));
    }
}
