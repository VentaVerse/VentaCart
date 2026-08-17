<?php

namespace Extensions\ShippingDhl;

use App\Services\Shipping\ShippingQuoteService;
use Illuminate\Support\ServiceProvider;

class ShippingDhlServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'shipping-dhl');

        ShippingQuoteService::register(DhlShippingProvider::class, 40);
    }
}
