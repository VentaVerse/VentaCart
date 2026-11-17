<?php

namespace Tests\Support;

use App\Services\ExtensionManager;
use App\Services\ThemeService;

/**
 * For tests of the Layout screens, which arrange the default theme's
 * regions: the default theme is installed and made live for the length
 * of the test, beside the sample theme every test has.
 */
trait RunsOnTheDefaultTheme
{
    protected function setUpRunsOnTheDefaultTheme(): void
    {
        $manager = app(ExtensionManager::class);
        $manager->install(ThemeService::registryName('default'));
        app()->forgetInstance(ExtensionManager::class);

        \App\Models\Setting::updateOrCreate(['group' => 'identity', 'key' => 'active_theme'], ['value' => 'default']);
        app(ThemeService::class)->clearCache();
    }
}
