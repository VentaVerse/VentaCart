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
use Illuminate\Support\Str;

class ShippingSettingController extends Controller
{
    private const ENCRYPTED_KEYS = [
        'dhl_site_id',
        'dhl_password',
    ];

    // Legacy '?tab=<slug>' deep links into the old single-page layout — each
    // now has its own dedicated provider page (see routes/admin/settings.php).
    private const TAB_REDIRECTS = [
        'free-shipping' => 'admin.shipping-settings.free-shipping',
        'weight-based' => 'admin.shipping-settings.weight-based',
        'flat-rate' => 'admin.shipping-settings.flat-rate',
        'dhl' => 'admin.shipping-settings.dhl',
    ];

    /**
     * Provider list — the chain in real evaluation order (from
     * ShippingQuoteService::getProviders()), with a one-line summary per
     * provider and a link to its own configuration page. The Origin card
     * and the Rate Tester stay here: they're chain-wide, not per-provider.
     */
    public function index()
    {
        if (is_string($tab = request()->query('tab')) && isset(self::TAB_REDIRECTS[$tab])) {
            return redirect()->route(self::TAB_REDIRECTS[$tab], [], 301);
        }

        $settings = $this->loadSettings();

        $freeShippingActiveCount = FreeShippingRule::where('is_active', true)->count();
        $weightTierCount = WeightBasedMethod::withCount('rates')->get()->sum('rates_count');
        $flatRateCount = FlatShippingRate::count();
        $dhlConfigured = !empty($settings['shipping']['dhl_site_id']) && !empty($settings['shipping']['dhl_password']);

        // Real evaluation order from ShippingQuoteService::getProviders() —
        // free shipping has no enable setting (its "on" state is any active
        // rule) so it carries no settingKey and the view renders no toggle
        // for it.
        $chain = [
            [
                'id' => 'free-shipping',
                'label' => 'Free Shipping',
                'description' => 'Waives shipping cost automatically when a rule\'s conditions are met.',
                'settingKey' => null,
                'active' => $freeShippingActiveCount > 0,
                'summary' => $freeShippingActiveCount . ' active ' . Str::plural('rule', $freeShippingActiveCount),
                'route' => route('admin.shipping-settings.free-shipping'),
            ],
            [
                'id' => 'weight-based',
                'label' => 'Weight Based',
                'description' => 'Cost tiers based on cart weight.',
                'settingKey' => 'weight_based_enabled',
                'active' => ($settings['shipping']['weight_based_enabled'] ?? '0') === '1',
                'summary' => $weightTierCount . ' rate ' . Str::plural('tier', $weightTierCount),
                'route' => route('admin.shipping-settings.weight-based'),
            ],
            [
                'id' => 'flat-rate',
                'label' => 'Flat Rate',
                'description' => 'Fixed-cost shipping methods with custom names.',
                'settingKey' => 'flat_rate_enabled',
                'active' => ($settings['shipping']['flat_rate_enabled'] ?? '0') === '1',
                'summary' => $flatRateCount . ' flat ' . Str::plural('rate', $flatRateCount),
                'route' => route('admin.shipping-settings.flat-rate'),
            ],
            [
                'id' => 'dhl',
                'label' => 'DHL',
                'description' => 'International shipments via the DHL Express API.',
                'settingKey' => 'dhl_enabled',
                'active' => ($settings['shipping']['dhl_enabled'] ?? '0') === '1',
                'summary' => $dhlConfigured ? 'Configured' : 'Not configured',
                'route' => route('admin.shipping-settings.dhl'),
            ],
        ];

        $countries = Country::orderBy('name')->get();
        $defaultCountryId = Country::where('iso2', 'PH')->value('id') ?? $countries->first()?->id ?? 1;

        return view('admin.settings.shipping.index', compact(
            'settings', 'chain', 'countries', 'defaultCountryId'
        ));
    }

    public function freeShipping()
    {
        $freeShippingRules = FreeShippingRule::orderBy('sort_order')->get();

        return view('admin.settings.shipping.free-shipping', compact('freeShippingRules'));
    }

    public function weightBased()
    {
        $weightMethods = WeightBasedMethod::with('geoZones', 'customerGroups', 'rates')->withCount('rates')->orderBy('sort_order')->get();
        $geoZones = GeoZone::where('is_active', true)->orderBy('name')->get();
        $customerGroups = CustomerGroup::orderBy('name')->get();

        return view('admin.settings.shipping.weight-based', compact('weightMethods', 'geoZones', 'customerGroups'));
    }

    public function flatRate()
    {
        $flatRates = FlatShippingRate::with('geoZone', 'customerGroup')->orderBy('sort_order')->get();

        return view('admin.settings.shipping.flat-rate', compact('flatRates'));
    }

    public function dhl()
    {
        $settings = $this->loadSettings();
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

        return view('admin.settings.shipping.dhl', compact('settings', 'maskedKeys'));
    }


    private function loadSettings(): array
    {
        $settings = [];
        foreach (Setting::all() as $s) {
            $settings[$s->group][$s->key] = $s->value;
        }

        return $settings;
    }
}
