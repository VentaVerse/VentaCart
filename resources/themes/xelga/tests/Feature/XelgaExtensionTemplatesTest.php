<?php

namespace Themes\Xelga\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesSampleExtension;
use Tests\TestCase;
use Themes\Xelga\Catalog\CatalogLinter;
use Themes\Xelga\Documents\Defaults;
use Themes\Xelga\Documents\Document;
use Themes\Xelga\Documents\Handles;
use Themes\Xelga\Tests\XelgaExtensionHelpers;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';
require_once __DIR__ . '/../XelgaExtensionHelpers.php';

/**
 * A page type an extension registered becomes a Xelga template the
 * merchant edits like any page, drawn from the extension's defaults until
 * then. While the extension is away the template waits, untouched.
 */
class XelgaExtensionTemplatesTest extends TestCase
{
    use RefreshDatabase;
    use UsesSampleExtension;
    use XelgaExtensionHelpers;
    use XelgaTestHelpers;

    private const HANDLE = 'sample_page';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpXelgaWithExtension();
    }

    public function test_the_page_type_is_a_template_listed_in_the_editor(): void
    {
        $this->assertSame([self::HANDLE => 'sample-extension'], Handles::extensionTemplates());
        $this->assertTrue(Handles::known(self::HANDLE));
        $this->assertSame('template', Handles::kind(self::HANDLE));
        $this->assertSame(self::HANDLE, Handles::pageType(self::HANDLE));

        $page = collect(\Themes\Xelga\Http\BootPayload::pages())->firstWhere('handle', self::HANDLE);
        $this->assertSame([
            'type' => self::HANDLE, 'handle' => self::HANDLE, 'label' => 'Sample pages', 'kind' => 'template',
            'subject_type' => null, 'subject' => null, 'url' => url('/sample-extension/things/lamp'),
            'extension' => true, 'group' => 'Sample Extension',
        ], $page);

        $this->actingAs($this->admin())->getJson(route('admin.xelga.documents.show', ['handle' => self::HANDLE]))
            ->assertOk()->assertJsonPath('data.content.sections.sample-main.type', 'sample-extension.main');
        $this->assertSame([], CatalogLinter::checkExtension('sample-extension'));
    }

    public function test_the_shipped_defaults_draw_with_the_controllers_data(): void
    {
        $this->page('/sample-extension/things/lamp')
            ->assertSee('data-sample-main', false)
            ->assertSee('<h1>Demo thing lamp</h1>', false)
            ->assertSee('About this thing')
            ->assertSee('data-page-type="sample_page"', false);
    }

    public function test_a_saved_template_draws_extension_and_theme_sections_with_the_page_data(): void
    {
        $content = Defaults::content(self::HANDLE);
        $content['order'] = ['crumbs', ...$content['order'], 'card-1'];
        $content['sections']['crumbs'] = ['type' => 'breadcrumbs', 'label' => 'Breadcrumbs', 'disabled' => false, 'settings' => [], 'block_order' => [], 'blocks' => []];
        $content['sections']['card-1'] = $this->card();
        $this->forgetXelgaState();
        $version = $this->saveDraft($this->admin(), self::HANDLE, $content, 0)->assertOk()->json('data.document.draft_version');
        $this->forgetXelgaState();
        $this->publish($this->admin(), [self::HANDLE => (int) $version])->assertOk();

        $this->page('/sample-extension/things/lamp')
            ->assertSee('data-sample-card', false)
            ->assertSee('<p data-thing>Demo thing lamp</p>', false)
            ->assertSee(url('/sample-extension/things'), false)
            ->assertSee('Things');
    }

    public function test_the_main_section_is_required_and_never_hidden(): void
    {
        $content = Defaults::content(self::HANDLE);
        $this->forgetXelgaState();

        $without = $content;
        $without['order'] = ['sample-text'];
        unset($without['sections']['sample-main']);
        $this->saveDraft($this->admin(), self::HANDLE, $without, 0)->assertStatus(422);

        $hidden = $content;
        $hidden['sections']['sample-main']['disabled'] = true;
        $this->saveDraft($this->admin(), self::HANDLE, $hidden, 0)->assertStatus(422);
    }

    public function test_while_the_extension_is_away_its_template_waits_untouched(): void
    {
        $draft = Defaults::content(self::HANDLE);
        $draft['sections']['sample-text']['settings']['heading'] = 'Waiting draft';
        (new Document(['handle' => self::HANDLE, 'kind' => 'template', 'page_type' => self::HANDLE]))
            ->forceFill(['draft' => $draft, 'draft_version' => 3, 'published' => Defaults::content(self::HANDLE), 'published_version' => 1])->save();

        $this->switchOffSampleExtension();
        $this->forgetXelgaState();

        $this->assertFalse(Handles::known(self::HANDLE));
        $this->assertNull(collect(\Themes\Xelga\Http\BootPayload::pages())->firstWhere('handle', self::HANDLE));
        $this->actingAs($this->admin())->getJson(route('admin.xelga.documents.show', ['handle' => self::HANDLE]))->assertNotFound();

        // Publishing and discarding the rest of the store leave it alone.
        $home = $this->saveDraft($this->admin(), 'home', $this->homeWithHeading('New home'), 0)->assertOk()->json('data.document.draft_version');
        $this->forgetXelgaState();
        $this->publish($this->admin(), ['home' => (int) $home])->assertOk();
        $this->forgetXelgaState();
        $this->actingAs($this->admin())->deleteJson(route('admin.xelga.documents.discard'))->assertOk();

        $row = Document::where('handle', self::HANDLE)->first();
        $this->assertSame('Waiting draft', $row->draft['sections']['sample-text']['settings']['heading']);
        $this->assertSame(3, $row->draft_version);
        $this->assertSame(1, $row->published_version);
    }

    public function test_a_page_type_the_extension_did_not_register_is_no_template(): void
    {
        \App\Support\StorefrontPageType::forget(self::HANDLE);
        $this->forgetXelgaState();

        $this->assertSame([], Handles::extensionTemplates());
        $this->assertContains(
            'sample-extension: templates/sample_page.json: sample_page is not a page type the extension registered with core (StorefrontPageType::register), or another extension ships it.',
            CatalogLinter::checkExtension('sample-extension'),
        );
    }
}
