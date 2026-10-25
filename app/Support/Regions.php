<?php

namespace App\Support;

use App\Services\ThemeService;

/**
 * The slots the ACTIVE THEME offers, declared in its theme.json.
 *
 * Core deliberately holds no list of region names. A theme that wants a
 * "faceplate" band across the top declares one and it becomes placeable, and
 * core never learns what a faceplate is. This is the line that keeps layout
 * portable while leaving looks entirely to the theme.
 *
 * A theme that declares nothing has no regions, and the layout editor has
 * nothing to offer. That is correct, and the editor says so rather than
 * showing an empty screen and letting a merchant wonder what broke.
 */
class Regions
{
    /** @return array<string, array{label:string, description?:string}> */
    public static function all(?string $theme = null): array
    {
        $path = app(ThemeService::class)->getThemePath($theme) . '/theme.json';
        $meta = json_decode((string) @file_get_contents($path), true) ?: [];
        $regions = $meta['regions'] ?? [];

        $out = [];
        foreach ($regions as $key => $spec) {
            $out[$key] = is_array($spec)
                ? $spec + ['label' => static::humanise($key)]
                : ['label' => (string) $spec];
        }

        return $out;
    }

    public static function exists(string $region, ?string $theme = null): bool
    {
        return array_key_exists($region, static::all($theme));
    }

    public static function label(string $region, ?string $theme = null): string
    {
        return static::all($theme)[$region]['label'] ?? static::humanise($region);
    }

    /**
     * What a region shows until the merchant arranges it: the theme's own
     * pieces, declared in theme.json as widget keys (or {widget, config}).
     * A region with defaults is a system region: the page's fixed parts
     * became rows the merchant may reorder, keep, or replace.
     *
     * Every piece, in order, whatever rows defaultRows() groups them into.
     *
     * @return list<array{widget: string, title: ?string, config: array}>
     */
    public static function defaults(string $region, ?string $theme = null): array
    {
        $out = [];
        foreach (static::defaultRows($region, $theme) as $row) {
            foreach ($row['cols'] as $pieces) {
                array_push($out, ...$pieces);
            }
        }

        return $out;
    }

    /**
     * The shipped arrangement as rows, the shape the storefront draws and
     * the editor edits.
     *
     * A defaults entry may be a row of its own, {columns: [4, 4], cols:
     * [[...], [...]]}, so a page whose pieces sit side by side (a gallery
     * beside the price) ships that way. Consecutive single entries share one
     * full-width row, which is how a theme that lists plain keys has always
     * been drawn. A row whose widths do not add up collapses to one column,
     * the same rule a saved row follows.
     *
     * @return list<array{columns: list<int>, responsive: ?array, cols: list<list<array{widget: string, title: ?string, config: array}>>}>
     */
    public static function defaultRows(string $region, ?string $theme = null): array
    {
        $rows = [];
        $loose = [];
        $grid = ColumnGrid::forTheme($theme);
        $flush = function () use (&$rows, &$loose, $grid) {
            if ($loose !== []) {
                $rows[] = ['columns' => $grid->full(), 'responsive' => null, 'cols' => [$loose]];
                $loose = [];
            }
        };

        foreach ((array) (static::all($theme)[$region]['defaults'] ?? []) as $entry) {
            if (is_array($entry) && isset($entry['cols']) && is_array($entry['cols'])) {
                $flush();
                $cols = array_values(array_map(
                    fn ($pieces) => array_values(array_filter(array_map([static::class, 'piece'], (array) $pieces))),
                    $entry['cols']
                ));
                $widths = $grid->normalizeColumns($entry['columns'] ?? []);
                if (count($widths) !== count($cols)) {
                    $widths = $grid->full();
                    $cols = [array_merge(...($cols ?: [[]]))];
                }
                $rows[] = ['columns' => $widths, 'responsive' => is_array($entry['responsive'] ?? null) ? $entry['responsive'] : null, 'cols' => $cols];
            } elseif ($piece = static::piece($entry)) {
                $loose[] = $piece;
            }
        }
        $flush();

        return $rows;
    }

    /** One shipped piece: a widget key, or {widget, title, config}. */
    private static function piece(mixed $entry): ?array
    {
        if (is_string($entry) && $entry !== '') {
            return ['widget' => $entry, 'title' => null, 'config' => []];
        }
        if (is_array($entry) && ! empty($entry['widget'])) {
            return ['widget' => (string) $entry['widget'], 'title' => $entry['title'] ?? null, 'config' => (array) ($entry['config'] ?? [])];
        }

        return null;
    }

    /**
     * Whether a page key shows a region's shipped pieces when it has no
     * rows of its own there: the region belongs to the key's page type and
     * nothing up the key's chain (a category's level, all categories) has
     * been arranged, since that arrangement is what the page inherits.
     */
    public static function shipsOn(string $region, string $pageKey, ?string $theme = null): bool
    {
        if (static::defaultRows($region, $theme) === [] || static::page($region, $theme) !== LayoutPage::type($pageKey)) {
            return false;
        }

        $theme = \App\Models\LayoutPlacement::scopeTheme($theme);
        $up = array_values(array_diff(array_slice(LayoutPage::chain($pageKey), 1), [\App\Models\LayoutPlacement::EVERYWHERE]));
        if ($up === []) {
            return true;
        }

        return ! \App\Models\LayoutPlacement::where('theme', $theme)->where('region', $region)->whereIn('page_type', $up)->exists()
            && ! \App\Models\LayoutRow::where('theme', $theme)->where('region', $region)->whereIn('page_type', $up)->exists();
    }

    /** The page type a region belongs to ('*' when it renders everywhere). */
    public static function page(string $region, ?string $theme = null): string
    {
        return (string) (static::all($theme)[$region]['page'] ?? '*');
    }

    /**
     * Placements pointing at regions this theme does not have.
     *
     * Surfaced, never deleted. Switching theme must not silently destroy a
     * merchant's layout, or they will never switch again.
     */
    public static function orphans(?string $theme = null)
    {
        $theme = $theme ?: app(ThemeService::class)->getActiveTheme();
        $known = array_keys(static::all($theme));

        // Scoped to the theme, like the layout itself. An unscoped query
        // reported another theme's placements as orphans of this one, which is
        // the opposite of true: they are fine, they are simply not here.
        return \App\Models\LayoutPlacement::query()
            ->where('theme', $theme)
            ->when($known !== [], fn ($q) => $q->whereNotIn('region', $known))
            ->orderBy('page_type')
            ->orderBy('sort_order')
            ->get();
    }

    private static function humanise(string $key): string
    {
        return ucfirst(str_replace('_', ' ', $key));
    }
}
