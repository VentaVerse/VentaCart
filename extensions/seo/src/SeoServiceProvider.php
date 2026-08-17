<?php

namespace Extensions\Seo;

use Extensions\Seo\Console\GenerateSeoSuggestions;
use Extensions\Seo\Console\GenerateSitemap;
use Illuminate\Support\ServiceProvider;

class SeoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'seo');

        // seo:generate and sitemap:generate exist only while this
        // extension is enabled; their Automations rows keep the schedule
        // and fail visibly while it is off (the honest state).
        if ($this->app->runningInConsole()) {
            $this->commands([GenerateSeoSuggestions::class, GenerateSitemap::class]);
        }
    }
}
