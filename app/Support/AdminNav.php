<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

class AdminNav
{
    /**
     * Groups with items the given user may see. Null user = no filtering.
     *
     * Also drops any item whose named route isn't currently registered.
     * Every core-controller route always exists, so this is a no-op for
     * them — it only matters for the handful of reserved slots (e.g.
     * Fulfillment) that point at an extension-provided route name: that
     * route only exists while the extension is installed AND enabled
     * (ExtensionManager/BootExtensions). Without this guard, disabling the
     * extension from the Modules page — or a fresh install that never
     * installed it at all — would make x-admin.shell's sidebar (rendered on
     * EVERY admin page) throw RouteNotFoundException calling route() on a
     * name nothing registered, taking down the entire admin panel rather
     * than just hiding one link.
     */
    public static function groups(?User $user = null): array
    {
        return collect(config('admin-nav'))
            ->map(function (array $group) use ($user) {
                $group['items'] = array_values(array_filter(
                    $group['items'],
                    fn (array $item) => (
                        $item['permission'] === null
                        || ($user?->hasPermission($item['permission']) ?? true)
                    ) && Route::has($item['route'])
                ));

                return $group;
            })
            ->filter(fn (array $group) => $group['items'] !== [])
            ->values()
            ->all();
    }

    /** The nav item matching the current request path, for breadcrumbs/titles. */
    public static function current(): ?array
    {
        $active = static::activeItemKey(request()->path());

        if (! $active || $active['item'] === null) {
            return null;
        }

        foreach (config('admin-nav') as $group) {
            foreach ($group['items'] as $item) {
                if (($item['key'] ?? null) === $active['item']) {
                    return $item + ['group' => $group['label']];
                }
            }
        }

        return null;
    }

    /**
     * The group label + item key matching the given request path, by
     * longest-URL-prefix match against the registry (Task 7 — replaces the
     * old routeIs('pattern') matching). Every settings/* path is represented
     * by the single 'Settings' item regardless of depth — SettingsNav owns
     * finer-grained active-state within that sub-tree (see
     * SettingsNav::activeKeyForPath()).
     *
     * @return array{group: ?string, item: ?string}|null
     */
    public static function activeItemKey(string $path): ?array
    {
        $segments = Breadcrumbs::segments($path);

        if ($segments === []) {
            return null;
        }

        $root = $segments[0];

        if ($root === 'settings') {
            foreach (config('admin-nav') as $group) {
                foreach ($group['items'] as $item) {
                    if ($item['route'] === 'admin.settings.index') {
                        return ['group' => $group['label'], 'item' => $item['key']];
                    }
                }
            }

            return null;
        }

        foreach (config('admin-nav') as $group) {
            if ($group['label'] === null) {
                foreach ($group['items'] as $item) {
                    if (($item['key'] ?? null) === $root) {
                        return ['group' => null, 'item' => $item['key']];
                    }
                }

                continue;
            }

            if (($group['segment'] ?? null) !== $root) {
                continue;
            }

            $entityKey = $segments[1] ?? null;

            foreach ($group['items'] as $item) {
                if (($item['key'] ?? null) === $entityKey) {
                    return ['group' => $group['label'], 'item' => $item['key']];
                }
            }

            return ['group' => $group['label'], 'item' => null];
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
