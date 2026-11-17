<?php

namespace App\Support;

use App\Services\ExtensionManager;
use Illuminate\Support\Str;

/**
 * URL segments -> ancestor breadcrumb trail, from the admin navigation
 * registries (config/admin-nav.php and config/admin-settings-nav.php).
 *
 * The trail returned is ancestors only (never the current page itself):
 * x-admin.page renders the page's own $title separately as the <h1> below
 * the trail. Segment mapping:
 *   - section segment (segments[0])  -> the owning group's label
 *   - entity segment  (segments[1])  -> the registry item's label, linked
 *     to its own index URL
 *   - deeper segments (anything past the entity) -> a single trailing
 *     unlinked crumb built from the page's own $title, except under
 *     extensions/{name}/... where {name} always gets its own linked
 *     crumb first (a registry item if one exists, else the extension's
 *     manifest title via ExtensionManager::discovered()) before any
 *     further $title crumb.
 *
 * Settings items match by their URL segment (settings/{key}), the same
 * convention SettingsNav::activeKeyForPath() uses for the settings
 * sub-menu's active state.
 */
class Breadcrumbs
{
    /**
     * The ancestor crumb trail for a request path, e.g. '/admin/sales/orders/5/edit'.
     * $title is the page's own title (x-admin.page's $title prop) — used only
     * as the label for a trailing "deeper than the entity" context crumb,
     * never for the section/entity crumbs themselves (those always come from
     * the registries).
     *
     * @return array<int, array{label: string, url?: string}>
     */
    public static function forPath(string $path, ?string $title = null): array
    {
        $segments = static::segments($path);

        if ($segments === []) {
            return [];
        }

        if ($segments[0] === 'settings') {
            return static::forSettings($segments, $title);
        }

        if ($segments[0] === 'extensions') {
            return static::forExtensions($segments, $title);
        }

        return static::forAdminNav($segments, $title);
    }

    /** Path segments with the configured admin prefix stripped off the front. */
    public static function segments(string $path): array
    {
        $prefix = trim((string) config('admin.prefix', 'admin'), '/');
        $segments = array_values(array_filter(
            explode('/', trim($path, '/')),
            fn ($segment) => $segment !== ''
        ));

        if (($segments[0] ?? null) === $prefix) {
            array_shift($segments);
        }

        return $segments;
    }

    protected static function forAdminNav(array $segments, ?string $title): array
    {
        $root = $segments[0];

        foreach (AdminNav::registry() as $group) {
            // The ungrouped group (Dashboard) is a single root-level page,
            // not a section with entities beneath it — no breadcrumb.
            if ($group['label'] === null || ($group['segment'] ?? null) !== $root) {
                continue;
            }

            $crumbs = [['label' => $group['label']]];

            $entityKey = $segments[1] ?? null;
            $item = collect($group['items'])->first(fn (array $i) => ($i['key'] ?? null) === $entityKey);

            if (! $item) {
                return $crumbs;
            }

            $crumbs[] = ['label' => $item['label'], 'url' => route($item['route'])];

            if (count($segments) > 2 && $title) {
                $crumbs[] = ['label' => $title];
            }

            return $crumbs;
        }

        return [];
    }

    protected static function forSettings(array $segments, ?string $title): array
    {
        // Exactly the hub itself ('/settings') — no ancestor trail needed,
        // same as the dashboard root.
        if (! isset($segments[1])) {
            return [];
        }

        $crumbs = [['label' => 'Settings', 'url' => route('admin.settings.index')]];

        $entitySegment = $segments[1];

        $navItem = static::findSettingsItemByKey($entitySegment);

        $crumbs[] = $navItem
            ? ['label' => $navItem['label'], 'url' => SettingsNav::url($navItem)]
            : ['label' => Str::title(str_replace('-', ' ', $entitySegment))];

        if (count($segments) > 2 && $title) {
            $crumbs[] = ['label' => $title];
        }

        return $crumbs;
    }

    /**
     * Trails inside the Extensions area (wave 0): the directory itself
     * has no ancestors; extensions/{name}/... gets "Extensions" then the
     * extension's own linked crumb (registry item if one exists, else the
     * manifest title), then the page's own $title when deeper.
     */
    protected static function forExtensions(array $segments, ?string $title): array
    {
        if (! isset($segments[1])) {
            return [];
        }

        // An extension whose entry sits in a work group, or alone, trails from there.
        $path = implode('/', $segments);
        if (($home = AdminNav::extensionHome($path)) && \Illuminate\Support\Facades\Route::has($home['route'])) {
            $crumbs = $home['group'] === null ? [] : [['label' => $home['group']]];
            $crumbs[] = ['label' => $home['label'], 'url' => route($home['route'])];
            if (count($segments) > 2 && $title && $title !== $home['label']) {
                $crumbs[] = ['label' => $title];
            }

            return $crumbs;
        }

        $crumbs = [['label' => 'Extensions', 'url' => route('admin.extensions.index')]];

        $name = $segments[1];
        $navItem = static::findSettingsItemByKey($name);

        if ($navItem) {
            $crumbs[] = ['label' => $navItem['label'], 'url' => SettingsNav::url($navItem)];
        } else {
            $manifest = app(ExtensionManager::class)->discovered()->get($name);
            $crumbs[] = [
                'label' => $manifest['title'] ?? Str::title(str_replace('-', ' ', $name)),
                'url' => static::extensionUrl($name),
            ];
        }

        if (count($segments) > 2 && $title) {
            $crumbs[] = ['label' => $title];
        }

        return $crumbs;
    }

    protected static function findSettingsItemByKey(string $key): ?array
    {
        foreach (config('admin-settings-nav') as $group) {
            foreach ($group['items'] as $item) {
                if ($item['key'] === $key) {
                    return $item;
                }
            }
        }

        return null;
    }

    /** Canonical URL for an extension mounted under the kernel's extensions/{name} convention. */
    protected static function extensionUrl(string $name): string
    {
        $prefix = trim((string) config('admin.prefix', 'admin'), '/');

        return url($prefix.'/extensions/'.$name);
    }
}
