<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\QueryException;
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
     *
     * Finally, an item may declare an optional 'enabled_setting' (see
     * settingAllows() below) and disappears while that setting is switched
     * off, the module-level "is this feature in use at all" answer, which
     * is a different question from whether its code is installed.
     */
    public static function groups(?User $user = null): array
    {
        $registry = config('admin-nav');
        $settings = static::settingValues($registry);

        return collect($registry)
            ->map(function (array $group) use ($user, $settings) {
                $group['items'] = array_values(array_filter(
                    $group['items'],
                    fn (array $item) => (
                        $item['permission'] === null
                        || ($user?->hasPermission($item['permission']) ?? true)
                    ) && Route::has($item['route'])
                        && static::settingAllows($item, $settings)
                ));

                return $group;
            })
            ->filter(fn (array $group) => $group['items'] !== [])
            ->values()
            ->all();
    }

    /**
     * groups() with the Settings hub item expanded into the full destination
     * list from SettingsNav, for the sidebar only. The palette must NOT use
     * this: it already merges SettingsNav::paletteEntries() and would list
     * every destination twice. Each injected item carries a resolved 'url'
     * because section pages need a route parameter plain route() can't supply.
     */
    public static function sidebarGroups(?User $user = null): array
    {
        $groups = static::groups($user);

        foreach ($groups as $gi => $group) {
            foreach ($group['items'] as $ii => $item) {
                if ($item['route'] !== 'admin.settings.index') {
                    continue;
                }

                $children = [];
                foreach (SettingsNav::groups($user) as $settingsGroup) {
                    foreach ($settingsGroup['items'] as $child) {
                        $children[] = [
                            'key' => $child['key'],
                            'label' => $child['label'],
                            'route' => $child['route'],
                            'icon' => $child['icon'],
                            'permission' => $child['permission'],
                            'keywords' => $child['keywords'],
                            'url' => SettingsNav::url($child),
                        ];
                    }
                }

                if ($children !== []) {
                    array_splice($groups[$gi]['items'], $ii, 1, $children);
                }

                return $groups;
            }
        }

        return $groups;
    }

    /**
     * The registry entry for $key IF it is currently reachable for this
     * user, otherwise null.
     *
     * "Reachable" is the whole composite answer groups() already computes:
     * the route is registered (so an extension that owns it is installed,
     * enabled and booted), the user holds the permission, and the merchant
     * has not switched the feature off. Anything outside the sidebar that
     * wants to offer a shortcut into a feature asks THIS rather than
     * assembling its own version of the test, so a feature that is hidden
     * from the menu is hidden everywhere (see the day sheet's fulfillment
     * work lines).
     *
     * @return array<string, mixed>|null
     */
    public static function visibleItem(string $key, ?User $user = null): ?array
    {
        foreach (static::groups($user) as $group) {
            foreach ($group['items'] as $item) {
                if ($item['key'] === $key) {
                    return $item;
                }
            }
        }

        return null;
    }

    /**
     * An item that declares
     *
     *     'enabled_setting' => ['group' => 'courier', 'key' => 'fulfillment_enabled']
     *
     * renders only while that setting is on. Semantics are opt-OUT to match
     * every toggle of this kind in the app: a missing row (never configured)
     * means on, and only an explicit '0' hides the entry. A feature the
     * merchant has never touched must appear, or a fresh install would ship
     * with an invisible menu.
     *
     * Declared as DATA, never a closure: config/admin-nav.php has to survive
     * `php artisan config:cache`, which serializes the array (a closure there
     * is a hard failure), and this keeps the registry the single source of
     * truth for the sidebar, breadcrumbs and command palette alike instead of
     * teaching core a list of feature-specific conditions.
     *
     * @param  array<string, string|null>  $settings  "group.key" => stored value
     */
    protected static function settingAllows(array $item, array $settings): bool
    {
        $declared = $item['enabled_setting'] ?? null;

        if (! is_array($declared) || ! isset($declared['group'], $declared['key'])) {
            return true;
        }

        return ($settings[$declared['group'] . '.' . $declared['key']] ?? null) !== '0';
    }

    /**
     * One query for every 'enabled_setting' the registry declares (today:
     * one), rather than a query per item.
     *
     * @param  array<int, array<string, mixed>>  $registry
     * @return array<string, string|null>
     */
    protected static function settingValues(array $registry): array
    {
        $declared = [];
        foreach ($registry as $group) {
            foreach ($group['items'] as $item) {
                $setting = $item['enabled_setting'] ?? null;
                if (is_array($setting) && isset($setting['group'], $setting['key'])) {
                    $declared[$setting['group'] . '.' . $setting['key']] = $setting;
                }
            }
        }

        if ($declared === []) {
            return [];
        }

        try {
            $rows = Setting::query()
                ->where(function ($query) use ($declared) {
                    foreach ($declared as $setting) {
                        $query->orWhere(fn ($q) => $q
                            ->where('group', $setting['group'])
                            ->where('key', $setting['key']));
                    }
                })
                ->get(['group', 'key', 'value']);
        } catch (QueryException) {
            // No settings table yet (a fresh install part-way through its
            // migrations, or a boot with no database): there is nothing to
            // hide by, and the sidebar still has to render. Same fail-open
            // principle as the Route::has() guard above: a nav helper must
            // never be the thing that takes the admin panel down.
            return [];
        }

        return $rows
            ->mapWithKeys(fn (Setting $row) => [$row->group . '.' . $row->key => $row->value])
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
                        // The sidebar lists every settings destination, so the
                        // active item is the specific one, not the hub.
                        return [
                            'group' => $group['label'],
                            'item' => SettingsNav::activeKeyForPath($path) ?? $item['key'],
                        ];
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
