<?php

namespace Extensions\ShippingFree;

use App\Services\Shipping\ShippingQuoteService;
use Illuminate\Support\ServiceProvider;

class ShippingFreeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'shipping-free');

        // First in the chain: a matching free rule replaces every paid
        // method below it (ShippingQuoteService's free-shipping override).
        ShippingQuoteService::register(FreeShippingRuleProvider::class, 10);
    }
}
