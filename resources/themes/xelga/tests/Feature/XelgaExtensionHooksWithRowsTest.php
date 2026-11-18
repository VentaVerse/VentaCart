<?php

namespace Themes\Xelga\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesSampleExtension;
use Tests\TestCase;
use Themes\Xelga\Documents\Defaults;
use Themes\Xelga\Documents\Document;
use Themes\Xelga\Extensions\Placements;
use Themes\Xelga\Tests\XelgaExtensionHelpers;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';
require_once __DIR__ . '/../XelgaExtensionHelpers.php';

/**
 * What extensions rely on keeps working on a page laid out in rows:
 * placements, sections kept while their extension is off, blocks added to
 * the page's main section, and an extension's own page type.
 */
class XelgaExtensionHooksWithRowsTest extends TestCase
{
    use RefreshDatabase;
    use UsesSampleExtension;
    use XelgaExtensionHelpers;
    use XelgaTestHelpers;

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

    /** @return array{0: array, 1: int} what the editor opens with, and its draft version */
    private function editorContent(string $handle = 'product'): array
    {
        $this->forgetXelgaState();
        $state = $this->actingAs($this->admin())->getJson(route('admin.xelga.documents.show', ['handle' => $handle]))->assertOk()->json('data');

        return [json_decode(json_encode($state['content']), true), (int) $state['draft_version']];
    }

    private function publishProduct(int $version): void
    {
        $this->forgetXelgaState();
        $this->publish($this->admin(), ['product' => $version])->assertOk();
    }

    /** @return list<list<string>>|null the columns of the row holding the section */
    private function columnsOf(array $content, string $sectionId): ?array
    {
        foreach ($content['rows'] ?? [] as $row) {
            foreach ($row['columns'] as $column) {
                if (in_array($sectionId, $column, true)) {
                    return $row['columns'];
                }
            }
        }

        return null;
    }

    public function test_placements_apply_once_and_never_return(): void
    {
        $this->publishedDocument('product', $this->withTileRow(Defaults::content('product'), 'product-main-1'));

        [$content, $version] = $this->editorContent();
        $this->assertSame(['sample-extension:card', 'sample-extension:seller'], $content['placed']);
        $this->assertSame([[$this->card]], $this->columnsOf($content, $this->card));
        $version = (int) $this->saveWithRows('product', $content, $version)->assertOk()->json('data.document.draft_version');
        $this->publishProduct($version);
        $this->assertSame(1, substr_count($this->page('/product/linen-shirt')->getContent(), 'data-sample-card'));

        // Removed by the merchant, row and all.
        [$content, $version] = $this->editorContent();
        $content['order'] = array_values(array_diff($content['order'], [$this->card]));
        unset($content['sections'][$this->card]);
        $content['rows'] = array_values(array_filter($content['rows'], fn ($row) => $row['columns'] !== [[$this->card]]));
        $version = (int) $this->saveWithRows('product', $content, $version)->assertOk()->json('data.document.draft_version');
        $this->publishProduct($version);

        $this->page('/product/linen-shirt')->assertDontSee('data-sample-card', false)->assertSee('data-sample-tile', false);
        [$content] = $this->editorContent();
        $this->assertArrayNotHasKey($this->card, $content['sections']);
        $this->assertNull($this->columnsOf($content, $this->card));
        $this->assertSame([['tile-a'], ['tile-b']], $this->columnsOf($content, 'tile-a'));
        $this->assertContains('sample-extension:card', $content['placed']);
    }

    public function test_a_dormant_section_keeps_its_column_while_its_extension_is_off(): void
    {
        $this->publishedDocument('product', $this->withTileRow(Defaults::content('product'), 'product-main-1'));
        $this->switchOffSampleExtension();

        [$content, $version] = $this->editorContent();
        $this->assertSame([['tile-a'], ['tile-b']], $this->columnsOf($content, 'tile-a'));
        $version = (int) $this->saveWithRows('product', $content, $version)->assertOk()->json('data.document.draft_version');
        $this->publishProduct($version);

        $stored = Document::where('handle', 'product')->firstOrFail()->published;
        $this->assertSame([['tile-a'], ['tile-b']], $this->columnsOf($stored, 'tile-a'));
        $this->assertTrue($stored['sections']['tile-a']['unchecked']);
        $this->page('/product/linen-shirt')->assertDontSee('data-sample-tile', false);

        $this->switchOnSampleExtension();
        $html = $this->page('/product/linen-shirt')->assertSee('Tile A')->assertSee('Tile B')->getContent();
        $this->assertLessThan(strpos($html, '<!--xelga:tile-b-->'), strpos($html, '<!--xelga:tile-a-->'));

        [$content, $version] = $this->editorContent();
        $this->saveWithRows('product', $content, $version)->assertOk()->assertJsonPath('data.adjusted', []);
        $this->assertSame([['tile-a'], ['tile-b']], $this->columnsOf(Document::where('handle', 'product')->firstOrFail()->draft, 'tile-a'));
    }

    public function test_extension_blocks_still_join_the_product_main_section(): void
    {
        $this->publishedDocument('product', $this->withTileRow(Defaults::content('product'), 'product-main-1'));

        $html = $this->page('/product/linen-shirt')->getContent();

        $title = strpos($html, 'class="pinfo__title"');
        $seller = strpos($html, 'data-block-id="' . $this->seller . '"');
        $price = strpos($html, 'data-block-type="price"');
        $this->assertNotFalse($seller);
        $this->assertTrue($title < $seller && $seller < $price, 'The block right after the title.');
        $this->assertLessThan(strpos($html, '<!--/xelga:product-main-1-->'), $seller, 'Inside the product information.');
    }

