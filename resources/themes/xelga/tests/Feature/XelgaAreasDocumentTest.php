<?php

namespace Themes\Xelga\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesSampleExtension;
use Tests\TestCase;
use Themes\Xelga\Catalog\Catalog;
use Themes\Xelga\Catalog\CatalogLinter;
use Themes\Xelga\Documents\Defaults;
use Themes\Xelga\Documents\Document;
use Themes\Xelga\Documents\Layout;
use Themes\Xelga\Documents\Revision;
use Themes\Xelga\Documents\TemplateManager;
use Themes\Xelga\Rendering\Storefront;
use Themes\Xelga\Tests\XelgaExtensionHelpers;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';
require_once __DIR__ . '/../XelgaExtensionHelpers.php';

/**
 * Top, Content and Bottom as a saved page keeps them, the sections a page
 * always holds (its page content, the Header and the Footer), and what the
 * editor is told moved when it opens a page.
 */
class XelgaAreasDocumentTest extends TestCase
{
    use RefreshDatabase;
    use UsesSampleExtension;
    use XelgaExtensionHelpers;
    use XelgaTestHelpers;

    private const SETTINGS = ['width' => 'page', 'scheme' => '', 'space' => 'md'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpXelgaWithExtension();
    }

    private function draft(string $handle): array
    {
        return Document::where('handle', $handle)->firstOrFail()->draft;
    }

    private function row(string $id, string $section, ?string $area = null): array
    {
        $row = ['id' => $id, 'split' => '12', 'columns' => [[$section]], 'settings' => self::SETTINGS];

        return $area === null ? $row : $row + ['area' => $area];
    }

    private function text(string $heading): array
    {
        return ['type' => 'rich-text', 'label' => '', 'disabled' => false, 'settings' => ['variant' => 'start', 'heading' => $heading, 'text' => '<p>Free returns within 30 days.</p>'], 'block_order' => [], 'blocks' => []];
    }

    /** The shipped category page with Filters in its left side and one row per area. */
    private function categoryWithAreas(): array
    {
        $category = $this->defaults('category');
        $category['sections']['filters-1'] = ['type' => 'filters', 'label' => '', 'disabled' => false, 'settings' => [], 'block_order' => [], 'blocks' => []];
        $category['order'] = ['recent-1', 'category-main-1', 'filters-1', 'breadcrumbs-1'];
        $category['rows'] = [
            $this->row('row-crumbs', 'breadcrumbs-1', 'top'),
            $this->row('row-main', 'category-main-1', 'content'),
            $this->row('row-recent', 'recent-1', 'bottom'),
        ];
        $category['sides'] = ['left' => ['filters-1'], 'right' => []];

        return $category;
    }

    /** The shipped product page with its details kept in Content, which its place alone would put in Bottom. */
    private function productWithAreas(): array
    {
        $product = $this->defaults('product');
        $product['sections']['text-1'] = $this->text('Care guide');
        array_splice($product['order'], array_search('product-details-1', $product['order'], true) + 1, 0, ['text-1']);
        $rows = [];
        foreach ($product['order'] as $id) {
            $area = match ($id) {
                'breadcrumbs-1' => 'top',
                'product-main-1', 'product-details-1', 'text-1' => 'content',
                default => 'bottom',
            };
            $rows[] = $this->row('row-' . $id, $id, $area);
        }
        $product['rows'] = $rows;

        return $product;
    }

    /** @return array<string, string> row id => area */
    private function areas(array $content): array
    {
        return array_column($content['rows'], 'area', 'id');
    }

    private function restore(string $handle, Revision $revision, int $version): \Illuminate\Testing\TestResponse
    {
        $this->forgetXelgaState();

        return $this->actingAs($this->admin())->postJson(route('admin.xelga.revisions.restore', ['handle' => $handle, 'revision' => $revision->id]), ['draft_version' => $version]);
    }

    private function state(string $handle): array
    {
        $this->forgetXelgaState();

        return $this->actingAs($this->admin())->getJson(route('admin.xelga.documents.show', ['handle' => $handle]))->assertOk()->json('data');
    }

