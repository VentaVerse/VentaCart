<?php

namespace Extensions\StockAlerts;

use Extensions\StockAlerts\Console\CheckLowStock;
use Extensions\StockAlerts\Console\NotifyStockSubscribers;
use Extensions\StockAlerts\Database\Seeders\StockNotificationTemplateSeeder;
use Illuminate\Support\ServiceProvider;

class StockAlertsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'stock-alerts');

        // stock:notify and stock:check-low exist only while this extension
        // is enabled. Their Automations rows keep the schedule; while the
        // extension is disabled the scheduled run fails fast ("command not
        // found") and the Automations page shows it, which is the honest
        // state for a paused extension's job (same as fulfillment's poll).
        if ($this->app->runningInConsole()) {
            $this->commands([NotifyStockSubscribers::class, CheckLowStock::class]);
        }

        $this->registerSeeder(StockNotificationTemplateSeeder::class);
    }

    /** Make db:seed pick up this extension's seeder (same hook core uses). */
    private function registerSeeder(string $seeder): void
    {
        $seeders = config('venta.extension_seeders', []);
        $seeders[] = $seeder;
        config(['venta.extension_seeders' => $seeders]);
    }
}
