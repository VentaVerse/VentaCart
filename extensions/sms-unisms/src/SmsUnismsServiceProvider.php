<?php

namespace Extensions\SmsUnisms;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
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

        // UniSMS's own pace, applied to the queued texts. Core's job asks for
        // a limiter named after its target and never names this gateway: a
        // burst of twenty status changes becomes twenty texts sent two
        // seconds apart, instead of one sent and nineteen refused.
        RateLimiter::for('order-notification:' . (new SmsChannel())->key(), function () {
            return Limit::perSecond(1, UniSms::SECONDS_PER_REQUEST);
        });
    }
}
