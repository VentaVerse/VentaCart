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