    public function test_a_draft_with_areas_saves_and_order_follows_the_areas(): void
    {
        $this->saveWithRows('category', $this->categoryWithAreas(), 0)->assertOk();

        $draft = $this->draft('category');
        $this->assertSame(['breadcrumbs-1', 'filters-1', 'category-main-1', 'recent-1'], $draft['order']);
        $this->assertSame(['row-crumbs' => 'top', 'row-main' => 'content', 'row-recent' => 'bottom'], $this->areas($draft));
        $this->assertSame(['left' => ['filters-1']], $draft['sides']);
    }

    public function test_an_unknown_area_is_refused_with_a_path(): void
    {
        $category = $this->categoryWithAreas();
        $category['rows'][0]['area'] = 'middle';

        $this->saveWithRows('category', $category, 0)->assertStatus(422)->assertJsonPath('code', 'invalid')
            ->assertJsonPath('errors', ['content.rows.0.area' => ['A row is in the Top, Content or Bottom area.']]);
        $this->assertNull(Document::where('handle', 'category')->first(), 'Nothing is written.');
    }

    public function test_a_save_without_areas_is_refused_once_the_page_has_areas(): void
    {
        $this->saveWithRows('product', $this->productWithAreas(), 0)->assertOk();
        $before = Document::where('handle', 'product')->firstOrFail();

        // An editor that keeps rows but knows no areas would save every row without its area.
        $this->forgetXelgaState();
        $this->actingAs($this->admin())->putJson(route('admin.xelga.documents.draft', ['handle' => 'product']), [
            'content' => $this->defaults('product') + ['rows' => [$this->row('row-x', 'product-main-1')]],
            'draft_version' => 1,
            'with_rows' => true,
        ])->assertStatus(409)->assertJsonPath('code', 'editor-outdated');

        $after = Document::where('handle', 'product')->firstOrFail();
        $this->assertSame($before->draft, $after->draft);
        $this->assertSame(1, (int) $after->draft_version);

        // Home has no areas, so rows alone are enough there.
        $this->saveWithRows('home', $this->withTileRow($this->defaults('home'), 'hero-1'), 0)->assertOk();
        $this->forgetXelgaState();
        $this->actingAs($this->admin())->putJson(route('admin.xelga.documents.draft', ['handle' => 'home']), [
            'content' => $this->withTileRow($this->defaults('home'), 'usp-1'),
            'draft_version' => 1,
            'with_rows' => true,
        ])->assertOk();
    }

    public function test_a_revision_saved_before_areas_restores_with_areas(): void
    {
        $product = $this->defaults('product');
        $product['sections']['side-1'] = $this->text('Free returns');
        $product['sections']['side-2'] = $this->text('Secure checkout');
        $rows = [];
        foreach ($product['order'] as $id) {
            $rows[] = $this->row('row-' . $id, $id);
        }
        $product['order'] = ['side-1', ...$product['order'], 'side-2'];
        $product['rows'] = $rows;
        $product['sides'] = ['left' => ['side-1'], 'right' => ['side-2']];
        $document = $this->publishedDocument('product', $this->defaults('product'));
        $revision = Revision::create(['document_id' => $document->id, 'version' => 3, 'content' => $product, 'source' => 'publish', 'created_by' => $this->admin()->id]);

        $adjusted = $this->restore('product', $revision, 0)->assertOk()->json('data.adjusted');

        $draft = $this->draft('product');
        $ids = array_keys($product['sections']);
        $kept = $draft['order'];
        sort($ids);
        sort($kept);
        $this->assertSame($ids, $kept, 'Every section is kept.');
        $this->assertSame(['left' => ['side-1'], 'right' => ['side-2']], $draft['sides']);
        $this->assertNotSame([], $adjusted, 'A full-width section no longer fits beside two sides.');
        foreach ($adjusted as $entry) {
            $this->assertContains($entry['reason'], ['too-wide', 'in-order']);
        }
        // Rows stored beside the sides stay beside them, in Content, unless what they hold moved out.
        $moved = array_column($adjusted, 'path');
        foreach ($draft['rows'] as $row) {
            if ($row['area'] !== 'content') {
                $this->assertContains('content.sections.' . $row['columns'][0][0], $moved);
            }
        }
        $this->assertContains('content', array_column($draft['rows'], 'area'));
    }

