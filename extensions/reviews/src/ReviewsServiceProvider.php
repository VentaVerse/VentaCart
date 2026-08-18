<?php

namespace Extensions\Reviews;

use Extensions\Reviews\Console\SendReviewRequests;
use Illuminate\Support\ServiceProvider;

class ReviewsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'reviews');

        // review:send exists only while this extension is enabled; its
        // Automations row keeps the schedule and fails visibly while it
        // is off (the honest state).
        if ($this->app->runningInConsole()) {
            $this->commands([SendReviewRequests::class]);
        }
    }
}
