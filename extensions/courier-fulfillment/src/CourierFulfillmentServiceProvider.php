<?php

namespace Extensions\CourierFulfillment;

use Extensions\CourierFulfillment\Console\Commands\PollCourierStatus;
use Extensions\CourierFulfillment\Couriers\QuadX\QuadXFulfillment;
use Extensions\CourierFulfillment\Couriers\Spx\SpxFulfillment;
use App\Support\SettingsNav;
use Extensions\CourierFulfillment\Console\Commands\ImportPickupAddressFromSettings;
use Extensions\CourierFulfillment\Database\Seeders\CourierFulfillmentAutomationSeeder;
use Illuminate\Support\Facades\Gate;
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
        CourierManager::register(SpxFulfillment::class);

        // Status updates use the pull/fetch model. The poll runs as a managed
        // automation (Settings > Automations), driven by the global schedule:run
        // cron, deliberately NOT a hardcoded schedule here.
        //
        // Registered unconditionally, not behind runningInConsole(). The
        // Automations page's Run now calls Artisan::call() inside an HTTP
        // request, where that check is false, so gating on it left the command
        // unresolvable from the one screen built to run it. commands() is
        // already lazy: it defers to Artisan::starting(), so a web request that
        // never boots Artisan pays nothing for this.
        $this->commands([PollCourierStatus::class, ImportPickupAddressFromSettings::class]);

        // Who may configure this module. Same answer FulfillmentController
        // already computes for its own "Courier settings" affordance (the
        // settings-nav item's permission, null meaning any authenticated
        // admin), stated once as an ability so the pickup-address CRUD's
        // controller and its Form Request authorize against the same rule
        // rather than each re-deriving it.
        Gate::define('courier-fulfillment.configure', function ($user) {
            $item = SettingsNav::find('fulfillment');

            if (!$item) {
                return false;
            }

            return $item['permission'] === null || $user->hasPermission($item['permission']);
        });
        $this->registerSeeder(CourierFulfillmentAutomationSeeder::class);

        // NOTE: this provider used to also register a
        // View::composer('layouts.admin', ...) here, feeding fulfillmentEnabled/
        // fulfillmentInstalled to the pre-kernel sidebar partial. `layouts.admin`
        // no longer exists (admin-v2's sidebar renders from config/admin-nav.php
        // + App\Support\AdminNav, gated by Route::has() — see that config file's
        // 'fulfillment' entry) so the composer was dead code; removed rather than
        // ported. The 'fulfillment_enabled' setting it read now does real work as
        // an opt-out "pause the queue" toggle, read directly by
        // FulfillmentController::index() (renders an explanatory empty state
        // rather than hiding/redirecting anything) — see that controller and
        // CourierSettingsController's task reports for the full history.
    }

    /** Make db:seed pick up this extension's seeder (same hook core uses). */
    private function registerSeeder(string $seeder): void
    {
        $seeders = config('venta.extension_seeders', []);
        $seeders[] = $seeder;
        config(['venta.extension_seeders' => $seeders]);
    }
}
