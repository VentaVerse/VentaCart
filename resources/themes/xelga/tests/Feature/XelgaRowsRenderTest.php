<?php

namespace Themes\Xelga\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesSampleExtension;
use Tests\TestCase;
use Themes\Xelga\Documents\Layout;
use Themes\Xelga\Documents\LayoutRules;
use Themes\Xelga\Rendering\Picture;
use Themes\Xelga\Rendering\RenderContext;
use Themes\Xelga\Rendering\SectionRenderer;
use Themes\Xelga\Rendering\Storefront;
use Themes\Xelga\Tests\XelgaExtensionHelpers;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';
require_once __DIR__ . '/../XelgaExtensionHelpers.php';

/**
 * Rows of columns as the shopper gets them: a one-column row draws its
 * section as before, a row of columns draws a grid whose columns carry
 * their width in twelfths, and what a section draws inside a column
 * (its photos' sizes) follows the column's width.
 */
class XelgaRowsRenderTest extends TestCase
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

    /** The home page with tiles in one row of the given split, right after the banner. */
    private function homeWithRow(string $split, array $columns, array $settings = []): array
    {
        $home = $this->defaults('home');
        $ids = array_merge(...$columns);
        $order = array_values($home['order']);
        array_splice($order, 1, 0, $ids);
        $home['order'] = $order;
        foreach ($ids as $id) {
            $home['sections'][$id] = $this->tile('Tile ' . $id);
        }
        $home['rows'] = [['id' => 'row-tiles', 'split' => $split, 'columns' => $columns, 'settings' => $settings]];

        return $home;
    }

    /** The HTML between a row's or section's markers. */
    private function cut(string $html, string $id): string
    {
        $open = "<!--xelga:{$id}-->";
        $start = strpos($html, $open);
        $this->assertNotFalse($start, "{$id} is drawn.");
        $end = strpos($html, "<!--/xelga:{$id}-->", $start);
        $this->assertNotFalse($end);

        return substr($html, $start + strlen($open), $end - $start - strlen($open));
    }

    /** @return list<string> the data-span of each column, in order */
    private function columnSpans(string $row): array
    {
        preg_match_all('#<div class="xr__col" data-span="(\d+)">#', $row, $m);

        return $m[1];
    }

    private function mainContent(string $url): string
    {
        $html = $this->page($url)->getContent();
        $start = strpos($html, '<main id="main-content">');
        $end = strrpos($html, '</main>');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        return (string) preg_replace('/name="_token" value="[^"]*"/', 'name="_token" value=""', substr($html, $start, $end - $start));
    }

    public function test_a_plain_row_draws_no_wrapper(): void
    {
        // The first visit makes the photos' smaller copies, which later visits list.
        $this->mainContent('/');
        $before = $this->mainContent('/');

        // Every section in a one-column row of its own, one of them with
        // settings, so the stored rows are kept and drawn.
        $home = $this->defaults('home');
        $home['rows'] = Layout::rows($home);
        $home['rows'][1]['settings'] = ['width' => 'full', 'scheme' => 'scheme-2', 'space' => 'lg'];
        $this->publishedDocument('home', $home);

        $after = $this->mainContent('/');
        $this->assertSame($before, $after);
        $this->assertStringNotContainsString('class="xr', $after);
    }

    public function test_a_row_draws_its_columns_with_spans(): void
    {
        $this->publishedDocument('home', $this->homeWithRow('6-6', [['tile-a'], ['tile-b']]));

        $html = $this->page('/')->getContent();
        $row = $this->cut($html, 'row-tiles');

        $this->assertStringStartsWith('<div class="xr" data-row-id="row-tiles" data-split="6-6" data-width="page" data-space="md"><div class="xr__inner">', $row);
        $this->assertSame(['6', '6'], $this->columnSpans($row));
        foreach (['tile-a', 'tile-b'] as $tile) {
            $this->assertMatchesRegularExpression('#data-section-id="' . $tile . '"[^>]*data-span="6"#', $this->cut($row, $tile));
        }
        // Each column holds its own tile, in order.
        $this->assertMatchesRegularExpression('#<div class="xr__col" data-span="6"><!--xelga:tile-a-->.*?<!--/xelga:tile-a--></div><div class="xr__col" data-span="6"><!--xelga:tile-b-->#s', $row);
        $this->assertStringEndsWith('</div></div></div>', $row);
        // The banner before the row and the promises after it draw outside it.
        $this->assertLessThan(strpos($html, '<!--xelga:row-tiles-->'), strpos($html, '<!--/xelga:hero-1-->'));
        $this->assertGreaterThan(strpos($html, '<!--/xelga:row-tiles-->'), strpos($html, '<!--xelga:usp-1-->'));
    }

    public function test_quarters_hold_span_three_sections(): void
    {
        $this->publishedDocument('home', $this->homeWithRow('3-3-3-3', [['t-1'], ['t-2'], ['t-3'], ['t-4']]));

        $row = $this->cut($this->page('/')->getContent(), 'row-tiles');

        $this->assertSame(['3', '3', '3', '3'], $this->columnSpans($row));
        foreach (['t-1', 't-2', 't-3', 't-4'] as $tile) {
            $this->assertStringContainsString('data-span="3"', $this->cut($row, $tile));
        }
    }

    public function test_an_empty_column_keeps_its_place(): void
    {
        $this->publishedDocument('home', $this->homeWithRow('3-6-3', [[], ['tile-b'], ['tile-c']]));

        $row = $this->cut($this->page('/')->getContent(), 'row-tiles');

        $this->assertSame(['3', '6', '3'], $this->columnSpans($row));
        $this->assertStringContainsString('<div class="xr__inner"><div class="xr__col" data-span="3"></div><div class="xr__col" data-span="6"><!--xelga:tile-b-->', $row);
    }

    public function test_hidden_sections_in_a_column_are_not_drawn_for_shoppers(): void
    {
        $home = $this->homeWithRow('6-6', [['tile-a', 'tile-hidden'], ['tile-b']]);
        $home['sections']['tile-hidden']['disabled'] = true;
        $this->publishedDocument('home', $home);

        $row = $this->cut($this->page('/')->getContent(), 'row-tiles');
        $this->assertStringNotContainsString('tile-hidden', $row);
        $this->assertSame(['6', '6'], $this->columnSpans($row));

        // A row whose sections are all hidden draws nothing at all.
        $home['sections']['tile-a']['disabled'] = true;
        $home['sections']['tile-b']['disabled'] = true;
        $this->publishedDocument('home', $home);
        $html = $this->page('/')->getContent();
        $this->assertStringNotContainsString('row-tiles', $html);
        $this->assertStringNotContainsString('class="xr', $html);
    }

    public function test_section_only_cuts_a_whole_row(): void
    {
        $home = $this->homeWithRow('6-6', [['tile-a'], ['tile-b']]);
        $this->saveWithRows('home', $home, 0)->assertOk();
        $nonce = $this->openEditor($this->admin());

        $row = $this->get('/?xelga_preview=' . $nonce . '&xelga_section=row-tiles')
            ->assertOk()->assertHeader('X-Xelga-Section', 'row-tiles')->getContent();
        $this->assertStringStartsWith('<div class="xr" data-row-id="row-tiles"', $row);
        $this->assertSame(['6', '6'], $this->columnSpans($row));
        $this->assertStringContainsString('data-section-id="tile-a"', $row);
        $this->assertStringContainsString('data-section-id="tile-b"', $row);
        $this->assertStringNotContainsString('data-section-id="hero-1"', $row);

        // A section in a column is still answered on its own.
        $tile = $this->get('/?xelga_preview=' . $nonce . '&xelga_section=tile-b')->assertOk()->getContent();
        $this->assertStringStartsWith('<section', ltrim($tile));
        $this->assertStringContainsString('data-span="6"', $tile);
    }

    public function test_images_in_a_column_are_sized_for_the_column(): void
    {
        $this->publishedDocument('home', $this->homeWithRow('4-8', [['tile-a'], ['tile-b']]));

        $row = $this->cut($this->page('/')->getContent(), 'row-tiles');

        $this->assertStringContainsString('sizes="' . e(Picture::spanSizes(4)) . '"', $this->cut($row, 'tile-a'));
        $this->assertStringContainsString('427px', $this->cut($row, 'tile-a'));
        $this->assertStringContainsString('854px', $this->cut($row, 'tile-b'));
        // A tile in a full-width row is sized for the page.
        $this->assertSame('(min-width: 1320px) 1280px, 100vw', Picture::spanSizes(12));
    }

    public function test_image_sizes_follow_the_grid_at_each_width(): void
    {
        $this->assertSame('(min-width: 1320px) 427px, (min-width: 990px) 34vw, (min-width: 750px) 50vw, 100vw', Picture::spanSizes(4));
        $this->assertSame('(min-width: 1320px) 854px, (min-width: 750px) 67vw, 100vw', Picture::spanSizes(8));
        $this->assertSame('(min-width: 1320px) 640px, (min-width: 750px) 50vw, 100vw', Picture::spanSizes(6));
        $this->assertSame('(min-width: 1320px) 320px, (min-width: 990px) 25vw, 50vw', Picture::spanSizes(3, '50vw'));

        // A product card's photo: unchanged on a full-width row, a share of the column in one.
        $this->assertSame('(min-width: 1320px) 320px, (min-width: 990px) 25vw, (min-width: 750px) 33vw, 50vw', Picture::cardSizes());
        $this->assertSame('(min-width: 1320px) 320px, (min-width: 990px) 25vw, (min-width: 750px) 33vw, 100vw', Picture::cardSizes(1, 4, 12));
        $this->assertSame('(min-width: 1320px) 214px, (min-width: 990px) 17vw, (min-width: 750px) 17vw, 50vw', Picture::cardSizes(2, 3, 6));
    }

    public function test_row_settings_reach_the_markup(): void
    {
        $this->publishedDocument('home', $this->homeWithRow('8-4', [['tile-a'], ['tile-b']], ['width' => 'full', 'scheme' => 'scheme-2', 'space' => 'lg']));

        $row = $this->cut($this->page('/')->getContent(), 'row-tiles');

        $this->assertStringStartsWith('<div class="xr scheme-2" data-row-id="row-tiles" data-split="8-4" data-width="full" data-space="lg">', $row);
        $this->assertSame(['8', '4'], $this->columnSpans($row));
    }

    public function test_drawn_types_leave_out_hidden_sections(): void
    {
        // As the storefront hands it over: rows read back with every section placed.
        $normalized = fn (array $content) => Layout::normalize($content, app(LayoutRules::class, ['handle' => 'home']))['content'];
        $home = $this->homeWithRow('6-6', [['tile-a'], ['tile-b']]);
        $context = new RenderContext('home', 'home', 'template', false);
        $renderer = app(SectionRenderer::class);

        $types = $renderer->drawnTypes($normalized($home), $context);
        $this->assertContains('hero', $types);
        $this->assertContains('sample-extension.tile', $types);
        $this->assertSame($types, array_values(array_unique($types)));
        $this->assertTrue($context->withTypes($types)->has('sample-extension.tile'));

        $home['sections']['tile-a']['disabled'] = true;
        $home['sections']['tile-b']['disabled'] = true;
        $home['sections']['gone-1'] = ['type' => 'no-such-section', 'disabled' => false, 'settings' => [], 'block_order' => [], 'blocks' => []];
        $home['order'][] = 'gone-1';
        $types = $renderer->drawnTypes($normalized($home), $context);
        $this->assertFalse($context->withTypes($types)->has('sample-extension.tile'));
        $this->assertNotContains('no-such-section', $types);
        $this->assertTrue($context->withTypes($types)->has('hero'));

        // Every section is told what else the page draws.
        $this->publishedDocument('home', $this->homeWithRow('6-6', [['tile-a'], ['tile-b']]));
        $this->assertStringContainsString('data-beside-banner', $this->cut($this->page('/')->getContent(), 'tile-a'));
    }

    public function test_a_column_span_survives_a_change_of_group(): void
    {
        $context = (new RenderContext('home', 'home', 'template', false))->withSpan(4)->withTypes(['hero']);

        $this->assertSame(4, $context->span);
        $this->assertSame(4, $context->withGroup('footer')->span);
        $this->assertTrue($context->withGroup('footer')->has('hero'));
        $this->assertSame(12, (new RenderContext('home', 'home', 'template', false))->span);
        $this->assertSame(12, $context->withSpan(40)->span);
        $this->assertSame(1, $context->withSpan(0)->span);
    }

    protected function tearDown(): void
    {
        Storefront::forgetRequestState();
        parent::tearDown();
    }
}
