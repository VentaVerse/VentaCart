<?php

namespace Extensions\AbandonedCarts;

use Extensions\AbandonedCarts\Console\SendAbandonedCartNotifications;
use Extensions\AbandonedCarts\Database\Seeders\AbandonedCartTemplateSeeder;
use Illuminate\Support\ServiceProvider;

class AbandonedCartsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'abandoned-carts');

        // cart:notify exists only while this extension is enabled. Its
        // Automations row keeps the schedule; while disabled the scheduled
        // run fails fast and the Automations page shows it (the honest
        // state for a paused extension's job). The recovery sequence's own
        // on/off (abandoned_cart.enabled) stays a setting INSIDE the
        // feature, checked by the command itself.
        if ($this->app->runningInConsole()) {
            $this->commands([SendAbandonedCartNotifications::class]);
        }

        $this->registerSeeder(AbandonedCartTemplateSeeder::class);
    }

    /** Make db:seed pick up this extension's seeder (same hook core uses). */
    private function registerSeeder(string $seeder): void
    {
        $seeders = config('venta.extension_seeders', []);
        $seeders[] = $seeder;
        config(['venta.extension_seeders' => $seeders]);
    }
}
