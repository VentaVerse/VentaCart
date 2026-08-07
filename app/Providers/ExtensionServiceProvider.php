<?php

namespace App\Providers;

use App\Services\ExtensionManager;
use Illuminate\Support\ServiceProvider;

/**
 * Boots every installed+enabled extension's own provider. Registering extension
 * providers here (in register(), not boot()) is deliberate: providers added via
 * $this->app->register() during another provider's register() phase are still
 * included in the framework's normal boot loop, so their boot() hooks (routes,
 * views, nav merges) run exactly like any statically-declared provider.
 */
class ExtensionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ExtensionManager::class);

        $this->app->make(ExtensionManager::class)->bootEnabled();
    }
}
