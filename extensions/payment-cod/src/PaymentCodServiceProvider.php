<?php

namespace Extensions\PaymentCod;

use App\Services\PaymentManager;
use Illuminate\Support\ServiceProvider;

class PaymentCodServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'payment-cod');

        PaymentManager::register(CodPayment::class);
    }
}
