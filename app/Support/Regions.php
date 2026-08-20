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
     * Placements pointing at regions this theme does not have.
     *
     * Surfaced, never deleted. Switching theme must not silently destroy a
     * merchant's layout, or they will never switch again.
     */
    public static function orphans(?string $theme = null)
    {
        $known = array_keys(static::all($theme));

        return \App\Models\LayoutPlacement::query()
            ->when($known !== [], fn ($q) => $q->whereNotIn('region', $known))
            ->orderBy('page_type')
            ->get();
    }

    private static function humanise(string $key): string
    {
        return ucfirst(str_replace('_', ' ', $key));
    }
}
