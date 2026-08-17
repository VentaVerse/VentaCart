<?php

namespace Extensions\ShippingWeightBased;

use App\Services\Shipping\ShippingQuoteService;
use Illuminate\Support\ServiceProvider;

class ShippingWeightBasedServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'shipping-weight-based');

        ShippingQuoteService::register(WeightBasedShippingProvider::class, 20);
    }
}
