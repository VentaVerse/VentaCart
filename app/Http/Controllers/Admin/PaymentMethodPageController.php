<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\OrderStatus;
use App\Models\PaymentMethodRestriction;
use App\Models\Setting;
use App\Support\PaymentRestrictionFormData;
use Illuminate\Support\Facades\Crypt;

class PaymentMethodPageController extends Controller
{
    private const ENCRYPTED_KEYS = [
        'hitpay_sandbox_api_key',
        'hitpay_sandbox_salt',
        'hitpay_production_api_key',
        'hitpay_production_salt',
    ];

    /**
     * Method families this detail page knows how to render: family key =>
     * [display label default, description]. Keep in sync with
     * PaymentSettingController::FAMILIES (landing page cards).
     */
    private const METHODS = [
        'bank_transfer' => [
            'default_name' => 'Bank Transfer',
            'description' => 'Multi-account bank transfer with QR codes',
        ],
        'hitpay' => [
            'default_name' => 'HitPay (Card / GCash / GrabPay / Maya)',
            'description' => 'Online payment gateway (credit card, e-wallets)',
        ],
        'cod' => [
            'default_name' => 'Cash on Delivery',
            'description' => 'Customer pays upon delivery',
        ],
    ];

    public function show(string $method)
    {
        abort_unless(array_key_exists($method, self::METHODS), 404);

        $meta = self::METHODS[$method];

        $settings = ['payment' => []];
        foreach (Setting::where('group', 'payment')->get() as $s) {
            $settings['payment'][$s->key] = $s->value;
        }

        $displayName = $settings['payment']["{$method}_display_name"] ?? '';
        $name = !empty($displayName) ? $displayName : $meta['default_name'];

        $orderStatuses = OrderStatus::where('store_id', (int) config('app.store_id', 1))
            ->orderBy('sort_order')
            ->get(['id', 'name']);

        $maskedKeys = [];
        if ($method === 'hitpay') {
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
        }

        $bankAccounts = $method === 'bank_transfer'
            ? BankAccount::orderBy('sort_order')->orderBy('bank_name')->get()
            : collect();

        $restriction = PaymentMethodRestriction::where('payment_method', $method)->first();

        return view('admin.payment-settings.show', [
            'method' => $method,
            'name' => $name,
            'defaultName' => $meta['default_name'],
            'description' => $meta['description'],
            'settings' => $settings,
            'orderStatuses' => $orderStatuses,
            'maskedKeys' => $maskedKeys,
            'bankAccounts' => $bankAccounts,
            'restriction' => $restriction,
            ...PaymentRestrictionFormData::get(),
        ]);
    }
}
