<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

class AdminNav
{
    /** Groups with items the given user may see. Null user = no filtering. */
    public static function groups(?User $user = null): array
    {
        return collect(config('admin-nav'))
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

    /** The nav item matching the current route, for breadcrumbs/titles. */
    public static function current(): ?array
    {
        foreach (config('admin-nav') as $group) {
            foreach ($group['items'] as $item) {
                if (Route::currentRouteName() && request()->routeIs($item['pattern'])) {
                    return $item + ['group' => $group['label']];
                }
            }
        }

        return null;
    }

    /** Flat destination list for the command palette. */
    public static function paletteEntries(?User $user = null): array
    {
        $entries = [];
        foreach (static::groups($user) as $group) {
            foreach ($group['items'] as $item) {
                $entries[] = [
                    'label' => $item['label'],
                    'url' => route($item['route']),
                    'group' => $group['label'] ?? 'General',
                    'keywords' => $item['keywords'],
                ];
            }
        }

        return $entries;
    }
}
