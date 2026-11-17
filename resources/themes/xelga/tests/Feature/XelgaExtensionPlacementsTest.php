<?php

namespace Themes\Xelga\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesSampleExtension;
use Tests\TestCase;
use Themes\Xelga\Catalog\CatalogLinter;
use Themes\Xelga\Documents\Defaults;
use Themes\Xelga\Documents\Document;
use Themes\Xelga\Documents\TemplateManager;
use Themes\Xelga\Extensions\Placements;
use Themes\Xelga\Tests\XelgaExtensionHelpers;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';
require_once __DIR__ . '/../XelgaExtensionHelpers.php';

/**
 * An extension's placements put its section or block where it belongs by
 * default, as a page is read: once per document, insert only, and never
 * again after the merchant removes it. Nothing is written until a save.
 */
class XelgaExtensionPlacementsTest extends TestCase
{
    use RefreshDatabase;
    use UsesSampleExtension;
    use XelgaExtensionHelpers;
    use XelgaTestHelpers;

    private const PLACEMENTS = 'tests/fixtures/extensions/sample-extension/resources/xelga/placements.json';

    private string $card;

    private string $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpXelgaWithExtension();
        $this->product('Linen Shirt');
        $this->card = Placements::insertId('sample-extension:card');
        $this->seller = Placements::insertId('sample-extension:seller');
    }

    private function editorContent(string $handle = 'product'): array
    {
        $this->forgetXelgaState();
        $state = $this->actingAs($this->admin())->getJson(route('admin.xelga.documents.show', ['handle' => $handle]))->assertOk()->json('data');

        return [json_decode(json_encode($state['content']), true), (int) $state['draft_version']];
    }

    private function save(array $content, int $version, string $handle = 'product'): int
    {
        $this->forgetXelgaState();

        return (int) $this->saveDraft($this->admin(), $handle, $content, $version)->assertOk()->json('data.document.draft_version');
    }

    private function publishProduct(int $version): void
    {
        $this->forgetXelgaState();
        $this->publish($this->admin(), ['product' => $version])->assertOk();
    }

    /** Run $check with these placements in the fixture instead of its own. */
    private function withPlacements(array $placements, callable $check): void
    {
        $file = base_path(self::PLACEMENTS);
        $original = (string) file_get_contents($file);
        file_put_contents($file, json_encode(['placements' => $placements]));
        try {
            $this->forgetXelgaState();
            $check();
        } finally {
            file_put_contents($file, $original);
            $this->forgetXelgaState();
        }
    }

    public function test_a_never_saved_product_page_shows_the_section_and_the_block_in_place(): void
    {
        $html = $this->page('/product/linen-shirt')->getContent();

        $this->assertSame(1, substr_count($html, 'data-sample-card'));
        $this->assertStringContainsString('data-section-id="' . $this->card . '"', $html);
        $main = strpos($html, 'data-section-type="product-main"');
        $card = strpos($html, 'data-section-id="' . $this->card . '"');
        $this->assertTrue($main < $card, 'After the product information.');
        $this->assertSame(1, substr_count(substr($html, $main, $card - $main), '<!--/xelga:'), 'Right after it: only the product information closes in between.');

        $title = strpos($html, 'class="pinfo__title"');
        $seller = strpos($html, 'data-block-id="' . $this->seller . '"');
        $price = strpos($html, 'data-block-type="price"');
        $this->assertTrue($title < $seller && $seller < $price, 'The block right after the title.');
        $this->assertNull(Document::where('handle', 'product')->first(), 'Nothing is written.');
    }

    public function test_a_saved_template_gets_them_too_and_the_ids_never_change(): void
    {
        $this->publishedDocument('product', Defaults::content('product'));

        $first = $this->page('/product/linen-shirt')->getContent();
        $second = $this->page('/product/linen-shirt')->getContent();

        $this->assertStringContainsString('data-section-id="' . $this->card . '"', $first);
        $this->assertStringContainsString('data-section-id="' . $this->card . '"', $second);
    }

    public function test_the_editor_gets_them_with_the_record_and_saving_keeps_both(): void
    {
        [$content, $version] = $this->editorContent();

        $this->assertSame('sample-extension.card', $content['sections'][$this->card]['type']);
        $this->assertSame(['sample-extension:card', 'sample-extension:seller'], $content['placed']);

        $this->publishProduct($this->save($content, $version));

        $stored = Document::where('handle', 'product')->first()->published;
        $this->assertSame(['sample-extension:card', 'sample-extension:seller'], $stored['placed']);
        $this->assertSame('Sold by Demo Store', $stored['sections'][$this->card]['settings']['heading'], 'The preset\'s settings.');
        $this->assertArrayNotHasKey('unchecked', $stored['sections'][$this->card], 'Checked when saved.');
        $this->assertSame('sample-extension.seller', $stored['sections']['product-main-1']['blocks'][$this->seller]['type']);
    }

    public function test_once_removed_they_never_come_back(): void
    {
        [$content, $version] = $this->editorContent();
        $content['order'] = array_values(array_diff($content['order'], [$this->card]));
        unset($content['sections'][$this->card]);
        $main = &$content['sections']['product-main-1'];
        $main['block_order'] = array_values(array_diff($main['block_order'], [$this->seller]));
        unset($main['blocks'][$this->seller]);
        unset($main);
        $this->publishProduct($this->save($content, $version));

        $this->page('/product/linen-shirt')->assertDontSee('data-sample-card', false)->assertDontSee('data-sample-seller', false);

        // Not after switching the extension off and on, nor after a save that forgot the record.
        $this->switchOffSampleExtension();
        $this->page('/product/linen-shirt');
        $this->switchOnSampleExtension();
        [$content, $version] = $this->editorContent();
        $this->assertArrayNotHasKey($this->card, $content['sections']);
        unset($content['placed']);
        $this->save($content, $version);
        [$content] = $this->editorContent();
        $this->assertArrayNotHasKey($this->card, $content['sections']);
        $this->assertContains('sample-extension:card', $content['placed']);
        $this->page('/product/linen-shirt')->assertDontSee('data-sample-card', false);
    }

    public function test_a_save_records_only_placements_that_exist_or_were_stored(): void
    {
        $junk = array_map(fn (int $n) => "zz:junk-{$n}", range(1, 100));
        [$content, $version] = $this->editorContent();
        $content['placed'] = [...$junk, 'marketplace:seller-card'];
        $version = $this->save($content, $version);

        $placed = Document::where('handle', 'product')->value('draft')['placed'] ?? [];
        $this->assertSame([], array_values(array_diff($placed, ['sample-extension:card', 'sample-extension:seller'])), 'Made-up ids and ids of extensions the store lacks are not recorded.');

        $this->publishProduct($version);
        $this->page('/product/linen-shirt')->assertSee('data-sample-card', false);
    }

    public function test_a_full_record_of_stale_ids_makes_room_and_keeps_what_was_removed(): void
    {
        // Written before saves were checked: a full record of ids of an extension never installed.
        $content = Defaults::content('product');
        $content['placed'] = [...array_map(fn (int $n) => "zz:junk-{$n}", range(1, 99)), 'sample-extension:seller'];
        $this->publishedDocument('product', $content);
        $this->page('/product/linen-shirt')->assertDontSee('data-sample-card', false);

        [$content, $version] = $this->editorContent();
        $this->publishProduct($this->save($content, $version));

        $placed = Document::where('handle', 'product')->value('published')['placed'];
        $this->assertNotContains('zz:junk-1', $placed);
        $this->assertContains('sample-extension:seller', $placed, 'A stored id of a known placement stays, so its block never returns.');
        $html = $this->page('/product/linen-shirt')->assertSee('data-sample-card', false)->getContent();
        $this->assertStringNotContainsString('data-sample-seller', $html);
    }

    public function test_stored_ids_stay_while_their_extension_is_away(): void
    {
        [$content, $version] = $this->editorContent();
        $version = $this->save($content, $version);
        $this->publishProduct($version);
        $this->switchOffSampleExtension();

        [$content, $version] = $this->editorContent();
        $content['placed'] = [];
        $this->save($content, $version);

        $this->assertContains('sample-extension:card', Document::where('handle', 'product')->value('draft')['placed']);
    }

    public function test_a_page_that_already_has_the_section_gets_no_second_one(): void
    {
        $this->publishedDocument('product', $this->productWith(['my-card' => $this->card(['heading' => 'Mine'])]));

        $html = $this->page('/product/linen-shirt')->assertSee('Mine')->getContent();

        $this->assertSame(1, substr_count($html, 'data-sample-card'));
    }

    public function test_a_full_page_gets_nothing(): void
    {
        $content = Defaults::content('product');
        for ($i = count($content['order']); $i < 40; $i++) {
            $content['order'][] = "text-{$i}";
            $content['sections']["text-{$i}"] = ['type' => 'rich-text', 'label' => 'Text', 'disabled' => false, 'settings' => [], 'block_order' => [], 'blocks' => []];
        }
        $this->publishedDocument('product', $content);

        $this->page('/product/linen-shirt')->assertDontSee('data-sample-card', false);
    }

    public function test_special_layouts_get_the_section_only_when_the_placement_says_so(): void
    {
        $layout = app(TemplateManager::class)->create('product', 'Gift guide', null, $this->admin())['document'];
        [$content] = $this->editorContent($layout->handle);
        $this->assertArrayHasKey($this->card, $content['sections']);

        $this->withPlacements([
            ['key' => 'card', 'section' => 'sample-extension.card', 'templates' => ['product'], 'after' => 'product-main'],
        ], function () use ($layout) {
            [$content] = $this->editorContent($layout->handle);
            $this->assertArrayNotHasKey($this->card, $content['sections']);
            [$content] = $this->editorContent('product');
            $this->assertArrayHasKey($this->card, $content['sections']);
        });
    }

    public function test_a_placement_is_given_where_its_section_is_allowed_only(): void
    {
        $this->withPlacements([
            ['key' => 'card', 'section' => 'sample-extension.card', 'templates' => ['home', 'cart'], 'otherwise' => 'start'],
        ], function () {
            [$content] = $this->editorContent('home');
            $this->assertArrayNotHasKey($this->card, $content['sections'], 'The card is not allowed on the home page.');
            $this->assertContains(
                'sample-extension: placements.json: placement sample-extension:card puts sample-extension.card on home, where it is not allowed.',
                CatalogLinter::checkExtension('sample-extension'),
            );
        });
    }

    public function test_placements_that_break_a_rule_are_refused(): void
    {
        $this->withPlacements([
            ['key' => 'Bad Key', 'section' => 'sample-extension.card', 'templates' => ['product']],
            ['key' => 'foreign', 'section' => 'rich-text', 'templates' => ['product']],
            ['key' => 'main', 'section' => 'sample-extension.main', 'templates' => ['sample_page']],
            ['key' => 'both', 'section' => 'sample-extension.card', 'templates' => ['product'], 'after' => 'a', 'before' => 'b'],
            ['key' => 'block-nowhere', 'block' => 'sample-extension.seller', 'section' => 'product-details'],
            ['key' => 'ok', 'section' => 'sample-extension.card', 'templates' => ['product']],
            ['key' => 'ok', 'section' => 'sample-extension.card', 'templates' => ['product']],
        ], function () {
            $problems = CatalogLinter::checkExtension('sample-extension');
            foreach (['placement Bad Key', 'placement foreign', 'placement main', 'placement both', 'placement block-nowhere', 'placement ok: its key is used twice'] as $expected) {
                $this->assertNotEmpty(array_filter($problems, fn ($p) => str_contains($p, $expected)), $expected);
            }
            $this->assertSame(['sample-extension:ok'], array_column(\Themes\Xelga\Catalog\Catalog::all()['placements'], 'id'));
        });
    }
}