    public function test_an_extension_template_without_rows_renders_as_before(): void
    {
        $before = $this->page('/sample-extension/things/lamp')->assertSee('data-sample-main', false)->getContent();

        [$content, $version] = $this->editorContent('sample_page');
        $this->assertArrayNotHasKey('rows', $content);
        $version = (int) $this->saveWithRows('sample_page', $content, $version)->assertOk()->assertJsonPath('data.adjusted', [])->json('data.document.draft_version');
        $this->forgetXelgaState();
        $this->publish($this->admin(), ['sample_page' => $version])->assertOk();

        $stored = Document::where('handle', 'sample_page')->firstOrFail()->published;
        $this->assertArrayNotHasKey('rows', $stored);
        $this->assertArrayNotHasKey('sides', $stored);
        $this->assertSame($before, $this->page('/sample-extension/things/lamp')->getContent());
    }

    /** Run $check with these placements in the fixture instead of its own. */
    private function withPlacements(array $placements, callable $check): void
    {
        $file = base_path('tests/fixtures/extensions/sample-extension/resources/xelga/placements.json');
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

    /** @return array{0: list<array>, 1: array} the rows the page is drawn with, and the content they are of */
    private function rowsOf(array $content, string $handle = 'product'): array
    {
        $rules = app(\Themes\Xelga\Documents\LayoutRules::class, ['handle' => $handle]);
        $content = \Themes\Xelga\Documents\Layout::normalize($content, $rules)['content'];

        return [\Themes\Xelga\Documents\Layout::rows($content, $rules), $content];
    }

    private function rowIndexOf(array $rows, string $sectionId): ?int
    {
        foreach ($rows as $i => $row) {
            foreach ($row['columns'] as $column) {
                if (in_array($sectionId, $column, true)) {
                    return $i;
                }
            }
        }

        return null;
    }

    public function test_a_placement_after_the_page_content_starts_bottom(): void
    {
        [$content] = $this->editorContent();
        $this->assertArrayNotHasKey('rows', $content);
        $this->assertArrayNotHasKey('sides', $content);

        [$rows] = $this->rowsOf($content);
        $main = $this->rowIndexOf($rows, 'product-main-1');
        $card = $this->rowIndexOf($rows, $this->card);
        $this->assertSame($main + 1, $card, 'Right after the page content.');
        $this->assertSame('bottom', $rows[$card]['area']);
        $this->assertSame([[$this->card]], $rows[$card]['columns']);

        $html = $this->page('/product/linen-shirt')->getContent();
        $this->assertSame(1, substr_count($html, 'data-sample-card'));
        $this->assertLessThan(strpos($html, 'data-section-id="' . $this->card . '"'), strpos($html, 'data-section-type="product-main"'));
    }

    public function test_a_placement_after_the_page_content_starts_bottom_with_stored_rows(): void
    {
        $stored = $this->withTileRow(Defaults::content('product'), 'product-main-1');
        $stored['sections']['side-1'] = ['type' => 'rich-text', 'label' => '', 'disabled' => false, 'settings' => [], 'block_order' => [], 'blocks' => []];
        $stored['order'] = ['side-1', ...$stored['order']];
        $stored['rows'] = \Themes\Xelga\Documents\Layout::rows(array_diff_key($stored, ['rows' => 1]));
        $stored['sides'] = ['left' => ['side-1'], 'right' => []];
        $stored['rows'] = array_values(array_filter($stored['rows'], fn ($row) => $row['columns'] !== [['side-1']]));
        $this->publishedDocument('product', $stored);

        [$content] = $this->editorContent();
        $this->assertSame(['side-1'], $content['sides']['left']);
        [$rows] = $this->rowsOf($content);
        $main = $this->rowIndexOf($rows, 'product-main-1');
        $card = $this->rowIndexOf($rows, $this->card);
        $this->assertSame($main + 1, $card, 'The first row after the page content.');
        $this->assertSame('bottom', $rows[$card]['area']);
        $this->assertSame([[$this->card]], $rows[$card]['columns']);
        $this->assertSame('content', $rows[$main]['area']);

        $html = $this->page('/product/linen-shirt')->getContent();
        $card = strpos($html, 'data-section-id="' . $this->card . '"');
        $this->assertNotFalse($card);
        $this->assertGreaterThan(strpos($html, '<!--/xelga:product-main-1-->'), $card);
        $this->assertStringContainsString('class="xp" data-sides="left"', $html, 'The page has a side.');
        $this->assertStringContainsString('</div></div><!--xelga:' . $this->card . '-->', $html, 'The placement stands after the sides wrapper closes.');
    }

    public function test_a_placement_otherwise_at_the_end_ends_bottom(): void
    {
        $this->withPlacements([
            ['key' => 'card', 'section' => 'sample-extension.card', 'templates' => ['product'], 'after' => 'no-such-type', 'otherwise' => 'end'],
        ], function () {
            [$content] = $this->editorContent();
            [$rows] = $this->rowsOf($content);
            $this->assertSame($this->card, end($content['order']));
            $last = $rows[array_key_last($rows)];
            $this->assertSame([[$this->card]], $last['columns']);
            $this->assertSame('bottom', $last['area']);
            $main = $this->rowIndexOf($rows, 'product-main-1');
            $this->assertNotNull($main);
            $this->assertGreaterThan($main, array_key_last($rows));
        });
    }
}
