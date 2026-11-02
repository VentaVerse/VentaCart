<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Services\ThemeService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ?theme={slug} on a layout route edits that installed theme instead of
 * the live one: the layout scope, the regions, the settings prefix and the
 * palette all follow, and the theme's own stashed look is laid over the
 * live groups so colour chips speak its vocabulary. Put back afterwards.
 *
 * A theme that keeps its settings in storage of its own (`"storage": "own"`)
 * is never edited here: a request for it is sent to the theme's own editor,
 * or answered 404 when it declares none, before anything is written.
 */
class EditTheme
{
    /**
     * Installed themes with no editor of their own that keep their settings
     * in core: the ones this editor arranges.
     */
    public static function rideable(ThemeService $theme): array
    {
        return array_filter($theme->getInstalledThemes(), function ($meta, $slug) {
            $json = json_decode((string) @file_get_contents(ThemeService::root("{$slug}/theme.json")), true) ?: [];

            return empty($json['editor']) && ($json['storage'] ?? null) !== 'own';
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * The answer to a request that would edit a theme keeping its own
     * storage: its own editor when it declares one, else 404.
     */
    public static function sendAway(ThemeService $theme, string $slug): Response
    {
        $editor = $theme->editorRoute($slug);
        if ($editor === null) {
            abort(404);
        }

        return redirect()->route($editor);
    }

    public function handle(Request $request, Closure $next)
    {
        $theme = app(ThemeService::class);
        $live = (string) (Setting::where('group', 'identity')->where('key', 'active_theme')->value('value') ?: ThemeService::THEME);
        $rideable = self::rideable($theme);

        // The asked-for theme when it rides this editor, else the live one
        // when it does, else the first that does: a theme with its own
        // editor is decoupled and never edited here.
        $asked = trim((string) ($request->query('theme') ?? $request->input('theme') ?? ''));

        // Asked for by name, a theme that edits its settings in its own
        // panel goes there, rather than this editor quietly opening another.
        $installed = $theme->getInstalledThemes();
        if ($asked !== '' && isset($installed[$asked]) && $theme->keepsOwnStorage($asked)) {
            return self::sendAway($theme, $asked);
        }

        $slug = isset($rideable[$asked]) ? $asked : (isset($rideable[$live]) ? $live : (string) array_key_first($rideable));

        // No theme rides this editor, so the live one would be edited: refuse
        // when the live theme keeps its own storage.
        if ($slug === '' && $theme->keepsOwnStorage($live)) {
            return self::sendAway($theme, $live);
        }

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
