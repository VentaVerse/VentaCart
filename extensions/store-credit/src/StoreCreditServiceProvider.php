<?php

namespace Extensions\StoreCredit;

use App\Services\OrderTotals\OrderTotals;
use Extensions\StoreCredit\Console\ImportOpenCartCredit;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class StoreCreditServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'store-credit');

        // After coupons (400): the coupon shrinks the total first, then
        // credit covers what is left, so a balance stretches further.
        OrderTotals::register(StoreCreditTotalProvider::class, 700);

        // The storefront checkout preview: hand the theme the signed-in
        // customer's balance so it can show the credit line the pipeline
        // will apply at placement. No core seam needed; a composer from
        // here simply stops running when the extension is disabled.
        View::composer('checkout.index', function ($view) {
            $customer = auth('customer')->user();
            if ($customer) {
                $view->with('creditBalance', CreditService::balance($customer->id));
            }
        });

        if ($this->app->runningInConsole()) {
            $this->commands([ImportOpenCartCredit::class]);
        }
    }
}