    public function test_a_home_revision_with_sides_restores(): void
    {
        $home = $this->defaults('home');
        $home['sides'] = ['left' => ['text-1'], 'right' => []];
        $document = $this->publishedDocument('home', $this->defaults('home'));
        $revision = Revision::create(['document_id' => $document->id, 'version' => 2, 'content' => $home, 'source' => 'publish', 'created_by' => $this->admin()->id]);

        $adjusted = $this->restore('home', $revision, 0)->assertOk()->json('data.adjusted');

        $draft = $this->draft('home');
        $this->assertArrayNotHasKey('sides', $draft);
        $this->assertContains('text-1', $draft['order']);
        $this->assertContains('no-sides', array_column($adjusted, 'reason'));
    }

    public function test_the_header_cannot_be_removed(): void
    {
        $header = $this->defaults('header');
        unset($header['sections']['header-1']);
        $header['order'] = array_values(array_diff($header['order'], ['header-1']));

        $this->saveDraft($this->admin(), 'header', $header, 0)->assertStatus(422)
            ->assertJsonPath('errors', ['content.order' => ['Header is fixed in the header band and cannot be removed.']]);
        $this->assertNull(Document::where('handle', 'header')->first());

        $footer = $this->defaults('footer');
        $footer['order'] = [];
        $footer['sections'] = [];
        $this->saveDraft($this->admin(), 'footer', $footer, 0)->assertStatus(422)
            ->assertJsonPath('errors', ['content.order' => ['Footer is fixed in the footer band and cannot be removed.']]);
    }

    public function test_a_hidden_header_stays_hidden(): void
    {
        $header = $this->defaults('header');
        $header['sections']['header-1']['disabled'] = true;

        $this->saveDraft($this->admin(), 'header', $header, 0)->assertOk();
        $this->assertTrue($this->draft('header')['sections']['header-1']['disabled']);
        $this->publish($this->admin(), ['header' => 1])->assertOk();

        $html = $this->page('/')->getContent();
        $this->assertStringContainsString('<!--xelga:announcement-1-->', $html);
        $this->assertStringNotContainsString('<!--xelga:header-1-->', $html, 'Shoppers see no Header.');
        $this->assertTrue(Storefront::withLockedSections(Document::where('handle', 'header')->firstOrFail()->published, 'header')['sections']['header-1']['disabled']);
        $this->assertTrue($this->state('header')['content']['sections']['header-1']['disabled']);
    }

    public function test_a_header_stored_without_its_header_gets_it_back_hidden(): void
    {
        $header = $this->defaults('header');
        unset($header['sections']['header-1']);
        $header['order'] = ['announcement-1'];
        $this->publishedDocument('header', $header);

        $read = Storefront::withLockedSections($header, 'header');
        $this->assertSame(['announcement-1', 'header-1'], $read['order']);
        $this->assertSame('header', $read['sections']['header-1']['type']);
        $this->assertTrue($read['sections']['header-1']['disabled'], 'The store keeps its look.');

        $state = $this->state('header');
        $this->assertSame(['announcement-1', 'header-1'], $state['content']['order']);
        $this->assertTrue($state['content']['sections']['header-1']['disabled']);

        $html = $this->page('/')->getContent();
        $this->assertStringContainsString('<!--xelga:announcement-1-->', $html);
        $this->assertStringNotContainsString('<!--xelga:header-1-->', $html);

        // A revision of it restores, and a draft of it publishes, with the Header back and hidden.
        $document = Document::where('handle', 'header')->firstOrFail();
        $revision = Revision::create(['document_id' => $document->id, 'version' => 1, 'content' => $header, 'source' => 'publish', 'created_by' => $this->admin()->id]);
        $this->restore('header', $revision, 0)->assertOk();
        $this->assertSame(['announcement-1', 'header-1'], $this->draft('header')['order']);
        $this->assertTrue($this->draft('header')['sections']['header-1']['disabled']);
        $document->forceFill(['draft' => $header])->save();
        $this->publish($this->admin(), ['header' => (int) $document->fresh()->draft_version])->assertOk();
        $this->assertTrue(Document::where('handle', 'header')->firstOrFail()->published['sections']['header-1']['disabled']);
    }

