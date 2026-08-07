<?php

namespace App\Support;

use App\Models\User;

class SettingsNav
{
    /** Groups with items the given user may see. Null user = no filtering. */
    public static function groups(?User $user = null): array
    {
        return collect(config('settings-nav'))
            ->map(function (array $group) use ($user) {
                $group['items'] = array_values(array_filter(
                    $group['items'],
                    fn (array $item) => $item['permission'] === null
                        || ($user?->hasPermission($item['permission']) ?? true)
                ));

                return $group;
            })
            ->filter(fn (array $group) => $group['items'] !== [])
            ->values()
            ->all();
    }

    /** Item by key, with its group label attached. */
    public static function find(string $key): ?array
    {
        foreach (config('settings-nav') as $group) {
            foreach ($group['items'] as $item) {
                if ($item['key'] === $key) {
                    return $item + ['group' => $group['label']];
                }
            }
        }

        return null;
    }

    /** Resolve an item's URL (section pages carry a route parameter). */
    public static function url(array $item): string
    {
        return $item['section'] !== null
            ? route($item['route'], $item['section'])
            : route($item['route']);
    }

    /**
     * The settings-nav item key that should highlight as active for a given
     * request path, by longest-URL-prefix match (Task 7). Item keys already
     * equal their URL segment (settings/{key}) for ordinary items; items
     * mounted under the extension-kernel convention (settings/modules/{key}/…)
     * match on that 3-segment prefix instead of their own — often deeper —
     * resolved URL, so any page anywhere under a module highlights
     * consistently (e.g. settings/modules/abandoned-carts/rules still
     * highlights 'abandoned-carts', even though that item's own URL is
     * .../abandoned-carts/settings). See Breadcrumbs, which shares this
     * convention for the breadcrumb trail.
     */
    public static function activeKeyForPath(string $path): ?string
    {
        $segments = Breadcrumbs::segments($path);

        if (($segments[0] ?? null) !== 'settings') {
            return null;
        }

        $entitySegment = $segments[1] ?? null;

        if ($entitySegment === 'modules' && isset($segments[2])) {
            return $segments[2];
        }

        return $entitySegment;
    }

    /** Flat entries for the command palette, namespaced under "Settings". */
    public static function paletteEntries(?User $user = null): array
    {
        $entries = [];
        foreach (static::groups($user) as $group) {
            foreach ($group['items'] as $item) {
                $entries[] = [
                    'label' => 'Settings: ' . $item['label'],
                    'url' => static::url($item),
                    'group' => 'Settings',
                    'keywords' => $item['keywords'],
                ];
            }
        }

        return $entries;
    }
}
