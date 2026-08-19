<?php

namespace Extensions\EmailCampaigns;

use Illuminate\Support\ServiceProvider;

class EmailCampaignsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'email-campaigns');
    }
}
