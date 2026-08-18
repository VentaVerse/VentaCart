<?php

namespace Extensions\Coupons;

use App\Services\OrderTotals\OrderTotals;
use Illuminate\Support\ServiceProvider;

class CouponsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'coupons');

        // The order-totals sign-in sheet. 400 leaves room before store
        // credit (700): a coupon shrinks the total FIRST, credit covers
        // what is left, so the customer's balance stretches further.
        OrderTotals::register(CouponTotalProvider::class, 400);
    }
}
