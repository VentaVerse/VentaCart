<?php

namespace Tests\Feature\Media;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Support\Media\ColumnReference;
use App\Support\Media\MediaReferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The Media library page is the shared library component in manage mode:
 * the folder tree and the images fill the page, and the page adds rename,
 * move and delete. The tools come from the permissions the component
 * prints, so a person who may only look sees no tool they cannot use.
 */
class MediaPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Demo Store']);
        Storage::fake('public');
        Cache::flush();
        $this->withoutVite();
    }

    /** @param  list<string>  $slugs */
    private function adminWith(array $slugs): User
    {
        $role = Role::firstOrCreate(['slug' => 'media-' . md5(implode(',', $slugs))], ['name' => 'Media Tester']);
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

    public function test_the_media_page_mounts_the_library_in_manage_mode(): void
    {
        $this->actingAs($this->adminWith(['media.view', 'media.create', 'media.edit', 'media.delete']));

        $html = $this->get(route('admin.media.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<div[^>]*\bid="media-library"[^>]*\bdata-manage\b#', $html);
        $this->assertSame(
            ['view' => true, 'upload' => true, 'edit' => true, 'delete' => true],
            $this->configIn($html)['can'],
        );

        // The page is the component, not the older picker or its folder cards.
        $this->assertStringNotContainsString('data-library', $html);
        $this->assertStringNotContainsString('mediaPage()', $html);
        $this->assertStringNotContainsString('v-mediaroot', $html);
    }

    public function test_the_media_page_hides_tools_the_user_cannot_use(): void
    {
        $this->actingAs($this->adminWith(['media.view']));

        $html = $this->get(route('admin.media.index'))->assertOk()->getContent();

        $this->assertSame(
            ['view' => true, 'upload' => false, 'edit' => false, 'delete' => false],
            $this->configIn($html)['can'],
        );
        $this->assertStringContainsString('id="media-library"', $html);

        $this->actingAs($this->adminWith([]));
        $this->get(route('admin.media.index'))->assertForbidden();
    }

    public function test_the_unused_count_ignores_images_used_by_any_registered_source(): void
    {
        $disk = Storage::disk('public');
        foreach (['a', 'b', 'c', 'd'] as $name) {
            $disk->put("media/catalog/demo-{$name}.jpg", 'bytes');
        }
        $disk->put('media/catalog/demo-a_thumb.jpg', 'bytes');

        DB::table('products')->insert([
            'store_id' => 1, 'name' => 'Demo', 'slug' => 'demo-001', 'price' => 1, 'quantity' => 1,
            'image' => 'media/catalog/demo-a.jpg', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // A source registered by a package, not one of core's own columns.
        Schema::create('demo_banners', function ($table) {
            $table->id();
            $table->string('picture');
        });
        DB::table('demo_banners')->insert(['picture' => 'media/catalog/demo-b.jpg']);
        MediaReferences::register(ColumnReference::path('demo_banners', 'picture', 'banners'));

        $this->actingAs($this->adminWith(['media.view']));

        $this->get(route('admin.media.index'))
            ->assertOk()
            ->assertViewHas('unused', fn (array $unused) => $unused['count'] === 2
                && $unused['total'] === 4
                && $unused['path'] === 'media/catalog')
            ->assertSee('data-media-go="media/catalog"', false);
    }

    public function test_the_product_image_routes_are_gone(): void
    {
        foreach (['upload', 'import-url', 'browse', 'upload-to-catalog', 'delete'] as $name) {
            $this->assertFalse(Route::has('admin.products.images.' . $name), "admin.products.images.{$name} is still registered");
        }

        $this->actingAs($this->adminWith(['media.view', 'media.create', 'media.edit', 'media.delete']));

        $this->get('/admin/catalog/products/images/browse')->assertNotFound();
        $this->get('/admin/products/images/browse')->assertNotFound();

        // Only the storefront's catch-all page route matches the address now,
        // and it answers GET alone, so a POST is refused before any code runs.
        $this->assertContains($this->post('/admin/catalog/products/images/upload')->getStatusCode(), [404, 405]);
        $this->assertContains($this->post('/admin/catalog/products/images/delete')->getStatusCode(), [404, 405]);
        $this->assertFileDoesNotExist(app_path('Http/Controllers/Admin/Catalog/ProductImageController.php'));
    }
}
