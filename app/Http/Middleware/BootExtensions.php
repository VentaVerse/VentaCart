<?php

namespace App\Http\Middleware;

use App\Services\ExtensionManager;
use Closure;
use Illuminate\Http\Request;

/**
 * Safety-net boot for the extension kernel, run on every request before
 * routing.
 *
 * ExtensionServiceProvider::register() already boots installed+enabled
 * extensions during normal app bootstrap, and that is sufficient for every
 * real deployment (migrations always run via CLI before the app ever serves
 * a request, so the `extensions` table is already populated by the time
 * ExtensionServiceProvider registers).
 *
 * It is NOT sufficient inside Laravel's own test harness: RefreshDatabase
 * swaps in a migrated in-memory SQLite connection *after* the application
 * has already finished booting (see Illuminate\Foundation\Testing\
 * RefreshDatabase::refreshDatabase(), called from setUpTraits() — which runs
 * after refreshApplication()). So in every RefreshDatabase test,
 * ExtensionServiceProvider::register() runs against a connection that has no
 * `extensions` table yet, and correctly boots nothing. Without this
 * middleware, extension routes (banners' admin.banners.* included) would
 * never register in any test, even after migrations complete.
 *
 * ExtensionManager::bootEnabled() is idempotent (Laravel's own
 * Application::register() short-circuits already-registered providers), and
 * ExtensionManager caches a `$booted` flag once it successfully runs against
 * a present `extensions` table, so this is a cheap no-op on every request
 * after the first in a normal (non-test) process.
 */
class BootExtensions
{
    public function handle(Request $request, Closure $next)
    {
        app(ExtensionManager::class)->bootEnabled();

        return $next($request);
    }
}
