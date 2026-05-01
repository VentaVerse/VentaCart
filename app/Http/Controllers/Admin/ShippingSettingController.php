<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FlatShippingRate;
use App\Models\Setting;
use App\Models\WeightBasedMethod;
use Illuminate\Support\Facades\Crypt;

class ShippingSettingController extends Controller
{
    private const ENCRYPTED_KEYS = [
        'dhl_site_id',
        'dhl_password',
    ];

    public function index()
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

        $flatRates = FlatShippingRate::with('geoZone', 'customerGroup')->orderBy('sort_order')->get();
        $weightMethods = WeightBasedMethod::with('geoZones', 'customerGroups', 'rates')->withCount('rates')->orderBy('sort_order')->get();
        $geoZones = \App\Models\GeoZone::where('is_active', true)->orderBy('name')->get();
        $customerGroups = \App\Models\CustomerGroup::orderBy('name')->get();

        return view('admin.shipping-settings.index', compact(
            'settings', 'maskedKeys', 'flatRates', 'weightMethods', 'geoZones', 'customerGroups'
        ));
    }
}
