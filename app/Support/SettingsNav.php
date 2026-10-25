<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

class SettingsNav
{
    /**
     * Groups with items the given user may see, by the same permission map
     * the routes enforce (see AdminNav::userMayOpen()). Null user = no
     * filtering.
     *
     * Also drops any item whose named route isn't currently registered —
     * same reasoning as App\Support\AdminNav::groups(): the Fulfillment hub
     * card points at an extension-provided route (admin.fulfillment.settings)
     * that only exists while courier-fulfillment is installed AND enabled,
     * and the Settings hub/sub-sidebar renders every group's items via
     * route($item['route']) unconditionally.
     */
    public static function groups(?User $user = null): array
    {
        $permissions = app(AdminPermissions::class);

        return collect(config('settings-nav'))
            ->map(function (array $group) use ($user, $permissions) {
                $group['items'] = array_values(array_filter(
                    $group['items'],
                    fn (array $item) => AdminNav::userMayOpen($user, $item, $permissions)
                        && Route::has($item['route'])
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
     * request path, by longest-URL-prefix match (Task 7). Item keys equal
     * their URL segment (settings/{key}). The old settings/modules/{key}
     * convention retired with the Extensions area (wave 0): extension
     * pages live at extensions/{name} and answer to the main nav's
     * Extensions group, not this space.
     */
    public static function activeKeyForPath(string $path): ?string
    {
        $segments = Breadcrumbs::segments($path);

        if (($segments[0] ?? null) !== 'settings') {
            return null;
        }

        return $segments[1] ?? null;
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
