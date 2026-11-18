<?php

namespace Tests\Feature\Media;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\MediaLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * <x-media-library /> is how a page offers the library: it prints the
 * script tags and the settings the library reads (addresses, limits and what
 * the signed-in person may do). Many fields on one page each include it, so
 * it prints once. A guest gets nothing, since every request would be refused.
 */
class MediaLibraryComponentTest extends TestCase
{
    use RefreshDatabase;

    /** @param  list<string>  $slugs */
    private function adminWith(array $slugs): User
    {
        $role = Role::firstOrCreate(['slug' => 'media-viewer'], ['name' => 'Media Viewer']);
        $role->permissions()->sync(collect($slugs)->map(fn (string $slug) => Permission::firstOrCreate(
            ['slug' => $slug],
            ['name' => $slug, 'group' => 'media'],
        )->id));

        return User::factory()->create(['role_id' => $role->id]);
    }

    /** @return array<string, mixed> */
    private function configIn(string $html): array
    {
        $this->assertSame(1, preg_match(
            '#<script type="application/json" id="media-library-config">(.*?)</script>#s',
            $html,
            $match,
        ));

        return json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_the_component_prints_its_config_once_with_the_users_permissions(): void
    {
        $this->withoutVite();
        $this->startSession();
        $this->actingAs($this->adminWith(['media.view']));

        $html = (string) $this->blade('<x-media-library /><div>Demo Store</div><x-media-library />');

        $this->assertSame(1, substr_count($html, 'id="media-library-config"'));

        $config = $this->configIn($html);

        $this->assertSame(
            ['browse', 'tree', 'upload', 'importUrl', 'folder', 'rename', 'move', 'delete', 'usage'],
            array_keys($config['endpoints']),
        );
        $this->assertSame(route('admin.media.browse'), $config['endpoints']['browse']);
        $this->assertSame(route('admin.media.import-url'), $config['endpoints']['importUrl']);
        $this->assertSame(route('admin.media.delete'), $config['endpoints']['delete']);
        $this->assertIsString($config['token']);
        $this->assertNotSame('', $config['token']);
        $this->assertSame(['view' => true, 'upload' => false, 'edit' => false, 'delete' => false], $config['can']);
        $this->assertSame(MediaLibrary::MAX_KB * 1024, $config['maxBytes']);
        $this->assertSame(MediaLibrary::MAX_FILES, $config['maxFiles']);
        $this->assertSame(MediaLibrary::PER_PAGE, $config['perPage']);
        $this->assertSame(['jpg', 'jpeg', 'png', 'gif', 'webp'], $config['accept']);
        $this->assertSame(['jpg', 'jpeg', 'png', 'webp'], $config['uploadAccept']);
    }

    public function test_the_component_gives_every_permission_to_a_super_admin(): void
    {
        $this->withoutVite();
        $role = Role::firstOrCreate(['slug' => Role::SUPER_ADMIN], ['name' => 'Super Admin']);
        $this->actingAs(User::factory()->create(['role_id' => $role->id]));

        $config = $this->configIn((string) $this->blade('<x-media-library />'));

        $this->assertSame(['view' => true, 'upload' => true, 'edit' => true, 'delete' => true], $config['can']);
    }

    public function test_the_component_prints_nothing_for_a_guest(): void
    {
        $this->withoutVite();

        $html = (string) $this->blade('<x-media-library />');

        $this->assertSame('', trim($html));
    }
}
