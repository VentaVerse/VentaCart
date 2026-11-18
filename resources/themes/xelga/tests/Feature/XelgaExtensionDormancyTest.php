<?php

namespace Themes\Xelga\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesSampleExtension;
use Tests\TestCase;
use Themes\Xelga\Documents\Document;
use Themes\Xelga\Documents\Defaults;
use Themes\Xelga\Documents\Layout;
use Themes\Xelga\Documents\Revision;
use Themes\Xelga\Documents\TemplateManager;
use Themes\Xelga\Tests\XelgaExtensionHelpers;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';
require_once __DIR__ . '/../XelgaExtensionHelpers.php';

/**
 * A section whose extension is switched off or removed is dormant: kept as
 * stored across saves and publishes, drawn for nobody, shown in the editor
 * preview as a note naming its extension. Switched back on, it draws as
 * before, its values cleaned before the view sees them.
 */
class XelgaExtensionDormancyTest extends TestCase
{
    use RefreshDatabase;
    use UsesSampleExtension;
    use XelgaExtensionHelpers;
    use XelgaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpXelgaWithExtension();
        $this->product('Linen Shirt');
    }

    /** Open the product template in the editor, save it as it came, and publish. */
    private function saveAndPublish(): void
    {
        $this->forgetXelgaState();
        $state = $this->actingAs($this->admin())->getJson(route('admin.xelga.documents.show', ['handle' => 'product']))->assertOk()->json('data');
        $this->forgetXelgaState();
        $saved = $this->saveWithRows('product', $state['content'], (int) $state['draft_version'])->assertOk()->json('data.document');
        $this->forgetXelgaState();
        $this->publish($this->admin(), ['product' => (int) $saved['draft_version']])->assertOk();
    }

    /** Open the product template and save it as it came, without publishing. */
    private function saveProductDraft(): void
    {
        $this->forgetXelgaState();
        $state = $this->actingAs($this->admin())->getJson(route('admin.xelga.documents.show', ['handle' => 'product']))->assertOk()->json('data');
        $this->forgetXelgaState();
        $this->saveWithRows('product', $state['content'], (int) $state['draft_version'])->assertOk();
    }

    public function test_switched_off_the_section_survives_save_and_publish_exactly(): void
    {
        $card = $this->card(['heading' => 'Sold by Maria Santos', 'size' => 30], disabled: true);
        $this->publishedDocument('product', $this->productWith(['card-1' => $card]));
        $this->switchOffSampleExtension();

        $this->saveAndPublish();

        $stored = $this->storedSection('product', 'card-1');
        $this->assertSame('sample-extension.card', $stored['type']);
        $this->assertTrue($stored['disabled'], 'The hidden flag is kept.');
        $this->assertSame($card['settings'], $stored['settings']);
        $this->assertSame($card['block_order'], $stored['block_order']);
        $this->assertSame($card['blocks'], $stored['blocks']);
        $this->assertSame('sample-extension', $stored['source']);
        $this->assertTrue($stored['unchecked']);

        $this->switchOnSampleExtension();
        $this->saveAndPublish();

        $stored = $this->storedSection('product', 'card-1');
        $this->assertTrue($stored['disabled']);
        $this->assertSame('Sold by Maria Santos', $stored['settings']['heading']);
        $this->assertSame(30, $stored['settings']['size']);
        $this->assertArrayNotHasKey('unchecked', $stored, 'Checked again once its extension is back.');
        $this->assertSame('sample-extension', $stored['source']);
    }

    public function test_shoppers_get_nothing_and_the_editor_preview_names_the_extension(): void
    {
        $this->publishedDocument('product', $this->productWith(['card-1' => $this->card()]));
        $this->page('/product/linen-shirt')->assertSee('data-sample-card', false);

        $this->switchOffSampleExtension();
        $this->page('/product/linen-shirt')->assertDontSee('data-sample-card', false)->assertDontSee('section-note', false);

        $nonce = $this->openEditor($this->admin());
        $html = $this->page('/product/linen-shirt?xelga_preview=' . $nonce)
            ->assertSee('data-section-source="Sample Extension"', false)
            ->assertSee('Seller: Sample Extension is switched off.')
            ->getContent();
        // The editor learns the note is drawn, and by the section's own name.
        preg_match('#<script type="application/json" id="xelga-page-data">(.*?)</script>#s', $html, $m);
        $sections = array_column(json_decode(html_entity_decode($m[1] ?? 'null'), true)['sections'] ?? [], null, 'id');
        $this->assertSame('Seller', $sections['card-1']['label'] ?? null);

        $this->removeSampleExtension();
        $this->page('/product/linen-shirt?xelga_preview=' . $nonce)->assertSee('sample-extension is not installed.');
    }

    public function test_switched_back_on_it_draws_as_before(): void
    {
        $this->publishedDocument('product', $this->productWith(['card-1' => $this->card(['heading' => 'Sold by Maria Santos'])]));
        $this->switchOffSampleExtension();
        $this->saveAndPublish();

        $this->switchOnSampleExtension();

        $this->page('/product/linen-shirt')->assertSee('data-sample-card', false)->assertSee('Sold by Maria Santos')->assertSee('Ships tomorrow');
    }

    public function test_values_stored_while_dormant_are_cleaned_before_the_view_sees_them(): void
    {
        $tampered = $this->card(['heading' => str_repeat('A', 300), 'size' => 73, 'link' => ['type' => 'url', 'href' => 'javascript:alert(1)']]);
        $tampered['unchecked'] = true;
        $tampered['source'] = 'sample-extension';
        $this->publishedDocument('product', $this->productWith(['card-1' => $tampered]));

        $html = $this->page('/product/linen-shirt')->assertSee('data-sample-card', false)->getContent();

        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('data-size="73"', $html);
        $this->assertStringNotContainsString(str_repeat('A', 81), $html);
    }

    public function test_a_dormant_section_must_stay_small_and_well_formed(): void
    {
        $this->publishedDocument('product', $this->productWith(['card-1' => $this->card()]));
        $this->switchOffSampleExtension();
        $this->forgetXelgaState();
        $big = $this->card(['heading' => str_repeat('x', 17000)]);

        $this->saveDraft($this->admin(), 'product', $this->productWith(['card-1' => $big]), 0)
            ->assertStatus(422)->assertJsonPath('code', 'invalid');

        $badIds = $this->card();
        $badIds['block_order'] = ['Not An Id'];
        $this->saveDraft($this->admin(), 'product', $this->productWith(['card-1' => $badIds]), 0)->assertStatus(422);
    }

    public function test_a_client_cannot_make_a_section_dormant_or_unchecked(): void
    {
        $card = $this->card();
        $card['unchecked'] = true;
        $card['source'] = 'other-extension';
        $this->forgetXelgaState();
        $this->saveDraft($this->admin(), 'product', $this->productWith(['card-1' => $card]), 0)->assertOk();

        $stored = $this->storedSection('product', 'card-1', 'draft');
        $this->assertArrayNotHasKey('unchecked', $stored);
        $this->assertSame('sample-extension', $stored['source'], 'The source is the type\'s, never the client\'s.');

        $rich = $this->card();
        $rich['type'] = 'rich-text';
        $rich['source'] = 'sample-extension';
        $rich['block_order'] = [];
        $rich['blocks'] = [];
        $this->forgetXelgaState();
        $this->saveDraft($this->admin(), 'product', $this->productWith(['card-1' => $rich]), 1)->assertOk();
        $this->assertArrayNotHasKey('source', $this->storedSection('product', 'card-1', 'draft'));
    }

    public function test_copying_a_template_keeps_its_dormant_sections(): void
    {
        $this->publishedDocument('product', $this->productWith(['card-1' => $this->card()]));
        $this->switchOffSampleExtension();
        $this->forgetXelgaState();

        $copy = app(TemplateManager::class)->create('product', 'Gift guide', null, $this->admin())['document'];

        $this->assertSame('sample-extension.card', $copy->draft['sections']['card-1']['type']);
        $this->assertTrue($copy->draft['sections']['card-1']['unchecked']);
        $this->assertSame('Sold by Demo Store', $copy->draft['sections']['card-1']['settings']['heading']);
    }

    public function test_a_waiting_page_keeps_its_sides(): void
    {
        $page = Defaults::content('sample_page');
        $page['sections']['side-1'] = ['type' => 'rich-text', 'label' => '', 'disabled' => false, 'settings' => [], 'block_order' => [], 'blocks' => []];
        $page['order'] = ['side-1', ...$page['order']];
        $page['rows'] = array_values(array_filter(Layout::rows($page), fn ($row) => $row['columns'] !== [['side-1']]));
        $page['sides'] = ['left' => ['side-1'], 'right' => []];
        $this->publishedDocument('sample_page', $page, $page);
        $before = Document::where('handle', 'sample_page')->firstOrFail();
        $stored = [$before->draft['sides'], $before->draft['rows']];

        $this->switchOffSampleExtension();
        // While the extension is away: another page is saved and published, and the waiting page is asked for.
        $this->saveAndPublish();
        $this->forgetXelgaState();
        $this->actingAs($this->admin())->getJson(route('admin.xelga.documents.show', ['handle' => 'sample_page']))->assertNotFound();
        $revision = Revision::create(['document_id' => $before->id, 'version' => 1, 'content' => $page, 'source' => 'publish', 'created_by' => $this->admin()->id]);
        $this->forgetXelgaState();
        $this->actingAs($this->admin())->postJson(route('admin.xelga.revisions.restore', ['handle' => 'sample_page', 'revision' => $revision->id]), ['draft_version' => 0]);
        $this->forgetXelgaState();
        $this->publish($this->admin(), ['sample_page' => 0]);
        $this->switchOnSampleExtension();

        $after = Document::where('handle', 'sample_page')->firstOrFail();
        $this->assertSame(json_encode($stored), json_encode([$after->draft['sides'], $after->draft['rows']]));
        $this->assertSame(json_encode($stored), json_encode([$after->published['sides'], $after->published['rows']]));
        $this->assertSame(['side-1'], $this->actingAs($this->admin())->getJson(route('admin.xelga.documents.show', ['handle' => 'sample_page']))->assertOk()->json('data.content.sides.left'));
    }
}