    public function test_the_page_content_still_cannot_be_hidden(): void
    {
        $product = $this->defaults('product');
        $product['sections']['product-main-1']['disabled'] = true;

        $this->saveDraft($this->admin(), 'product', $product, 0)->assertStatus(422)
            ->assertJsonPath('errors', ['content.sections.product-main-1.disabled' => ['Product information is the page\'s main section and cannot be hidden. Hide its blocks instead.']]);
    }

    public function test_opening_home_with_sides_reports_what_moved(): void
    {
        $home = $this->defaults('home');
        $home['sides'] = ['left' => ['text-1'], 'right' => []];
        $this->publishedDocument('home', $home);

        $state = $this->state('home');
        $this->assertContains('no-sides', array_column($state['adjusted'], 'reason'));
        $this->assertArrayNotHasKey('sides', $state['content']);

        $this->assertSame([], $this->state('product')['adjusted']);
    }

    public function test_duplicate_and_make_a_layout_for_one_record_keep_areas(): void
    {
        $this->saveWithRows('product', $this->productWithAreas(), 0)->assertOk();
        $areas = $this->areas($this->draft('product'));
        $this->assertSame('content', $areas['row-product-details-1']);

        $this->forgetXelgaState();
        $this->actingAs($this->admin())->postJson(route('admin.xelga.documents.duplicate', ['handle' => 'product']), [
            'section' => 'text-1', 'block' => null, 'draft_version' => 1,
        ])->assertOk();
        $copied = $this->areas($this->draft('product'));
        $this->assertSame($areas, array_intersect_key($copied, $areas), 'Every row keeps its area.');

        $this->forgetXelgaState();
        $layout = app(TemplateManager::class)->create('product', 'Gift guide', null, $this->admin())['document'];
        $this->assertSame($copied, $this->areas($layout->draft));
    }

    public function test_the_header_and_footer_are_fixed_and_main_sections_locked(): void
    {
        $this->assertTrue(Catalog::section('header')->fixed());
        $this->assertTrue(Catalog::section('footer')->locked());
        $this->assertFalse(Catalog::section('product-main')->fixed());
        $this->assertTrue(Catalog::section('product-main')->locked());
        $this->assertFalse(Catalog::section('announcement-bar')->locked());
        $this->assertSame(['header'], Defaults::lockedTypes('header'));
        $this->assertSame(['footer'], Defaults::lockedTypes('footer'));
        $this->assertSame(['product-main'], Defaults::lockedTypes('product.gift-guide'));
        $this->assertSame([], Defaults::lockedTypes('home'));

        $header = $this->defaults('header');
        $header['sections']['header-1']['disabled'] = true;
        $this->assertSame(['missing' => [], 'extra' => [], 'hidden_main' => []], Defaults::lockedState('header', $header['sections']));
        $product = $this->defaults('product');
        $product['sections']['product-main-1']['disabled'] = true;
        $product['sections']['product-main-2'] = $product['sections']['product-main-1'];
        $this->assertSame(['missing' => [], 'extra' => ['product-main'], 'hidden_main' => ['product-main-1', 'product-main-2']], Defaults::lockedState('product', $product['sections']));
        $this->assertSame(['missing' => ['product-main'], 'extra' => [], 'hidden_main' => []], Defaults::lockedState('product', []));

        $html = $this->actingAs($this->admin())->get(route('admin.xelga.editor'))->getContent();
        preg_match('/<script type="application\/json" id="xelga-boot">(.*?)<\/script>/s', (string) $html, $m);
        $sections = json_decode(html_entity_decode($m[1] ?? '', ENT_QUOTES), true)['catalog']['sections'];
        $this->assertTrue($sections['header']['fixed']);
        $this->assertFalse($sections['hero']['fixed']);
    }

