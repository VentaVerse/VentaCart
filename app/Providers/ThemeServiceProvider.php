<?php

namespace App\Providers;

use App\Services\BadgeService;
use App\Services\ThemeService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ThemeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ThemeService::class);
    }

    public function boot(): void
    {
        View::composer(['layouts.store', 'home', 'product.*', 'category.*', 'cart.*', 'checkout.*', 'account.*', 'blog.*', 'sections.*', 'partials.*'], function ($view) {
            $theme = app(ThemeService::class);
            $view->with('theme', $theme);
            $view->with('defaultCurrency', $theme->getDefaultCurrency());
            $view->with('activeCurrency', $theme->getActiveCurrency());
            $view->with('badgeService', app(BadgeService::class));
        });
    }
}
