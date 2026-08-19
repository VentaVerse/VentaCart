<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * The active theme's view folder must be findable while a request is being
 * served, not merely at the instant the application booted.
 *
 * AppServiceProvider prepends that folder from a one-shot `booted()` callback
 * which reads the active theme out of the `settings` table. That is correct
 * for every real deployment, where migrations have always run before the app
 * serves anything. It is NOT correct inside Laravel's test harness:
 * RefreshDatabase swaps in a migrated connection AFTER the application has
 * finished booting, so the lookup runs against a database with no `settings`
 * table, throws, is swallowed by the provider's `catch (\Throwable)`, and the
 * folder is never registered. `booted` does not fire twice, so every
 * storefront view in that test resolves against core views alone and dies
 * with "View [account.dashboard] not found".
 *
 * This is the same ordering hazard BootExtensions was written for, and it is
 * answered the same way: a per-request safety net that costs a single
 * in-array check once the folder is already registered.
 */
class ThemeViewLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_active_themes_views_are_findable_while_serving_a_request(): void
    {
        // Any route through the web stack. The homepage is the one every
        // storefront visitor hits, so if the theme is missing anywhere it is
        // missing here.
        $this->get('/');

        $this->assertContains(
            resource_path('themes/' . app(\App\Services\ThemeService::class)->getActiveTheme() . '/views'),
            View::getFinder()->getPaths(),
            'The active theme\'s view folder was not registered while serving the request.'
        );
    }

    /**
     * The safety net runs on every request, so it must not keep prepending the
     * same folder. A duplicate would be harmless to correctness and unbounded
     * in a long-running worker (Octane), which is the sort of leak nobody
     * notices until memory does.
     */
    public function test_serving_many_requests_registers_the_theme_folder_once(): void
    {
        $this->get('/');
        $this->get('/');
        $this->get('/');

        $path = resource_path('themes/' . app(\App\Services\ThemeService::class)->getActiveTheme() . '/views');

        $this->assertSame(
            1,
            count(array_keys(View::getFinder()->getPaths(), $path, true)),
            'The theme view folder was registered more than once.'
        );
    }
}
