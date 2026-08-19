<?php

namespace App\Http\Middleware;

use App\Services\ThemeService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

/**
 * Safety-net registration of the active theme's view folder, run on every web
 * request.
 *
 * AppServiceProvider already prepends that folder from a `booted()` callback,
 * and that is sufficient for every real deployment: migrations always run via
 * CLI before the app serves a request, so the `settings` table is populated by
 * the time the callback reads it.
 *
 * It is NOT sufficient inside Laravel's own test harness, for exactly the
 * reason spelled out in BootExtensions: RefreshDatabase swaps in a migrated
 * connection AFTER the application has finished booting. So in every
 * RefreshDatabase test the callback runs against a connection with no
 * `settings` table, throws, and is swallowed by the provider's deliberate
 * `catch (\Throwable)` (which exists so a fresh install can migrate at all).
 * `booted` never fires again, so the theme folder stays unregistered for the
 * life of that test and every storefront view resolves against core views
 * alone, dying with "View [account.dashboard] not found".
 *
 * The same hole is reachable outside tests, which is the better argument for
 * fixing it here rather than in the harness: any boot that happens while the
 * database is briefly unreachable would otherwise serve unthemed pages until
 * the process was restarted.
 *
 * Cost after the first success is one in_array over a handful of paths. The
 * duplicate guard is load-bearing rather than tidy: without it a long-running
 * worker (Octane) would prepend the same folder on every request forever.
 */
class RegisterThemeViews
{
    public function handle(Request $request, Closure $next)
    {
        self::register();

        return $next($request);
    }

    /**
     * Prepend the active theme's view folder if it is not already there.
     *
     * Returns false when the theme could not be resolved (no database yet) or
     * names a folder that is not on disk. Both are non-fatal: the caller is a
     * safety net and the next request tries again.
     */
    public static function register(): bool
    {
        try {
            $theme = app(ThemeService::class)->getActiveTheme();
        } catch (\Throwable) {
            // The settings table may not exist yet: fresh install, mid-migration,
            // or a test whose database is created after the app boots.
            return false;
        }

        $path = resource_path("themes/{$theme}/views");

        if (!is_dir($path)) {
            return false;
        }

        $finder = View::getFinder();

        if (in_array($path, $finder->getPaths(), true)) {
            return true;
        }

        $finder->prependLocation($path);

        return true;
    }
}
