<?php

namespace App\Models;

use App\Support\ColumnGrid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * One row inside a region: columns on its theme's grid (eight parts unless
 * the theme declares another, see App\Support\ColumnGrid), each holding
 * placements in order.
 *
 * Rows are the merchant's arrangement and belong to a page type of one theme,
 * like placements. A placement with no row is an implicit one-column row and
 * renders on its own, so a store that predates rows renders exactly as it did.
 */
class LayoutRow extends Model
{
    /**
     * The default grid: eight parts, the same grid as the page's rails. A
     * theme may put its rows on another one (App\Support\ColumnGrid).
     */
    public const GRID = ColumnGrid::DEFAULT_UNITS;

    public const MAX_COLUMNS = ColumnGrid::DEFAULT_MAX_COLUMNS;

    /**
     * The column layouts core's editor offers, in eighths. A merchant picks
     * a shape, not numbers; anything else that sums to eight is still
     * accepted from an import.
     *
     * @var list<list<int>>
     */
    public const LAYOUTS = ColumnGrid::DEFAULT_SHAPES;

    protected $fillable = ['theme', 'page_type', 'region', 'sort_order', 'columns', 'style', 'responsive', 'is_enabled'];

    /**
     * A row's columns on smaller screens, on the given grid (the default
     * eight parts when none is given). See ColumnGrid::normalizeResponsive().
     *
     * @return array{tablet: list<int|string|null>, phone: list<int|string|null>, tablet_order: list<int>, phone_order: list<int>}
     */
    public static function normalizeResponsive(mixed $in, int $count, ?ColumnGrid $grid = null): array
    {
        return ($grid ?? ColumnGrid::default())->normalizeResponsive($in, $count);
    }

    /** Whether any column departs from the default on a smaller screen. */
    public static function isResponsive(?array $responsive, int $count, ?ColumnGrid $grid = null): bool
    {
        return ($grid ?? ColumnGrid::default())->isResponsive($responsive, $count);
    }

    protected $casts = [
        'columns' => 'array',
        'style' => 'array',
        'responsive' => 'array',
        'is_enabled' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function placements(): HasMany
    {
        return $this->hasMany(LayoutPlacement::class, 'row_id')
            ->orderBy('column_index')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Widths a row may carry on the given grid (the default eight parts when
     * none is given): on the default, one to six whole numbers, each at least
     * one, summing to eight. Anything else collapses to one full-width column
     * rather than rendering a grid nothing adds up in.
     *
     * @return list<int|string>
     */
    public static function normalizeColumns(mixed $columns, ?ColumnGrid $grid = null): array
    {
        return ($grid ?? ColumnGrid::default())->normalizeColumns($columns);
    }

    /** Does this row have more than one column to draw? */
    public function isSplit(): bool
    {
        return count($this->columns ?? []) > 1;
    }

    /**
     * Everything that renders in one region of one page type, in order, as
     * units: an explicit row with its placements grouped by column, or a
     * placement standing on its own. Rows placed on every page render on
     * every page, merged by sort order with the page's own, the same rule
     * placements always had.
     *
     * @return Collection<int, array{row: ?LayoutRow, columns: list<list<LayoutPlacement>>}>
     */
    public static function forRegion(?string $pageType, string $region, ?string $theme = null): Collection
    {
        $theme = LayoutPlacement::scopeTheme($theme);

        // Rows and placements answer to the same chain: every page's, plus
        // the most specific key with anything in this region.
        $chain = \App\Support\LayoutPage::chain($pageType);
        $allRows = static::query()
            ->where('theme', $theme)
            ->where('is_enabled', true)
            ->where('region', $region)
            ->whereIn('page_type', $chain)
            ->get();
        $allPlacements = LayoutPlacement::query()
            ->where('theme', $theme)
            ->where('is_enabled', true)
            ->where('region', $region)
            ->whereIn('page_type', $chain)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $own = \App\Support\LayoutPage::pick($chain, $allRows->pluck('page_type')->merge($allPlacements->pluck('page_type'))->unique()->all());
        $keep = fn ($m) => $m->page_type === LayoutPlacement::EVERYWHERE || $m->page_type === $own;

        $placements = $allPlacements->filter($keep)->values();
        $rows = $allRows->filter($keep)->keyBy('id');

        $units = [];

        // A placement whose row is disabled or gone renders nothing here: the
        // row is the merchant's switch for the whole strip.
        $byRow = $placements->groupBy(fn ($p) => $p->row_id ? (string) $p->row_id : '');

        foreach ($byRow->get('', collect()) as $placement) {
            $units[] = [
                'row' => null,
                'sort' => [$placement->page_type === LayoutPlacement::EVERYWHERE ? 0 : 1, $placement->sort_order, $placement->id],
                'columns' => [[$placement]],
            ];
        }

        $grid = ColumnGrid::forTheme($theme);
        foreach ($rows as $row) {
            $widths = static::normalizeColumns($row->columns, $grid);
            $columns = array_fill(0, count($widths), []);

            foreach ($byRow->get((string) $row->id, collect()) as $placement) {
                $index = max(0, min((int) $placement->column_index, count($widths) - 1));
                $columns[$index][] = $placement;
            }

            $units[] = [
                'row' => $row,
                'sort' => [$row->page_type === LayoutPlacement::EVERYWHERE ? 0 : 1, $row->sort_order, $row->id],
                'columns' => $columns,
            ];
        }

        // Sort order is the merchant's order inside each scope; everywhere
        // units come first within a tie, as inherited placements always did
        // through forRegion()'s ordering.
        usort($units, fn ($a, $b) => [$a['sort'][1], $a['sort'][0], $a['sort'][2]] <=> [$b['sort'][1], $b['sort'][0], $b['sort'][2]]);

        return collect(array_map(fn ($u) => ['row' => $u['row'], 'columns' => $u['columns']], $units));
    }
}
