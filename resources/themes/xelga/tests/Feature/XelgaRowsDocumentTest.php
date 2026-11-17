<?php

namespace Themes\Xelga\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Tests\Support\UsesSampleExtension;
use Tests\TestCase;
use Themes\Xelga\Documents\Defaults;
use Themes\Xelga\Documents\Document;
use Themes\Xelga\Documents\Layout;
use Themes\Xelga\Documents\LayoutRules;
use Themes\Xelga\Documents\Revision;
use Themes\Xelga\Extensions\Placements;
use Themes\Xelga\Tests\XelgaExtensionHelpers;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';
require_once __DIR__ . '/../XelgaExtensionHelpers.php';

/**
 * Rows and sides as a saved page keeps them: checked on save, kept by
 * copies and restores, and read back into one reading order when the page
 * is drawn. A layout problem never costs the shopper the page.
 */
class XelgaRowsDocumentTest extends TestCase
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

    private function draft(string $handle): array
    {
        return Document::where('handle', $handle)->firstOrFail()->draft;
    }

    /** The row holding the section, as the stored draft has it. */
    private function rowOf(array $content, string $sectionId): ?array
    {
        foreach ($content['rows'] ?? [] as $row) {
            foreach ($row['columns'] as $column) {
                if (in_array($sectionId, $column, true)) {
                    return $row;
                }
            }
        }

        return null;
    }

    /** The home page with every row written out, the way the editor sends it. */
    private function homeRows(array $content): array
    {
        $content['rows'] = Layout::rows(array_diff_key($content, ['rows' => true]));

        return $content;
    }

    public function test_a_draft_with_rows_saves_and_order_follows_the_rows(): void
    {
        $home = $this->withTileRow($this->defaults('home'), 'hero-1');
        // Within a row the row decides, whatever order was sent.
        $home['order'] = ['hero-1', 'tile-b', 'tile-a', ...array_slice($home['order'], 3)];

        $this->saveWithRows('home', $home, 0)->assertOk()->assertJsonPath('data.adjusted', []);

        $draft = $this->draft('home');
        $rest = array_values(array_diff($this->defaults('home')['order'], ['hero-1', 'usp-1']));
        $this->assertSame(['hero-1', 'tile-a', 'tile-b', 'usp-1', ...$rest], $draft['order']);
        $this->assertSame(['id' => 'row-tiles', 'split' => '6-6', 'columns' => [['tile-a'], ['tile-b']], 'settings' => Layout::ROW_DEFAULTS], $this->rowOf($draft, 'tile-a'));
        $this->assertSame('row-tiles', $draft['rows'][1]['id'], 'Right after the banner\'s row.');
    }

    public function test_malformed_rows_are_refused_with_a_path(): void
    {
        $home = $this->withTileRow($this->defaults('home'), 'hero-1');
        $refused = function (string $handle, array $content, string $path): void {
            $this->saveWithRows($handle, $content, 0)->assertStatus(422)->assertJsonPath('code', 'invalid')
                ->assertJsonStructure(['errors' => [$path]]);
            $this->assertNull(Document::where('handle', $handle)->first(), "Nothing is written for {$path}.");
        };

        $refused('home', ['rows' => [['split' => '5-7', 'columns' => [['tile-a'], ['tile-b']]]]] + $home, 'content.rows.0.split');
        $refused('home', ['rows' => ['row-tiles' => $home['rows'][0]]] + $home, 'content.rows');
        $refused('home', ['rows' => [['id' => 'row-tiles', 'split' => '6-6', 'columns' => [['tile-a', 'tile-b']]]]] + $home, 'content.rows.0.columns');
        $refused('home', ['rows' => [['id' => 'row-tiles', 'split' => '12', 'columns' => [['tile-a', 'tile-b']]]]] + $home, 'content.rows.0.columns.0');
        $refused('home', ['rows' => [['id' => 'Row Tiles', 'split' => '6-6', 'columns' => [['tile-a'], ['tile-b']]]]] + $home, 'content.rows.0.id');
        $refused('home', ['rows' => [['id' => 'hero-1', 'split' => '6-6', 'columns' => [['tile-a'], ['tile-b']]]]] + $home, 'content.rows.0.id');
        $refused('home', ['rows' => [
            ['id' => 'row-a', 'split' => '6-6', 'columns' => [['tile-a'], []]],
            ['id' => 'row-a', 'split' => '6-6', 'columns' => [['tile-b'], []]],
        ]] + $home, 'content.rows.1.id');
        $refused('home', ['rows' => [['id' => 'row-tiles', 'split' => '6-6', 'columns' => [['tile-a'], ['tile-a']]]]] + $home, 'content.rows.0.columns.1.0');
        $refused('home', ['rows' => array_fill(0, Layout::MAX_ROWS + 1, ['split' => '6-6', 'columns' => [[], []]])] + $home, 'content.rows');
        $refused('home', ['sides' => ['left' => ['tile-a'], 'right' => ['tile-a']]] + $home, 'content.sides.right.0');
        $refused('home', ['sides' => ['middle' => ['tile-a']]] + $home, 'content.sides');

        $many = $this->defaults('home');
        $ids = [];
        for ($i = 1; $i <= Layout::MAX_PER_COLUMN + 1; $i++) {
            $many['sections']["tile-{$i}"] = $this->tile();
            $many['order'][] = $ids[] = "tile-{$i}";
        }
        $refused('home', ['rows' => [['split' => '6-6', 'columns' => [$ids, []]]]] + $many, 'content.rows.0.columns.0');
        $refused('home', ['sides' => ['left' => $ids]] + $many, 'content.sides.left');

        $cart = $this->defaults('cart');
        $refused('cart', ['sides' => ['left' => [$cart['order'][0]]]] + $cart, 'content.sides');
        $header = $this->defaults('header');
        $refused('header', ['sides' => ['left' => [$header['order'][0]]]] + $header, 'content.sides');
    }

    public function test_row_settings_are_cleaned_and_reported(): void
    {
        $home = $this->withTileRow($this->defaults('home'), 'hero-1');
        $home['rows'][0]['settings'] = ['width' => 'huge', 'scheme' => 'scheme-2', 'space' => 'lg', 'border' => 'thick'];

        $adjusted = $this->saveWithRows('home', $home, 0)->assertOk()->json('data.adjusted');

        $this->assertSame([['path' => 'content.rows.0.settings.width', 'reason' => 'Not one of the choices; the default was used.', 'value' => 'page']], $adjusted);
        $this->assertSame(['width' => 'page', 'scheme' => 'scheme-2', 'space' => 'lg'], $this->rowOf($this->draft('home'), 'tile-a')['settings']);

        $home['rows'][0]['settings'] = ['scheme' => 'scheme-99'];
        $this->saveWithRows('home', $home, 1)->assertOk()->assertJsonPath('data.adjusted.0.path', 'content.rows.0.settings.scheme');
        $this->assertSame(Layout::ROW_DEFAULTS, $this->rowOf($this->draft('home'), 'tile-a')['settings']);
    }

    public function test_a_section_too_wide_for_its_column_moves_to_its_own_row_and_is_reported(): void
    {
        $home = $this->homeRows($this->withTileRow($this->defaults('home'), 'hero-1'));
        // The banner and tile A side by side; tile B gets its own row.
        $rows = array_values(array_filter($home['rows'], fn ($row) => ! in_array($row['columns'][0][0] ?? null, ['hero-1', 'tile-a', 'tile-b'], true)));
        $home['rows'] = [
            ['id' => 'row-top', 'split' => '6-6', 'columns' => [['hero-1'], ['tile-a']], 'settings' => []],
            ['id' => 'row-b', 'split' => '12', 'columns' => [['tile-b']], 'settings' => []],
            ...$rows,
        ];

        $response = $this->saveWithRows('home', $home, 0)->assertOk();

        $draft = $this->draft('home');
        $heroRow = $this->rowOf($draft, 'hero-1');
        $this->assertSame([['path' => 'content.sections.hero-1', 'reason' => 'too-wide', 'value' => $heroRow['id']]], $response->json('data.adjusted'));
        $this->assertSame(['row-top', $heroRow['id'], 'row-b'], array_slice(array_column($draft['rows'], 'id'), 0, 3), 'Its own row, right after the row it left.');
        $this->assertSame([[], ['tile-a']], $draft['rows'][0]['columns']);
        $this->assertSame(['tile-a', 'hero-1', 'tile-b'], array_slice($draft['order'], 0, 3));

        // The same content restored from an earlier version is moved and reported alike.
        $document = Document::where('handle', 'home')->firstOrFail();
        $revision = Revision::create(['document_id' => $document->id, 'version' => 7, 'content' => $home, 'source' => 'publish', 'created_by' => $this->admin()->id]);
        $this->forgetXelgaState();
        $restored = $this->actingAs($this->admin())->postJson(route('admin.xelga.revisions.restore', ['handle' => 'home', 'revision' => $revision->id]), ['draft_version' => 1])
            ->assertOk()->json('data');

        $this->assertSame('too-wide', $restored['adjusted'][0]['reason']);
        $this->assertSame('content.sections.hero-1', $restored['adjusted'][0]['path']);
        $this->assertSame($draft['rows'], $this->draft('home')['rows']);
    }

    public function test_a_revision_saved_before_rows_restores_unchanged(): void
    {
        $admin = $this->admin();
        $this->saveDraft($admin, 'home', $this->homeWithHeading('First heading'), 0)->assertOk();
        $this->publish($admin, ['home' => 1])->assertOk();
        $before = Revision::where('version', 1)->firstOrFail();
        $this->saveWithRows('home', $this->withTileRow($this->defaults('home'), 'hero-1'), 2)->assertOk();

        $this->forgetXelgaState();
        $this->actingAs($admin)->postJson(route('admin.xelga.revisions.restore', ['handle' => 'home', 'revision' => $before->id]), ['draft_version' => 3])
            ->assertOk()->assertJsonPath('data.adjusted', []);

        $draft = $this->draft('home');
        $this->assertSame($before->content['order'], $draft['order']);
        $this->assertSame($before->content['sections'], $draft['sections']);
        $this->assertArrayNotHasKey('rows', $draft);
        $this->assertArrayNotHasKey('sides', $draft);
    }

    public function test_a_placed_section_gets_its_own_row_after_its_anchor(): void
    {
        $this->product('Linen Shirt');
        $this->publishedDocument('product', $this->withTileRow(Defaults::content('product'), 'product-main-1'));
        $card = Placements::insertId('sample-extension:card');

        $html = $this->page('/product/linen-shirt')->getContent();

        $mainEnd = strpos($html, '<!--/xelga:product-main-1-->');
        $cardStart = strpos($html, '<!--xelga:' . $card . '-->');
        $tiles = strpos($html, '<!--xelga:tile-a-->');
        $this->assertNotFalse($mainEnd);
        $this->assertNotFalse($cardStart);
        $between = substr($html, $mainEnd + strlen('<!--/xelga:product-main-1-->'), $cardStart - $mainEnd - strlen('<!--/xelga:product-main-1-->'));
        $this->assertStringNotContainsString('<!--xelga:', $between, 'Nothing between the product information and the card.');
        $this->assertStringNotContainsString('xr__col', $between, 'The card is in no column.');
        $this->assertGreaterThan($cardStart, $tiles, 'The tiles come after the card.');
        // The tiles' row does draw its columns, so the check above can fail.
        $cardEnd = strpos($html, '<!--/xelga:' . $card . '-->');
        $this->assertStringNotContainsString('xr__col', substr($html, $cardStart, $cardEnd - $cardStart), 'The card is in no column.');
        $rowStart = strpos($html, '<!--xelga:row-tiles-->');
        $this->assertNotFalse($rowStart, 'The tiles draw in their row.');
        $this->assertGreaterThan($cardEnd, $rowStart);
        $this->assertLessThan($tiles, $rowStart);
        $this->assertSame(2, substr_count(substr($html, $rowStart, strpos($html, '<!--/xelga:row-tiles-->') - $rowStart), '<div class="xr__col" data-span="6">'));

        // The editor reads the card in a row of its own, right after the product information's row.
        $content = $this->actingAs($this->admin())->getJson(route('admin.xelga.documents.show', ['handle' => 'product']))->assertOk()->json('data.content');
        $ids = array_map(fn ($row) => $row['columns'][0][0] ?? null, $content['rows']);
        $at = array_search('product-main-1', $ids, true);
        $this->assertSame($card, $ids[$at + 1]);
        $this->assertSame([[$card]], $content['rows'][$at + 1]['columns']);
        $this->assertSame('row-tiles', $content['rows'][$at + 2]['id']);
    }

    public function test_a_save_from_an_editor_without_rows_is_refused_once_the_page_has_rows(): void
    {
        $this->saveWithRows('home', $this->withTileRow($this->defaults('home'), 'hero-1'), 0)->assertOk();
        $before = Document::where('handle', 'home')->firstOrFail();

        $this->forgetXelgaState();
        $flat = $this->homeWithHeading('From an editor that knows no rows');
        $this->saveDraft($this->admin(), 'home', $flat, 1)
            ->assertStatus(409)->assertJsonPath('code', 'editor-outdated');
        $this->actingAs($this->admin())->putJson(route('admin.xelga.documents.draft', ['handle' => 'home']), ['content' => $flat, 'draft_version' => 1, 'with_rows' => false])
            ->assertStatus(409)->assertJsonPath('code', 'editor-outdated');

        $after = Document::where('handle', 'home')->firstOrFail();
        $this->assertSame($before->draft, $after->draft);
        $this->assertSame(1, (int) $after->draft_version);

        // Published rows count too, once the draft is gone.
        $this->publish($this->admin(), ['home' => 1])->assertOk();
        $this->forgetXelgaState();
        $this->saveDraft($this->admin(), 'home', $flat, 2)->assertStatus(409)->assertJsonPath('code', 'editor-outdated');

        // A page without rows still takes saves from any editor.
        $this->forgetXelgaState();
        $this->saveDraft($this->admin(), 'footer', $this->defaults('footer'), 0)->assertOk();
    }

    public function test_duplicate_puts_the_copy_under_its_original_in_the_same_column(): void
    {
        $home = $this->withTileRow($this->defaults('home'), 'hero-1');
        $home['sections']['tile-c'] = $this->tile('Tile C');
        $home['order'][] = 'tile-c';
        $this->saveWithRows('home', $home, 0)->assertOk();

        $duplicate = function (string $section, int $version): string {
            $this->forgetXelgaState();

            return (string) $this->actingAs($this->admin())->postJson(route('admin.xelga.documents.duplicate', ['handle' => 'home']), ['section' => $section, 'draft_version' => $version])
                ->assertOk()->json('data.new_id');
        };

        // In a column: right under the original, in that column.
        $copy = $duplicate('tile-a', 1);
        $draft = $this->draft('home');
        $this->assertSame([['tile-a', $copy], ['tile-b']], $this->rowOf($draft, 'tile-a')['columns']);
        $this->assertSame(['hero-1', 'tile-a', $copy, 'tile-b'], array_slice($draft['order'], 0, 4));

        // In a one-column row: a row of its own, right after.
        $copyC = $duplicate('tile-c', 2);
        $draft = $this->draft('home');
        $rows = array_column($draft['rows'], 'columns');
        $at = array_search([['tile-c']], $rows, true);
        $this->assertSame([[$copyC]], $rows[$at + 1]);

        // A full column: the copy gets a row of its own, after the original's row.
        $version = 3;
        while (count($this->rowOf($this->draft('home'), 'tile-a')['columns'][0]) < Layout::MAX_PER_COLUMN) {
            $duplicate('tile-a', $version++);
        }
        $last = $duplicate('tile-a', $version);
        $draft = $this->draft('home');
        $row = $this->rowOf($draft, 'tile-a');
        $this->assertCount(Layout::MAX_PER_COLUMN, $row['columns'][0]);
        $at = array_search($row['id'], array_column($draft['rows'], 'id'), true);
        $this->assertSame([[$last]], $draft['rows'][$at + 1]['columns']);
    }

    /**
     * The home page laid out in exactly Layout::MAX_ROWS rows: $first,
     * then one plain row per remaining home section, then $extra tiles in
     * plain rows, then empty 6-6 rows to fill up to the limit.
     */
    private function homeAtRowLimit(array $first, array $inFirst, int $extra = 0): array
    {
        $home = $this->defaults('home');
        $rows = [$first];
        foreach ($home['order'] as $id) {
            if (! in_array($id, $inFirst, true)) {
                $rows[] = ['split' => '12', 'columns' => [[$id]], 'settings' => []];
            }
        }
        foreach ($inFirst as $id) {
            if (! isset($home['sections'][$id])) {
                $home['sections'][$id] = $this->tile();
                $home['order'][] = $id;
            }
        }
        for ($i = 1; $i <= $extra; $i++) {
            $home['sections']["extra-{$i}"] = $this->tile();
            $home['order'][] = "extra-{$i}";
            $rows[] = ['split' => '12', 'columns' => [["extra-{$i}"]], 'settings' => []];
        }
        while (count($rows) < Layout::MAX_ROWS) {
            $rows[] = ['split' => '6-6', 'columns' => [[], []], 'settings' => []];
        }
        $home['rows'] = $rows;

        return $home;
    }

    /** Open the page in the editor and save it back unchanged. */
    private function resave(string $handle): array
    {
        $this->forgetXelgaState();
        $state = $this->actingAs($this->admin())->getJson(route('admin.xelga.documents.show', ['handle' => $handle]))->assertOk()->json('data');
        $this->assertLessThanOrEqual(Layout::MAX_ROWS, count($state['content']['rows'] ?? []));

        return $this->saveWithRows($handle, json_decode(json_encode($state['content']), true), (int) $state['draft_version'])->assertOk()->json('data');
    }

    public function test_a_page_at_the_row_limit_with_a_misfit_saves_and_saves_again(): void
    {
        // The banner is too wide for half a row; moving it out must not take the page over the limit.
        $home = $this->homeAtRowLimit(['id' => 'row-top', 'split' => '6-6', 'columns' => [['hero-1'], []], 'settings' => []], ['hero-1']);
        $this->assertCount(Layout::MAX_ROWS, $home['rows']);

        $this->saveWithRows('home', $home, 0)->assertOk()->assertJsonPath('data.adjusted.0.reason', 'too-wide');

        $draft = $this->draft('home');
        $this->assertCount(Layout::MAX_ROWS, $draft['rows']);
        $this->assertNotContains('row-top', array_column($draft['rows'], 'id'), 'The row the banner left empty goes first.');
        $this->assertSame([['hero-1']], $draft['rows'][0]['columns']);
        $this->assertSame([[], []], end($draft['rows'])['columns'], 'Empty rows the merchant made stay.');

        $this->assertSame([], $this->resave('home')['adjusted']);
        $this->assertSame($draft['rows'], $this->draft('home')['rows']);
    }

    public function test_a_duplicate_into_a_full_column_at_the_row_limit_saves(): void
    {
        $tiles = ['tile-1', 'tile-2', 'tile-3', 'tile-4', 'tile-5', 'tile-6'];
        $home = $this->homeAtRowLimit(['id' => 'row-tiles', 'split' => '6-6', 'columns' => [$tiles, []], 'settings' => []], $tiles);
        $this->saveWithRows('home', $home, 0)->assertOk();
        $this->assertCount(Layout::MAX_ROWS, $this->draft('home')['rows']);

        $this->forgetXelgaState();
        $copy = $this->actingAs($this->admin())->postJson(route('admin.xelga.documents.duplicate', ['handle' => 'home']), ['section' => 'tile-1', 'draft_version' => 1])
            ->assertOk()->json('data.new_id');

        $draft = $this->draft('home');
        $this->assertCount(Layout::MAX_ROWS, $draft['rows']);
        $this->assertSame([[$copy]], $draft['rows'][1]['columns'], 'Its own row, right after the full column\'s row.');
        $this->resave('home');
    }

    public function test_a_page_at_the_row_limit_still_reads_and_saves_after_a_span_is_raised(): void
    {
        $home = $this->homeAtRowLimit(['id' => 'row-tiles', 'split' => '4-4-4', 'columns' => [['tile-a'], [], []], 'settings' => []], ['tile-a'], 20);
        $this->saveWithRows('home', $home, 0)->assertOk()->assertJsonPath('data.adjusted', []);
        $this->publish($this->admin(), ['home' => 1])->assertOk();

        // A theme update makes the tile need half the page.
        $file = base_path('tests/fixtures/extensions/sample-extension/resources/xelga/schemas/sections/sample-extension.tile.json');
        $original = (string) file_get_contents($file);
        $schema = json_decode($original, true);
        $schema['span'] = ['min' => 6];
        file_put_contents($file, json_encode($schema));
        try {
            $this->forgetXelgaState();
            $this->page('/')->assertSee('data-sample-tile', false);
            $this->resave('home');
            $draft = $this->draft('home');
            $this->assertCount(Layout::MAX_ROWS, $draft['rows']);
            $this->assertSame([['tile-a']], $draft['rows'][0]['columns']);
        } finally {
            file_put_contents($file, $original);
            $this->forgetXelgaState();
        }
    }

    public function test_a_custom_template_copy_keeps_rows_and_drops_left_out_sections(): void
    {
        $product = $this->withTileRow(Defaults::content('product'), 'product-main-1');
        // A section the theme no longer has stays on the page it is on, but never goes into a copy.
        $product['sections']['gone-1'] = ['type' => 'retired-section', 'label' => 'Retired', 'disabled' => true, 'settings' => [], 'block_order' => [], 'blocks' => []];
        array_splice($product['order'], array_search('tile-b', $product['order'], true) + 1, 0, ['gone-1']);
        $product['rows'][0]['columns'][1][] = 'gone-1';
        $product['rows'][0]['settings'] = ['width' => 'full'];
        $this->saveWithRows('product', $product, 0)->assertOk();
        $this->assertSame([['tile-a'], ['tile-b', 'gone-1']], $this->rowOf($this->draft('product'), 'gone-1')['columns']);

        $this->forgetXelgaState();
        $this->actingAs($this->admin())->postJson(route('admin.xelga.templates.store'), ['page_type' => 'product', 'label' => 'Gift guide'])
            ->assertCreated()->assertJsonPath('data.left_out', ['Retired']);

        $copy = $this->draft('product.gift-guide');
        $this->assertSame(['id' => 'row-tiles', 'split' => '6-6', 'columns' => [['tile-a'], ['tile-b']], 'settings' => ['width' => 'full'] + Layout::ROW_DEFAULTS], $this->rowOf($copy, 'tile-a'));
        $this->assertNotContains('gone-1', $copy['order']);
        $this->assertArrayNotHasKey('gone-1', $copy['sections']);
        $this->assertSame(['product-main-1', 'tile-a', 'tile-b'], array_slice($copy['order'], array_search('product-main-1', $copy['order'], true), 3));
    }

    public function test_a_normalizer_failure_draws_the_page_flat(): void
    {
        $this->publishedDocument('home', $this->withTileRow($this->defaults('home'), 'hero-1'));
        $this->app->bind(LayoutRules::class, fn () => new class implements LayoutRules
        {
            public function minSpan(array $section): int
            {
                throw new \RuntimeException('The layout rules are unavailable.');
            }

            public function isMain(array $section): bool
            {
                throw new \RuntimeException('The layout rules are unavailable.');
            }

            public function sidesAllowed(): bool
            {
                throw new \RuntimeException('The layout rules are unavailable.');
            }
        });
        Exceptions::fake();

        $html = $this->page('/')->getContent();

        $positions = array_map(fn ($id) => strpos($html, "<!--xelga:{$id}-->"), ['hero-1', 'tile-a', 'tile-b', 'usp-1']);
        $this->assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions, 'Drawn in the stored order.');
        Exceptions::assertReportedCount(1);
        Exceptions::assertReported(fn (\RuntimeException $e) => $e->getMessage() === 'The layout rules are unavailable.');
    }
}
