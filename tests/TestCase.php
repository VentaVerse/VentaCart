<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * A migrated test database has no registry rows (seeders do not run),
     * and a store without an installed theme shows the plain no-theme page.
     * The shipped theme is installed the way the seeder installs it on a
     * fresh store, contract check included. A test about the registry
     * itself sets this false and starts from the bare table.
     */
    protected bool $installsDefaultTheme = true;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->installsDefaultTheme && in_array(RefreshDatabase::class, class_uses_recursive(static::class), true)) {
            app(\App\Services\ExtensionManager::class)->install(
                \App\Services\ThemeService::registryName(\App\Services\ThemeService::THEME)
            );
            // Discovery was cached by that install; a test that points the
            // kernel at fixture extensions must not find a stale list.
            app()->forgetInstance(\App\Services\ExtensionManager::class);
        }
    }
}
