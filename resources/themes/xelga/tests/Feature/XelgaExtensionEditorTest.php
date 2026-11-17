<?php

namespace Themes\Xelga\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Support\UsesSampleExtension;
use Tests\TestCase;
use Themes\Xelga\Extensions\Extensions;
use Themes\Xelga\Tests\XelgaExtensionHelpers;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';
require_once __DIR__ . '/../XelgaExtensionHelpers.php';

/**
 * What the editor learns about extensions when it opens: their sections and
 * blocks in the catalog, and every extension on disk with its state, so a
 * section or block whose extension is away can name it.
 */
class XelgaExtensionEditorTest extends TestCase
{
    use RefreshDatabase;
    use UsesSampleExtension;
    use XelgaExtensionHelpers;
    use XelgaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpXelgaWithExtension();
    }

    private function boot(): array
    {
        $this->forgetXelgaState();
        $html = $this->actingAs($this->admin())->get(route('admin.xelga.editor'))->assertOk()->getContent();
        preg_match('/<script type="application\/json" id="xelga-boot">(.*?)<\/script>/s', $html, $m);
        $this->assertNotEmpty($m, 'The editor has no boot data.');

        return json_decode(html_entity_decode($m[1]), true);
    }

    public function test_the_editor_gets_the_extension_sections_blocks_and_states(): void
    {
        $boot = $this->boot();

        $this->assertSame('Sample Extension', $boot['catalog']['sections']['sample-extension.card']['source_label']);
        $blockTypes = array_column($boot['catalog']['sections']['product-main']['blocks']['types'], 'source', 'type');
        $this->assertSame('sample-extension', $blockTypes['sample-extension.seller']);
        $this->assertSame(['title' => 'Sample Extension', 'state' => 'on'], $boot['extensions']['sample-extension']);

        $this->switchOffSampleExtension();
        $boot = $this->boot();
        $this->assertArrayNotHasKey('sample-extension.card', $boot['catalog']['sections']);
        $this->assertSame('off', $boot['extensions']['sample-extension']['state']);
    }

    public function test_the_editor_learns_only_of_installed_extensions(): void
    {
        $boot = $this->boot();
        $this->assertArrayNotHasKey('blog', $boot['extensions'], 'An extension on disk but never installed is not listed.');

        app(\App\Services\ExtensionManager::class)->install('blog');
        app(\App\Services\ExtensionManager::class)->disable('blog');
        $this->assertSame('off', $this->boot()['extensions']['blog']['state'] ?? null, 'An installed one is, switched on or off.');

        $this->removeSampleExtension();
        $this->assertArrayNotHasKey('sample-extension', $this->boot()['extensions']);
    }

    public function test_a_stylesheet_no_build_has_is_served_as_it_ships(): void
    {
        $this->withVite();
        $this->forgetXelgaState();
        $file = base_path('tests/fixtures/extensions/sample-extension/resources/css/xelga.css');
        $hash = substr(sha1_file($file), 0, 12);
        $href = "/xelga/extensions/sample-extension/{$hash}.css";

        $this->assertSame('<link rel="stylesheet" href="' . $href . '">', Extensions::current()->stylesheetTags()->toHtml());

        $this->get($href)
            ->assertOk()
            ->assertHeader('Content-Type', 'text/css; charset=utf-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public')
            ->assertContent(file_get_contents($file));
        $this->get('/xelga/extensions/sample-extension/000000000000.css')->assertRedirect($href);
        $this->get('/xelga/extensions/blog/000000000000.css')->assertNotFound();

        $this->switchOffSampleExtension();
        $this->forgetXelgaState();
        $this->get($href)->assertNotFound();
    }

    public function test_a_stylesheet_no_build_has_is_noted_once_per_catalog_build_not_per_request(): void
    {
        $this->withVite();
        $this->forgetXelgaState();
        Log::spy();

        foreach ([1, 2, 3] as $request) {
            // A new request in a new process: nothing remembered but the compiled catalog file.
            \Themes\Xelga\Rendering\Storefront::forgetRequestState();
            \Themes\Xelga\Catalog\Catalog::flush();
            \Themes\Xelga\Extensions\Registrar::forgetLogged();
            $this->get('/')->assertOk();
        }

        Log::shouldHaveReceived('notice')->withArgs(fn ($message) => str_contains($message, 'as it ships'))->once();
        Log::shouldNotHaveReceived('warning', [\Mockery::on(fn ($message) => str_contains((string) $message, 'stylesheet'))]);
    }
}
