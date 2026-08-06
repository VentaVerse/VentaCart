<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\OrderStatus;
use App\Models\PaymentMethodRestriction;
use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

class PaymentSettingController extends Controller
{
    private const ENCRYPTED_KEYS = [
        'hitpay_sandbox_api_key',
        'hitpay_sandbox_salt',
        'hitpay_production_api_key',
        'hitpay_production_salt',
    ];

    /**
     * Method families shown as cards on the landing page: family key =>
     * [display label default, description].
     */
    private const FAMILIES = [
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

    public function index()
    {
        $settings = [];
        foreach (Setting::all() as $s) {
            $settings[$s->group][$s->key] = $s->value;
        }

        $orderStatuses = OrderStatus::where('store_id', (int) config('app.store_id', 1))
            ->orderBy('sort_order')
            ->get(['id', 'name']);

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

        $bankAccounts = BankAccount::orderBy('sort_order')->orderBy('bank_name')->get();

        $restrictions = PaymentMethodRestriction::where('is_active', true)
            ->whereIn('payment_method', array_keys(self::FAMILIES))
            ->get()
            ->keyBy('payment_method');

        $cards = [];
        foreach (self::FAMILIES as $family => $meta) {
            $restriction = $restrictions->get($family);
            $displayName = $settings['payment']["{$family}_display_name"] ?? '';
            $cards[] = [
                'family' => $family,
                'name' => !empty($displayName) ? $displayName : $meta['default_name'],
                'description' => $meta['description'],
                'enabled' => ($settings['payment']["{$family}_enabled"] ?? '0') === '1',
                'restricted' => $restriction !== null,
                'restriction_summary' => $restriction?->getConditionsSummary(),
            ];
        }

        return view('admin.payment-settings.index', compact(
            'settings', 'orderStatuses', 'maskedKeys', 'bankAccounts', 'cards'
        ));
    }
}
