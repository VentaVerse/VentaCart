<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use App\Models\GeoZone;
use App\Models\ShippingZoneRate;
use Illuminate\Http\Request;

class ShippingZoneController extends Controller
{
    public function index()
    {
        $zones = GeoZone::where('is_active', true)
            ->withCount('rates')
            ->with('entries')
            ->orderBy('sort_order')
            ->get();

        return view('admin.shipping-zones.index', compact('zones'));
    }

    public function edit(GeoZone $geoZone)
    {
        $geoZone->load('rates');
        $customerGroups = CustomerGroup::orderBy('name')->get();
        $rates = $this->buildRatesFromZone($geoZone, $customerGroups);

        return view('admin.shipping-zones.rate-form', [
            'zone' => $geoZone,
            'customerGroups' => $customerGroups,
            'rates' => $rates,
        ]);
    }

    public function update(Request $request, GeoZone $geoZone)
    {
        // Delete existing rates and recreate
        ShippingZoneRate::where('geo_zone_id', $geoZone->id)->delete();

        $this->syncRates($geoZone, $request->input('rates', []));

        return redirect()->route('admin.shipping-zones.index')
            ->with('success', "Rates updated for {$geoZone->name}.");
    }

    private function syncRates(GeoZone $zone, array $ratesData): void
    {
        foreach ($ratesData as $groupKey => $rows) {
            $customerGroupId = ($groupKey === 'default') ? null : (int) $groupKey;

            foreach ($rows as $row) {
                if (!isset($row['weight_from']) || $row['weight_from'] === '' || !isset($row['cost']) || $row['cost'] === '') {
                    continue;
                }

                ShippingZoneRate::create([
                    'geo_zone_id' => $zone->id,
                    'shipping_zone_id' => null,
                    'customer_group_id' => $customerGroupId,
                    'weight_from' => $row['weight_from'],
                    'weight_to' => $row['weight_to'] ?? null,
                    'cost' => $row['cost'],
                    'free_shipping_threshold' => !empty($row['free_shipping_threshold']) ? $row['free_shipping_threshold'] : null,
                ]);
            }
        }
    }

    private function buildRatesFromZone(GeoZone $zone, $customerGroups): array
    {
        $rates = ['default' => []];
        foreach ($customerGroups as $group) {
            $rates[(string) $group->id] = [];
        }

        foreach ($zone->rates as $rate) {
            $key = $rate->customer_group_id ? (string) $rate->customer_group_id : 'default';
            $rates[$key][] = [
                'weight_from' => (float) $rate->weight_from,
                'weight_to' => $rate->weight_to !== null ? (float) $rate->weight_to : null,
                'cost' => (float) $rate->cost,
                'free_shipping_threshold' => $rate->free_shipping_threshold !== null ? (float) $rate->free_shipping_threshold : null,
            ];
        }

        return $rates;
    }
}
