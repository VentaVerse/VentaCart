<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Services\ThemeService;
use Closure;
use Illuminate\Http\Request;

/**
 * ?theme={slug} on a layout route edits that installed theme instead of
 * the live one: the layout scope, the regions, the settings prefix and the
 * palette all follow, and the theme's own stashed look is laid over the
 * live groups so colour chips speak its vocabulary. Put back afterwards.
 */
class EditTheme
{
    /** Installed themes with no editor of their own: the ones this editor arranges. */
    public static function rideable(ThemeService $theme): array
    {
        return array_filter($theme->getInstalledThemes(), function ($meta, $slug) {
            $json = json_decode((string) @file_get_contents(resource_path("themes/{$slug}/theme.json")), true) ?: [];

            return empty($json['editor']);
        }, ARRAY_FILTER_USE_BOTH);
    }

    public function handle(Request $request, Closure $next)
    {
        $theme = app(ThemeService::class);
        $live = (string) (Setting::where('group', 'identity')->where('key', 'active_theme')->value('value') ?: ThemeService::THEME);
        $rideable = self::rideable($theme);

        // The asked-for theme when it rides this editor, else the live one
        // when it does, else the first that does: a theme with its own
        // editor (Plaza) is decoupled and never edited here.
        $asked = trim((string) ($request->query('theme') ?? $request->input('theme') ?? ''));
        $slug = isset($rideable[$asked]) ? $asked : (isset($rideable[$live]) ? $live : (string) array_key_first($rideable));

        if ($slug === '' || $slug === $live) {
            return $next($request);
        }

        $overlay = ['identity' => ['active_theme' => $slug]];
        foreach (['colors', 'fonts', 'vars'] as $group) {
            $stash = $theme->getGroup("{$group}__theme_{$slug}");
            if ($stash !== []) {
                $overlay[$group] = $stash;
            }
        }
        $preset = $theme->getGroup("identity__theme_{$slug}")['active_preset'] ?? null;
        if ($preset !== null) {
            $overlay['identity']['active_preset'] = $preset;
        }
        $theme->overlay($overlay);

        try {
            return $next($request);
        } finally {
            $theme->reload();
        }
    }
}
