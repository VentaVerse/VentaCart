<?php

namespace Extensions\PaymentHitpay;

use App\Services\PaymentManager;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class PaymentHitpayServiceProvider extends ServiceProvider
{
    private const ENCRYPTED_KEYS = [
        'hitpay_sandbox_api_key',
        'hitpay_sandbox_salt',
        'hitpay_production_api_key',
        'hitpay_production_salt',
    ];

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'payment-hitpay');

        PaymentManager::register(HitPayPayment::class);

        // The method page (core framework) renders this extension's config
        // tab; the masked API keys are this extension's own concern, so
        // they arrive by composer instead of a per-family branch in core.
        View::composer('payment-hitpay::config', function ($view) {
            $settings = $view->getData()['settings'] ?? ['payment' => []];
            $maskedKeys = [];
            foreach (self::ENCRYPTED_KEYS as $key) {
                $encrypted = $settings['payment'][$key] ?? null;
                if ($encrypted) {
                    try {
                        $plain = Crypt::decryptString($encrypted);
                        $maskedKeys[$key] = str_repeat('*', max(0, strlen($plain) - 4)) . substr($plain, -4);
                    } catch (\Throwable) {
                        $maskedKeys[$key] = '********';
                    }
                }
            }
            $view->with('maskedKeys', $maskedKeys);
        });
    }
}
