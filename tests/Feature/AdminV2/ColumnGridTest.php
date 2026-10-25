<?php

namespace Tests\Feature\AdminV2;

use App\Models\LayoutPlacement;
use App\Models\LayoutRow;
use App\Models\LayoutTemplate;
use App\Services\LayoutSaver;
use App\Support\ColumnGrid;
use App\Support\Widgets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesSampleTheme;
use Tests\TestCase;

/**
 * A theme that owns its rows may put them on its own grid: twelve parts
 * with fill and auto columns, or percentages that wrap. Core's own rows
 * stay on eight parts, stored and drawn exactly as before.
 */
class ColumnGridTest extends TestCase
{
    use RefreshDatabase;
    use UsesSampleTheme;

    private const TWELVE = ['units' => 12, 'fill' => true, 'auto' => true, 'max_columns' => 12, 'shapes' => [[12], [6, 6], [4, 4, 4], ['fill', 'auto']]];

    private const PERCENT = [
        'units' => 100,
        'widths' => [100, 95, 90, 85, 80, 75, 70, 66, 60, 55, 50, 45, 40, 33, 30, 25, 20, 15, 10, 5],
        'wrap' => true,
        'shares' => ['33' => 0.333333, '66' => 0.666667],
        'max_columns' => 20,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Widgets::register('test.marker', [
            'title' => 'Marker',
            'view' => 'widgets.test.marker',
            // Not drawn by any theme, so the sample installs without a view for it.
            'optional' => true,
            'schema' => ['note' => ['type' => 'text']],
        ]);
    }

    protected function tearDown(): void
    {
        Widgets::forget('test.marker');
        ColumnGrid::flush();
        parent::tearDown();
    }

    /** The eight-part rules exactly as they were written before grids existed. */
    private static function oldColumns(mixed $columns): array
    {
        $widths = array_values(array_map('intval', array_filter((array) $columns, 'is_numeric')));
        if ($widths === [] || count($widths) > 6 || min($widths) < 1 || array_sum($widths) !== 8) {
            return [8];
        }

        return $widths;
    }

    private static function oldResponsive(mixed $in, int $count): array
    {
        $in = is_array($in) ? $in : [];
        $count = max(1, $count);
        $out = ['tablet' => [], 'phone' => [], 'tablet_order' => [], 'phone_order' => []];
        for ($i = 0; $i < $count; $i++) {
            foreach (['tablet', 'phone'] as $device) {
                $w = $in[$device][$i] ?? null;
                $out[$device][] = is_numeric($w) && (int) $w >= 1 && (int) $w <= 8 ? (int) $w : null;
            }
            foreach (['tablet_order', 'phone_order'] as $key) {
                $o = $in[$key][$i] ?? ($i + 1);
                $out[$key][] = is_numeric($o) ? max(1, min($count, (int) $o)) : $i + 1;
            }
        }

        return $out;
    }

    /** The sample theme on a grid, its shipped product row redrawn on it. */
    private function manifest(array $grid, array $productRow): array
    {
        return ['grid' => $grid, 'regions' => ['product_main' => ['defaults' => [['columns' => $productRow]]]]];
    }

    private function rows(array $columns, array $responsive = []): array
    {
        return [['id' => null, 'region' => 'content_top', 'columns' => $columns, 'enabled' => true, 'responsive' => $responsive,
            'placements' => array_map(fn ($i) => ['id' => null, 'widget' => 'test.marker', 'column' => $i, 'enabled' => true, 'config' => ['note' => "col {$i}"]], array_keys($columns))]];
    }

