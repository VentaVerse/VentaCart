<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\CustomerGroup;
use App\Models\FlatShippingRate;
use App\Models\FreeShippingRule;
use App\Models\GeoZone;
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
        $geoZones = GeoZone::where('is_active', true)->orderBy('name')->get();
        $customerGroups = CustomerGroup::orderBy('name')->get();
        $freeShippingRules = FreeShippingRule::orderBy('sort_order')->get();
        $freeShippingActive = $freeShippingRules->contains('is_active', true);

        // Real evaluation order from ShippingQuoteService::getProviders() — free
        // shipping has no enable setting (its "on" state is any active rule) so it
        // carries no settingKey and the view renders no toggle for it.
        $chain = [
            [
                'id' => 'free-shipping',
                'label' => 'Free Shipping',
                'description' => 'Waives shipping cost automatically when a rule\'s conditions are met.',
                'settingKey' => null,
                'active' => $freeShippingActive,
            ],
            [
                'id' => 'weight-based',
                'label' => 'Weight Based',
                'description' => 'Cost tiers based on cart weight.',
                'settingKey' => 'weight_based_enabled',
                'active' => ($settings['shipping']['weight_based_enabled'] ?? '0') === '1',
            ],
            [
                'id' => 'flat-rate',
                'label' => 'Flat Rate',
                'description' => 'Fixed-cost shipping methods with custom names.',
                'settingKey' => 'flat_rate_enabled',
                'active' => ($settings['shipping']['flat_rate_enabled'] ?? '0') === '1',
            ],
            [
                'id' => 'dhl',
                'label' => 'DHL',
                'description' => 'International shipments via the DHL Express API.',
                'settingKey' => 'dhl_enabled',
                'active' => ($settings['shipping']['dhl_enabled'] ?? '0') === '1',
            ],
        ];

        $countries = Country::orderBy('name')->get();
        $defaultCountryId = Country::where('iso2', 'PH')->value('id') ?? $countries->first()?->id ?? 1;

        return view('admin.shipping-settings.index', compact(
            'settings', 'maskedKeys', 'flatRates', 'weightMethods', 'geoZones', 'customerGroups',
            'freeShippingRules', 'chain', 'countries', 'defaultCountryId'
        ));
    }
}
