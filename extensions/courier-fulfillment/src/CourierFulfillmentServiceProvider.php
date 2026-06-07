<?php

namespace Extensions\CourierFulfillment;

use Extensions\CourierFulfillment\Couriers\QuadX\QuadXFulfillment;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class CourierFulfillmentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'courier-fulfillment');

        // Register couriers. Adding a competitor later = one more line here.
        CourierManager::register(QuadXFulfillment::class);

        // Surface the Fulfillment menu item in the admin nav (same gated pattern
        // banners/ph-address use). When this extension isn't installed, the flag
        // is never set and the menu link never renders.
        View::composer('layouts.admin', function ($view) {
            $view->with('fulfillmentInstalled', true);
        });
    }
}
