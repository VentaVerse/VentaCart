<?php

namespace Extensions\ShippingFlatRate;

use App\Services\Shipping\ShippingQuoteService;
use Illuminate\Support\ServiceProvider;

class ShippingFlatRateServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'shipping-flat-rate');

        ShippingQuoteService::register(FlatRateShippingProvider::class, 30);
    }
}
