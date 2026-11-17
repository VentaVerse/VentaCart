<?php

namespace Extensions\Theme;

use Illuminate\Support\ServiceProvider;

/**
 * The theme directory as an extension: install themes and choose the one
 * the store runs. The theme engine itself (ThemeService, view resolution,
 * the menus) stays core, because the storefront cannot sell without it.
 */
class ThemeExtensionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'theme');
    }
}