    public function test_the_eight_part_rules_are_unchanged(): void
    {
        $inputs = [[8], [4, 4], [3, 5], [2, 2, 2, 2], ['4', '4'], [4.0, 4.9], [4, 'x', 4], [1, 1, 1, 1, 1, 3], [1, 1, 1, 1, 1, 1, 2], [9, -1], [0, 8], [], null, 'eight', [8, 0], ['fill', 8], [4, 'auto', 4], [3.5, 4.5]];
        foreach ($inputs as $in) {
            $this->assertSame(self::oldColumns($in), LayoutRow::normalizeColumns($in), json_encode($in));
            $this->assertSame(self::oldColumns($in), ColumnGrid::default()->normalizeColumns($in), json_encode($in));
        }

        $responsive = [null, [], ['tablet' => [4, 9, '2'], 'phone' => [0, 8], 'tablet_order' => [2, 1, 'x'], 'phone_order' => [5]], ['tablet' => ['fill', 'auto']]];
        foreach ($responsive as $in) {
            foreach ([1, 2, 3] as $count) {
                $this->assertSame(self::oldResponsive($in, $count), LayoutRow::normalizeResponsive($in, $count));
            }
        }

        $this->assertSame(8, LayoutRow::GRID);
        $this->assertSame(8, \App\Models\LayoutColumns::GRID);
        $this->assertSame(ColumnGrid::DEFAULT_SHAPES, LayoutRow::LAYOUTS);
        $this->assertTrue(ColumnGrid::forTheme('venta')->isDefault());
    }

    public function test_core_rows_round_trip_and_draw_as_before(): void
    {
        $responsive = ['tablet' => [8, null], 'phone' => [null, null], 'tablet_order' => [2, 1], 'phone_order' => [1, 2]];
        app(LayoutSaver::class)->save('home', ['rows' => $this->rows([3, 5], $responsive)]);

        $row = LayoutRow::firstOrFail();
        $this->assertSame([3, 5], $row->columns);
        $this->assertSame($responsive, $row->responsive);
        $this->assertSame(json_encode([3, 5]), $row->getRawOriginal('columns'));

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('class="layout-row layout-row--2"', $html);
        $this->assertStringContainsString('class="layout-col layout-col--3" style="--col-tablet: 8; --col-tablet-order: 2"', $html);
        $this->assertStringNotContainsString('layout-row--flex', $html);
        $this->assertStringNotContainsString('--col-flex', $html);
    }

    public function test_a_twelve_part_grid_with_fill_and_auto(): void
    {
        $this->installSampleTheme(activate: true, manifest: $this->manifest(self::TWELVE, [6, 6]));
        $grid = ColumnGrid::forTheme('sample');

        $this->assertFalse($grid->isDefault());
        $this->assertSame([6, 6], $grid->normalizeColumns([6, 6]));
        $this->assertSame([12], $grid->normalizeColumns([5, 6]), 'Numbers alone add up to twelve.');
        $this->assertSame([6, 'fill'], $grid->normalizeColumns([6, 'fill']), 'With a fill column the numbers may leave space.');
        $this->assertSame(['auto', 'fill', 'auto'], $grid->normalizeColumns(['auto', 'fill', 'auto']));
        $this->assertSame([12], $grid->normalizeColumns([8, 6, 'fill']), 'Even then they may not run past the row.');
        $this->assertSame([12], $grid->normalizeColumns([13]));
        $this->assertSame(['fill', 'auto'], $grid->shapes[3]);

        app(LayoutSaver::class)->save('home', ['rows' => $this->rows([6, 'fill', 'auto'], ['tablet' => [12, 'auto', 'fill'], 'phone' => [6, null, 'bogus']])]);

        $row = LayoutRow::where('theme', 'sample')->firstOrFail();
        $this->assertSame([6, 'fill', 'auto'], $row->columns);
        $this->assertSame([12, 'auto', 'fill'], $row->responsive['tablet']);
        $this->assertSame([6, null, null], $row->responsive['phone']);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('class="layout-row layout-row--flex layout-row--3"', $html);
        $this->assertStringContainsString('class="layout-col" style="--col-flex: 0 0 calc(50% - var(--layout-gap) * 0.5); --col-flex-tablet: 0 0 100%; --col-flex-phone: 0 0 calc(50% - var(--layout-gap) * 0.5)"', $html);
        $this->assertStringContainsString('class="layout-col layout-col--fill" style="--col-flex: 1 1 0%; --col-flex-tablet: 0 0 auto"', $html);
        $this->assertStringContainsString('class="layout-col layout-col--auto" style="--col-flex: 0 0 auto; --col-flex-tablet: 1 1 0%"', $html);
    }

