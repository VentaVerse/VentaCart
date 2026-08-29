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
     * @return list<array{widget: string, title: ?string, config: array}>
     */
    public static function defaults(string $region, ?string $theme = null): array
    {
        $out = [];
        foreach ((array) (static::all($theme)[$region]['defaults'] ?? []) as $entry) {
            if (is_string($entry)) {
                $out[] = ['widget' => $entry, 'title' => null, 'config' => []];
            } elseif (is_array($entry) && ! empty($entry['widget'])) {
                $out[] = ['widget' => (string) $entry['widget'], 'title' => $entry['title'] ?? null, 'config' => (array) ($entry['config'] ?? [])];
            }
        }

        return $out;
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
