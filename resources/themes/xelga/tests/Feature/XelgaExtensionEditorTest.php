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

    public function test_a_stylesheet_the_build_lacks_is_logged_and_the_page_still_draws(): void
    {
        $this->withVite();
        Log::spy();

        $this->assertSame('', Extensions::current()->stylesheetTags()->toHtml());
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'not in the build'))->once();
    }
}
