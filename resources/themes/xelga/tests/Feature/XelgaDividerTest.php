<?php

namespace Themes\Xelga\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesSampleExtension;
use Tests\TestCase;
use Themes\Xelga\Catalog\Catalog;
use Themes\Xelga\Catalog\CatalogLinter;
use Themes\Xelga\Tests\XelgaExtensionHelpers;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';
require_once __DIR__ . '/../XelgaExtensionHelpers.php';

/**
 * The Divider section: a line, a line with a label, dots or space. What a
 * shopper gets is markup; the spacing between a divider and its neighbors
 * is stylesheet work and is checked in the browser.
 */
class XelgaDividerTest extends TestCase
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

    private function divider(array $settings = []): array
    {
        return ['type' => 'divider', 'label' => '', 'disabled' => false, 'settings' => $settings, 'block_order' => [], 'blocks' => []];
    }

    /** The home page with a divider right after the banner, drawn on its own row or in a column. */
    private function homeWith(array $settings, bool $inColumn = false): string
    {
        $home = $this->defaults('home');
        $order = array_values($home['order']);
        $ids = $inColumn ? ['div-1', 'tile-b'] : ['div-1'];
        array_splice($order, 1, 0, $ids);
        $home['order'] = $order;
        $home['sections']['div-1'] = $this->divider($settings);
        if ($inColumn) {
            $home['sections']['tile-b'] = $this->tile('Tile B');
            $home['rows'] = [['id' => 'row-cols', 'split' => '6-6', 'columns' => [['div-1'], ['tile-b']], 'settings' => []]];
        }
        $this->publishedDocument('home', $home);

        $html = $this->page('/')->getContent();
        $start = strpos($html, '<!--xelga:div-1-->');
        $this->assertNotFalse($start, 'The divider is drawn.');

        return substr($html, $start, strpos($html, '<!--/xelga:div-1-->', $start) - $start);
    }

    public function test_it_draws_a_line_hidden_from_screen_readers(): void
    {
        $html = $this->homeWith(['variant' => 'line']);

        $this->assertStringContainsString('class="s s-divider scheme-1', $html);
        $this->assertStringContainsString('data-variant="line"', $html);
        $this->assertStringContainsString('data-width="page"', $html);
        $this->assertStringContainsString('data-pad="md"', $html);
        $this->assertStringContainsString('data-weight="thin"', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertSame(1, substr_count($html, 'dv__line'));
        $this->assertStringNotContainsString('dv__label', $html);
    }

    public function test_it_draws_dots_and_space_hidden_from_screen_readers(): void
    {
        $dots = $this->homeWith(['variant' => 'dots']);
        $this->assertStringContainsString('data-variant="dots"', $dots);
        $this->assertSame(3, substr_count($dots, 'class="dv__dot"'));
        $this->assertStringContainsString('aria-hidden="true"', $dots);
        $this->assertStringNotContainsString('dv__line', $dots);

        $space = $this->homeWith(['variant' => 'space', 'pad' => 'lg']);
        $this->assertStringContainsString('data-variant="space"', $space);
        $this->assertStringContainsString('data-pad="lg"', $space);
        $this->assertStringContainsString('aria-hidden="true"', $space);
        $this->assertStringNotContainsString('dv__line', $space);
        $this->assertStringNotContainsString('dv__dot', $space);
    }

    public function test_the_labeled_line_reads_its_label_and_hides_its_lines(): void
    {
        $html = $this->homeWith(['variant' => 'label']);

        $this->assertStringContainsString('<p class="dv__label" data-setting="label">Our picks</p>', $html);
        $this->assertSame(2, substr_count($html, '<span class="dv__line" aria-hidden="true"></span>'));
        $this->assertStringNotContainsString('<section class="s s-divider scheme-1" aria-hidden', $html);
        $this->assertDoesNotMatchRegularExpression('/<section[^>]*aria-hidden/', $html);
        $this->assertStringNotContainsString('<h2', $html, 'The label is plain text, not a heading.');
    }

    public function test_the_label_is_escaped_and_kept_to_forty_characters(): void
    {
        $html = $this->homeWith(['variant' => 'label', 'label' => '<script>alert(1)</script>']);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);

        $label = Catalog::section('divider')->settings()['label'];
        $sanitizer = new \Themes\Xelga\Settings\Sanitizer(\Themes\Xelga\Settings\SanitizeContext::forStore(['scheme-1']));
        $this->assertSame(str_repeat('a', 40), $sanitizer->clean($label, str_repeat('a', 60), 'label'));
    }

    public function test_width_and_thickness_reach_the_markup(): void
    {
        $html = $this->homeWith(['variant' => 'line', 'width' => 'edge', 'weight' => 'medium', 'pad' => 'sm']);

        $this->assertStringContainsString('data-width="edge"', $html);
        $this->assertStringContainsString('data-weight="medium"', $html);
        $this->assertStringContainsString('data-pad="sm"', $html);
    }

    public function test_it_draws_in_a_column(): void
    {
        $html = $this->homeWith(['variant' => 'line', 'width' => 'edge'], inColumn: true);

        $this->assertStringContainsString('s-divider', $html);
        $page = $this->page('/')->getContent();
        $this->assertMatchesRegularExpression('#<div class="xr__col" data-span="6"><!--xelga:div-1-->#', $page);
    }

    public function test_edge_to_edge_is_flagged_as_needing_a_row_of_its_own(): void
    {
        $width = collect(Catalog::section('divider')->raw['settings'])->firstWhere('key', 'width');
        $flagged = collect($width['options'])->filter(fn ($o) => ($o['own_row'] ?? false) === true)->pluck('value')->all();

        $this->assertSame(['edge'], $flagged);
        $this->assertSame(['narrow', 'page', 'edge'], array_column($width['options'], 'value'));
        $this->assertSame(['Narrow', 'Page', 'Edge to edge'], array_column($width['options'], 'label'));
    }

    public function test_a_column_or_side_keeps_edge_to_edge_inside_its_own_width(): void
    {
        $css = file_get_contents(Catalog::root('css/sections/divider.css'));

        $this->assertMatchesRegularExpression('/\.xr__col > \.s-divider \.dv \{ width: 100%; \}/', $css);
    }

    public function test_neighbors_drop_their_space_toward_a_divider(): void
    {
        $css = file_get_contents(Catalog::root('css/sections/divider.css'));

        $this->assertStringContainsString('> .s-divider + .s:not(', $css);
        $this->assertStringContainsString('padding-block-start: 0;', $css);
        $this->assertStringContainsString(':has(+ .s-divider)', $css);
        $this->assertStringContainsString('padding-block-end: 0;', $css);

        // The shared rule that merges two same-scheme sections' gap leaves a divider's own space alone.
        $shared = file_get_contents(Catalog::root('css/base/section.css'));
        $this->assertSame(10, substr_count($shared, '.s-divider'));
    }

    public function test_the_schema_offers_four_layouts_and_three_steps_of_space(): void
    {
        $raw = Catalog::section('divider')->raw;

        $this->assertSame(['line', 'label', 'dots', 'space'], array_column($raw['variants'], 'value'));
        $this->assertSame(3, $raw['span']['min']);
        $settings = collect($raw['settings'])->keyBy('key');
        $this->assertSame(['sm', 'md', 'lg'], array_column($settings['pad']['options'], 'value'));
        $this->assertSame('Our picks', $settings['label']['default']);
        $this->assertSame(40, $settings['label']['max']);
        $this->assertSame(['variant' => 'label'], $settings['label']['visible_if']);
        $this->assertTrue($settings['weight']['advanced']);
        $this->assertSame('text', $raw['picker_group']);
        $this->assertSame(['line', 'label', 'dots', 'space'], array_column($raw['presets'], 'key'));
        $this->assertSame([], CatalogLinter::check());
    }
}
