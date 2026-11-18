<?php

namespace Themes\Xelga\Tests\Feature;

use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Themes\Xelga\Tests\XelgaTestHelpers;
use Themes\Xelga\Documents\Document;

require_once __DIR__ . '/../XelgaTestHelpers.php';

/**
 * Every Xelga admin route needs the theme permission: guests are sent to
 * sign in, admins without it get 403 and change nothing.
 */
class XelgaAccessTest extends TestCase
{
    use RefreshDatabase;
    use XelgaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installXelga();
    }

    /** @return list<array{0: string, 1: string, 2: array}> method, url, body */
    private function routes(): array
    {
        return [
            ['GET', route('admin.xelga.editor'), []],
            ['POST', route('admin.xelga.preview.open'), []],
            ['DELETE', route('admin.xelga.preview.close'), []],
            ['GET', route('admin.xelga.documents.show', ['handle' => 'home']), []],
            ['PUT', route('admin.xelga.documents.draft', ['handle' => 'home']), ['content' => $this->homeWithHeading('Not allowed'), 'draft_version' => 0]],
            ['DELETE', route('admin.xelga.documents.discard'), []],
            ['POST', route('admin.xelga.publish'), ['versions' => ['home' => 1]]],
            ['GET', route('admin.xelga.revisions.index', ['handle' => 'home']), []],
            ['POST', route('admin.xelga.revisions.restore', ['handle' => 'home', 'revision' => 1]), ['draft_version' => 0]],
            ['POST', route('admin.xelga.presence'), []],
            ['GET', route('admin.xelga.pick', ['kind' => 'menus']), []],
            ['POST', route('admin.xelga.documents.duplicate', ['handle' => 'home']), ['section' => 'hero-1', 'draft_version' => 0]],
            ['GET', route('admin.xelga.pick', ['kind' => 'products']), []],
            ['GET', route('admin.xelga.pick', ['kind' => 'links']), ['q' => 'linen']],
            ['GET', route('admin.xelga.schemes.usage'), []],
            ['GET', route('admin.xelga.subject', ['type' => 'category', 'id' => 1]), []],
        ];
    }

    public function test_every_xelga_admin_route_is_mapped_to_the_theme_permission(): void
    {
        $permissions = app(AdminPermissions::class);
        $names = collect(Route::getRoutes()->getRoutes())->map(fn ($r) => $r->getName())->filter(fn ($n) => str_starts_with((string) $n, 'admin.xelga.'));

        $this->assertGreaterThanOrEqual(10, $names->count());
        foreach ($names as $name) {
            $this->assertSame('themes.edit', $permissions->requirementFor($name, 'GET'), $name);
            $this->assertSame('themes.edit', $permissions->requirementFor($name, 'POST'), $name);
        }
    }

    public function test_an_admin_without_the_theme_permission_gets_403_everywhere(): void
    {
        $staff = $this->staff();

        foreach ($this->routes() as [$method, $url, $body]) {
            $this->actingAs($staff)->json($method, $url, $body)->assertForbidden();
        }
        $this->actingAs($staff)->get(route('admin.xelga.editor'))->assertForbidden();

        $this->assertSame(0, Document::count());
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        foreach ($this->routes() as [$method, $url, $body]) {
            $this->json($method, $url, $body)->assertUnauthorized();
        }
        $this->get(route('admin.xelga.editor'))->assertRedirect();
    }

    public function test_a_member_of_a_group_with_the_theme_permission_can_edit(): void
    {
        $maria = $this->themeEditor();

        $this->actingAs($maria)->get(route('admin.xelga.editor'))->assertOk();
        $this->saveDraft($maria, 'home', $this->homeWithHeading('Maria edits'), 0)->assertOk();
        $this->publish($maria, ['home' => 1])->assertOk();
    }

    public function test_the_editor_shell_carries_the_boot_payload(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.xelga.editor'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('id="xelga-boot"', false);

        preg_match('#<script type="application/json" id="xelga-boot">(.*?)</script>#s', $response->getContent(), $m);
        $boot = json_decode(html_entity_decode($m[1] ?? '', ENT_QUOTES), true) ?? json_decode($m[1] ?? '', true);

        $this->assertSame(array_keys(\Themes\Xelga\Catalog\Catalog::sections()), array_keys($boot['catalog']['sections']));
        $this->assertArrayHasKey('hero', $boot['catalog']['sections']);
        $this->assertSame('Main banner', $boot['catalog']['sections']['hero']['label']);
        $this->assertSame(['settings', 'header', 'footer', 'home'], array_keys($boot['documents']));
        $this->assertSame('Juan Dela Cruz', $boot['user']['name']);
        $this->assertNotEmpty($boot['csrf']);
        // The shell opens no preview window; the app asks for one by POST.
        $this->assertNull($this->previewWindow());
        $this->assertSame('', $boot['preview']['nonce']);
        $this->assertStringNotContainsString('xelga_preview=', $boot['preview']['url']);
        $this->assertStringEndsWith('/preview', $boot['routes']['preview_open']);
        $this->assertStringContainsString('/documents/__HANDLE__/draft', $boot['routes']['documents_draft']);
        $this->assertSame('main', $boot['options']['menus'][0]['value']);
        // The blog posts section's category picker reads this list; empty while the blog is not installed.
        $this->assertArrayHasKey('blog_categories', $boot['options']);
        $this->assertIsArray($boot['options']['blog_categories']);
    }

    public function test_the_menu_picker_lists_the_stores_menus(): void
    {
        $this->actingAs($this->admin())->getJson(route('admin.xelga.pick', ['kind' => 'menus']))
            ->assertOk()
            ->assertJsonPath('data.results.0.value', 'main')
            ->assertJsonPath('data.results.0.label', 'Main menu');

        $this->actingAs($this->admin())->getJson(route('admin.xelga.pick', ['kind' => 'customers']))->assertNotFound();
    }

    public function test_presence_names_the_other_editor(): void
    {
        $this->openEditor($this->themeEditor());
        $this->actingAs($this->themeEditor('Maria Santos'))->postJson(route('admin.xelga.presence'), ['handle' => 'home'])->assertOk();

        $this->actingAs($this->admin())->postJson(route('admin.xelga.presence'), ['handle' => 'home'])
            ->assertOk()
            ->assertJsonPath('data.others.0.name', 'Maria Santos');
    }
}
