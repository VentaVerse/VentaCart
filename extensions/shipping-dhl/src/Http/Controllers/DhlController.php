<?php

namespace Extensions\ShippingDhl\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

class DhlController extends Controller
{
    private const ENCRYPTED_KEYS = [
        'dhl_site_id',
        'dhl_password',
    ];

    public function page()
    {
        $settings = [];
        foreach (Setting::all() as $s) {
            $settings[$s->group][$s->key] = $s->value;
        }

        $maskedKeys = [];
        foreach (self::ENCRYPTED_KEYS as $key) {
            $encrypted = $settings['shipping'][$key] ?? null;
            if ($encrypted) {
                try {
                    $plain = Crypt::decryptString($encrypted);
                    $maskedKeys[$key] = str_repeat('*', max(0, strlen($plain) - 4)) . substr($plain, -4);
                } catch (\Throwable) {
                    $maskedKeys[$key] = '********';
                }
            }
        }

        return view('shipping-dhl::index', compact('settings', 'maskedKeys'));
    }
}
