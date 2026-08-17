<?php

namespace Extensions\PaymentBankTransfer;

use App\Services\PaymentManager;
use Extensions\PaymentBankTransfer\Models\BankAccount;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class PaymentBankTransferServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'payment-bank-transfer');

        PaymentManager::register(BankTransferPayment::class);

        // The method page (core framework) renders this extension's config
        // tab; the account list is this extension's own data, so it
        // arrives by composer instead of a per-family branch in core.
        View::composer('payment-bank-transfer::config', function ($view) {
            $view->with('bankAccounts', BankAccount::orderBy('sort_order')->orderBy('bank_name')->get());
        });
    }
}
