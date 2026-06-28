<?php

namespace Extensions\CourierFulfillment;

use Extensions\CourierFulfillment\Console\Commands\PollCourierStatus;
use Extensions\CourierFulfillment\Couriers\QuadX\QuadXFulfillment;
use Extensions\CourierFulfillment\Database\Seeders\CourierFulfillmentAutomationSeeder;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class CourierFulfillmentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'courier-fulfillment');

        // Register couriers. Adding a competitor later = one more line here.
        CourierManager::register(QuadXFulfillment::class);

        // Status updates use the pull/fetch model. The poll runs as a managed
        // automation (Settings > Automations), driven by the global schedule:run
        // cron — deliberately NOT a hardcoded schedule here. We only register the
        // CLI command and seed its automation row (admin owns it thereafter).
        if ($this->app->runningInConsole()) {
            $this->commands([PollCourierStatus::class]);
        }
        $this->registerSeeder(CourierFulfillmentAutomationSeeder::class);

        // Surface the Fulfillment menu item in the admin nav (same gated pattern
        // banners/ph-address use). When this extension isn't installed, the flag
        // is never set and the menu link never renders.
        View::composer('layouts.admin', function ($view) {
            $view->with('fulfillmentInstalled', true);
        });
    }

    /** Make db:seed pick up this extension's seeder (same hook core uses). */
    private function registerSeeder(string $seeder): void
    {
        $seeders = config('venta.extension_seeders', []);
        $seeders[] = $seeder;
        config(['venta.extension_seeders' => $seeders]);
    }
}
