<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\CustomerGroup;
use App\Models\GeoZone;

class PaymentRestrictionFormData
{
    /**
     * Shared reference datasets for building payment-restriction forms —
     * the include/exclude dual-listboxes, the shipping-method picker, and
     * the currency picker all draw from this. Used by both
     * PaymentRestrictionController (legacy resource actions) and
     * PaymentMethodPageController's Availability tab so the two surfaces
     * never drift out of sync.
     *
     * @return array{
     *     brands: \Illuminate\Database\Eloquent\Collection,
     *     categories: \Illuminate\Database\Eloquent\Collection,
     *     customerGroups: \Illuminate\Database\Eloquent\Collection,
     *     geoZones: \Illuminate\Database\Eloquent\Collection,
     *     shippingMethods: array<int, array{key: string, name: string}>,
     *     currencies: \Illuminate\Database\Eloquent\Collection,
     * }
     */
    public static function get(): array
    {
        return [
            'brands' => Brand::orderBy('name')->get(),
            'categories' => Category::whereNull('parent_id')->with('children')->orderBy('name')->get(),
            'customerGroups' => CustomerGroup::orderBy('name')->get(),
            'geoZones' => GeoZone::where('is_active', true)->orderBy('sort_order')->get(),
            'shippingMethods' => [
                ['key' => 'free_shipping', 'name' => 'Free Shipping'],
                ['key' => 'weight_based', 'name' => 'Weight-Based'],
                ['key' => 'flat_rate', 'name' => 'Flat Rate'],
                ['key' => 'dhl', 'name' => 'DHL Express'],
            ],
            'currencies' => Currency::orderByDesc('is_default')->orderBy('code')->get(),
        ];
    }
}