    public function test_a_percent_grid_wraps_and_keeps_true_thirds(): void
    {
        $this->installSampleTheme(activate: true, manifest: $this->manifest(self::PERCENT, [50, 50]));
        $grid = ColumnGrid::forTheme('sample');

        $this->assertSame([66, 33], $grid->normalizeColumns([66, 33]));
        $this->assertSame([50, 50, 50], $grid->normalizeColumns([50, 50, 50]), 'Past one hundred, columns wrap.');
        $this->assertSame([100], $grid->normalizeColumns([37]), 'Only listed percentages.');
        $this->assertSame([100], $grid->normalizeColumns(['fill']), 'No fill on this grid.');
        $this->assertSame('0 0 calc(33.3333% - var(--layout-gap) * 0.6667)', $grid->flex(33));
        $this->assertSame('0 0 100%', $grid->flex(100));

        $rows = LayoutTemplate::cleanRows([['columns' => [50, 50, 50], 'placements' => [['widget' => 'test.marker', 'column' => 2]]]]);
        $this->assertSame([50, 50, 50], $rows[0]['columns']);
        $this->assertSame(2, $rows[0]['placements'][0]['column']);

        app(LayoutSaver::class)->save('home', ['rows' => $this->rows([66, 33, 50, 50])]);
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('style="--col-flex: 0 0 calc(66.6667% - var(--layout-gap) * 0.3333)"', $html);
        $this->assertSame(4, substr_count($html, 'class="layout-col"'));
    }

    public function test_a_grid_registered_from_a_provider_wins_and_can_be_passed_explicitly(): void
    {
        $this->installSampleTheme(activate: true, manifest: $this->manifest(self::PERCENT, [50, 50]));
        ColumnGrid::register('sample', ColumnGrid::make(self::TWELVE));

        $this->assertSame(12, ColumnGrid::forTheme('sample')->units);

        // A theme with its own editor can name the grid it saves on.
        $result = app(LayoutSaver::class)->save('home', ['rows' => $this->rows([4, 4, 4])], false, ColumnGrid::make(self::TWELVE));
        $this->assertCount(3, $result['kept']);
        $this->assertSame([4, 4, 4], LayoutRow::where('theme', 'sample')->firstOrFail()->columns);
        $this->assertSame([8], ColumnGrid::default()->normalizeColumns([4, 4, 4]));
    }

    public function test_the_contract_checks_shipped_rows_against_the_themes_grid(): void
    {
        $this->makeSampleTheme(array_replace_recursive($this->manifest(self::TWELVE, [6, 6]), ['regions' => ['content_top' => ['label' => 'Top', 'defaults' => [
            ['columns' => [6, 'fill'], 'cols' => [['test.marker'], ['test.marker']]],
        ]]]]));
        $problems = \App\Support\ThemeContract::check(resource_path('themes/sample'));
        $this->assertSame([], array_values(array_filter($problems, fn ($p) => str_contains($p, 'default row'))));

        $this->makeSampleTheme(['regions' => ['content_top' => ['label' => 'Top', 'defaults' => [
            ['columns' => [6, 'fill'], 'cols' => [['test.marker'], ['test.marker']]],
        ]]]]);
        $problems = \App\Support\ThemeContract::check(resource_path('themes/sample'));
        $this->assertNotEmpty(array_filter($problems, fn ($p) => str_contains($p, 'default row')));
    }
}
