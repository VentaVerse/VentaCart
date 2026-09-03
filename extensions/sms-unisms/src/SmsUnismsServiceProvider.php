<?php

namespace Extensions\SmsUnisms;

use Illuminate\Support\ServiceProvider;

class SmsUnismsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'sms-unisms');

        // The same socket payments, shipping rates and couriers plug into.
        // Core learns there is another way to tell a customer; it never
        // learns that the way is SMS, or that UniSMS carries it.
        \App\Services\OrderNotificationService::registerChannel(SmsChannel::class);
    }
}
