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
    public function handle(Request $request, Closure $next)
    {
        $slug = trim((string) ($request->query('theme') ?? $request->input('theme') ?? ''));
        $theme = app(ThemeService::class);
        $live = (string) (Setting::where('group', 'identity')->where('key', 'active_theme')->value('value') ?: ThemeService::THEME);

        if ($slug === '' || $slug === $live || ! isset($theme->getInstalledThemes()[$slug])) {
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