    public function test_lint_allows_fixed_only_on_the_theme_header_and_footer_sections(): void
    {
        $wrong = 'Section zz-probe: only the theme\'s own header and footer sections can be fixed.';
        $probe = function (array $extra, callable $check): void {
            $schema = Catalog::root('schemas/sections/zz-probe.json');
            file_put_contents($schema, json_encode($extra + [
                'type' => 'zz-probe', 'label' => 'Probe', 'group' => 'template', 'templates' => ['home'],
                'class' => 's-zz-probe', 'picker_group' => 'text', 'settings' => [],
            ]));
            try {
                $check(CatalogLinter::check());
            } finally {
                @unlink($schema);
                Catalog::flush();
            }
        };

        $probe(['fixed' => true], fn (array $problems) => $this->assertContains($wrong, $problems));
        $probe(['fixed' => true, 'group' => 'header', 'templates' => []], fn (array $problems) => $this->assertNotContains($wrong, $problems));
        $probe(['fixed' => 'yes', 'group' => 'header', 'templates' => []], fn (array $problems) => $this->assertContains('Section zz-probe: fixed is true or false.', $problems));
        $this->assertSame([], CatalogLinter::check());
    }

    /** The shipped header without its Header, as a store that removed it before it was locked has it. */
    private function headerWithoutHeader(): array
    {
        $header = $this->defaults('header');
        unset($header['sections']['header-1']);
        $header['order'] = ['announcement-1'];

        return $header;
    }

    public function test_a_copy_in_a_header_stored_without_its_header_saves(): void
    {
        // Two messages, so a third fits.
        $header = $this->headerWithoutHeader();
        unset($header['sections']['announcement-1']['blocks']['announcement-1-b3']);
        $header['sections']['announcement-1']['block_order'] = ['announcement-1-b1', 'announcement-1-b2'];
        $this->publishedDocument('header', $header);

        $this->forgetXelgaState();
        $this->actingAs($this->admin())->postJson(route('admin.xelga.documents.duplicate', ['handle' => 'header']), [
            'section' => 'announcement-1', 'block' => 'announcement-1-b1', 'draft_version' => 0,
        ])->assertOk();

        $draft = $this->draft('header');
        $this->assertSame(['announcement-1', 'header-1'], $draft['order']);
        $this->assertTrue($draft['sections']['header-1']['disabled']);
        $this->assertCount(3, $draft['sections']['announcement-1']['block_order']);
    }

    public function test_a_layout_copied_from_a_page_stored_without_its_page_content_keeps_it(): void
    {
        $product = $this->defaults('product');
        unset($product['sections']['product-main-1']);
        $product['order'] = array_values(array_diff($product['order'], ['product-main-1']));
        $this->publishedDocument('product', $product);

        $this->forgetXelgaState();
        $layout = app(TemplateManager::class)->create('product', 'Gift guide', null, $this->admin())['document'];

        $this->assertSame('product-main', $layout->draft['sections']['product-main-1']['type']);
        $this->assertFalse($layout->draft['sections']['product-main-1']['disabled']);
        $this->assertSame(array_search('product-main-1', $this->defaults('product')['order'], true), array_search('product-main-1', $layout->draft['order'], true));
    }

    public function test_undoing_a_starting_store_puts_back_a_header_draft_without_its_header(): void
    {
        $this->publishedDocument('header', $this->defaults('header'), $this->headerWithoutHeader());
        $versions = [];
        foreach (['settings', 'header', 'footer', 'home'] as $handle) {
            $versions[$handle] = (int) (Document::where('handle', $handle)->value('draft_version') ?? 0);
        }

        $this->forgetXelgaState();
        $token = $this->actingAs($this->admin())->postJson(route('admin.xelga.store-templates.apply', ['slug' => 'parts']), ['bring' => 'all', 'versions' => $versions])
            ->assertOk()->json('data.undo');
        $this->forgetXelgaState();
        $this->actingAs($this->admin())->postJson(route('admin.xelga.store-templates.undo'), ['token' => $token])->assertOk();

        $draft = $this->draft('header');
        $this->assertSame(['announcement-1', 'header-1'], $draft['order']);
        $this->assertTrue($draft['sections']['header-1']['disabled']);
    }
}
