<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\OrderStatus;
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

        return view('admin.payment-settings.index', compact(
            'settings', 'orderStatuses', 'maskedKeys', 'bankAccounts'
        ));
    }
}
