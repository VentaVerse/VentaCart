<?php

namespace App\Support;

use App\Services\ExtensionManager;
use Illuminate\Support\Str;

/**
 * URL segments -> ancestor breadcrumb trail, via the admin-nav / settings-nav
 * registries (config/admin-nav.php, config/settings-nav.php). Task 7 of the
 * admin-v2 IA & extension-kernel plan — see the spec's "Navigation derives
 * from the path" section:
 * docs/superpowers/specs/2026-08-07-admin-ia-and-extension-kernel-design.md
 *
 * The trail returned is ANCESTORS ONLY (never the current page itself) —
 * x-admin.page renders the page's own $title separately as the <h1> below
 * the trail. Segment mapping:
 *   - section segment (segments[0])  -> the owning group's label
 *   - entity segment  (segments[1])  -> the registry item's label, linked
 *     to its own index URL
 *   - deeper segments (anything past the entity) -> a single trailing
 *     unlinked crumb built from the page's own $title, EXCEPT under
 *     settings/modules/{name}/... where {name} always gets its own linked
 *     crumb first (registry item if one exists — e.g. abandoned-carts, seo —
 *     else the extension's manifest title via ExtensionManager::discovered(),
 *     e.g. banners) before any further $title crumb.
 *
 * Longest-registered-URL-prefix matching (the Bagisto lesson the spec cites)
 * is handled structurally here rather than by literal string comparison:
 * every settings-nav item's *matching* prefix is truncated to
 * settings/modules/{key} when it lives under a modules/ mount, even though
 * its own resolved URL (the crumb's link target) is deeper — see
 * SettingsNav::activeKeyForPath(), which shares this convention for the
 * settings-shell sub-sidebar's active state.
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

        if ($entitySegment === 'modules' && isset($segments[2])) {
            $modulesItem = SettingsNav::find('modules');
            $crumbs[] = [
                'label' => $modulesItem['label'] ?? 'Modules',
                'url' => $modulesItem ? SettingsNav::url($modulesItem) : route('admin.modules.index'),
            ];

            $name = $segments[2];
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

            if (count($segments) > 3 && $title) {
                $crumbs[] = ['label' => $title];
            }

            return $crumbs;
        }

        $navItem = static::findSettingsItemByKey($entitySegment);

        $crumbs[] = $navItem
            ? ['label' => $navItem['label'], 'url' => SettingsNav::url($navItem)]
            : ['label' => Str::title(str_replace('-', ' ', $entitySegment))];

        if (count($segments) > 2 && $title) {
            $crumbs[] = ['label' => $title];
        }

        return $crumbs;
    }

    protected static function findSettingsItemByKey(string $key): ?array
    {
        foreach (config('settings-nav') as $group) {
            foreach ($group['items'] as $item) {
                if ($item['key'] === $key) {
                    return $item;
                }
            }
        }

        return null;
    }

    /** Canonical URL for an extension mounted under the kernel's settings/modules/{name} convention. */
    protected static function extensionUrl(string $name): string
    {
        $prefix = trim((string) config('admin.prefix', 'admin'), '/');

        return url($prefix.'/settings/modules/'.$name);
    }
}
