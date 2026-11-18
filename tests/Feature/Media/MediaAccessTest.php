<?php

namespace Tests\Feature\Media;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Who may use the library, and how hard. Looking needs media.view, adding
 * media.create, renaming and moving media.edit, deleting media.delete. Each
 * person's requests are counted per minute, so a loop in a script or a stuck
 * browser tab cannot tie up the server.
 */
class MediaAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Demo Store']);
        Storage::fake('public');
        // The limiters count in the cache; start every test at zero.
        Cache::flush();
    }

    /** @param  list<string>  $slugs */
    private function adminWith(array $slugs): User
    {
        $role = Role::firstOrCreate(['slug' => 'media-' . md5(implode(',', $slugs))], ['name' => 'Media Tester']);
        $ids = collect($slugs)->map(fn (string $slug) => Permission::firstOrCreate(
            ['slug' => $slug],
            ['name' => $slug, 'group' => 'media'],
        )->id);
        $role->permissions()->sync($ids);

        return User::factory()->create(['role_id' => $role->id]);
    }

    /** @return array<string, array{string, string, array<string, mixed>}> route => [method, permission, payload] */
    private function routes(): array
    {
        return [
            'admin.media.browse' => ['GET', 'media.view', []],
            'admin.media.tree' => ['GET', 'media.view', []],
            'admin.media.upload' => ['POST', 'media.create', ['path' => 'media/products', 'files' => [UploadedFile::fake()->image('demo-001.jpg', 10, 10)]]],
            'admin.media.import-url' => ['POST', 'media.create', ['url' => 'not a web address']],
            'admin.media.folder' => ['POST', 'media.create', ['path' => 'media', 'name' => 'demo']],
            'admin.media.delete' => ['POST', 'media.delete', ['paths' => ['media/products/demo-001.jpg']]],
        ];
    }

    public function test_media_routes_need_their_permission(): void
    {
        $all = ['media.view', 'media.create', 'media.edit', 'media.delete'];

        foreach ($this->routes() as $name => [$method, $needed, $payload]) {
            $this->actingAs($this->adminWith(array_values(array_diff($all, [$needed]))));
            $this->json($method, route($name), $payload)->assertForbidden();

            $this->actingAs($this->adminWith([$needed]));
            $status = $this->json($method, route($name), $payload)->status();
            $this->assertNotSame(403, $status, $name . ' is allowed with ' . $needed);
        }

        // Usage, rename and move are answered by the same map.
        $permissions = app(AdminPermissions::class);
        $this->assertSame('media.view', $permissions->requirementFor('admin.media.usage', 'GET'));
        $this->assertSame('media.edit', $permissions->requirementFor('admin.media.rename', 'POST'));
        $this->assertSame('media.edit', $permissions->requirementFor('admin.media.move', 'POST'));
    }

    public function test_library_writes_are_rate_limited(): void
    {
        $this->actingAs(User::factory()->create());

        for ($i = 1; $i <= 60; $i++) {
            $this->postJson(route('admin.media.folder'), ['path' => 'media', 'name' => 'demo-' . $i])->assertOk();
        }

        $this->postJson(route('admin.media.folder'), ['path' => 'media', 'name' => 'demo-61'])->assertStatus(429);
        Storage::disk('public')->assertMissing('media/demo-61');

        // Each person has their own allowance.
        $this->actingAs(User::factory()->create());
        $this->postJson(route('admin.media.folder'), ['path' => 'media', 'name' => 'demo-61'])->assertOk();
    }

    public function test_imports_from_a_web_address_have_a_lower_limit(): void
    {
        $this->actingAs(User::factory()->create());

        for ($i = 1; $i <= 10; $i++) {
            $this->postJson(route('admin.media.import-url'), ['url' => 'not a web address'])->assertStatus(422);
        }

        $this->postJson(route('admin.media.import-url'), ['url' => 'not a web address'])->assertStatus(429);
        // Other writes still have their own allowance left.
        $this->postJson(route('admin.media.folder'), ['path' => 'media', 'name' => 'demo'])->assertOk();
    }

    public function test_library_reads_are_rate_limited(): void
    {
        $this->actingAs(User::factory()->create());

        for ($i = 1; $i <= 240; $i++) {
            $this->getJson(route('admin.media.tree'))->assertOk();
        }

        $this->getJson(route('admin.media.tree'))->assertStatus(429);
    }
}
