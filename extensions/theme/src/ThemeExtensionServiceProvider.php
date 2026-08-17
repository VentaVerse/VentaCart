<?php

namespace Extensions\Theme;

use Illuminate\Support\ServiceProvider;

/**
 * The theme EDITOR as an extension. The theme system itself — rendering,
 * ThemeService, the homepage_sections / nav_menu_items / footer_columns
 * models and migrations — stays core, because the storefront cannot sell
 * without its look. Disabling this extension only closes the editing
 * surface; the storefront keeps rendering from stored settings.
 */
class ThemeExtensionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'theme');
    }
}
