<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use App\Models\GeoZone;
use App\Models\ShippingMethod;
use Illuminate\Http\Request;

class ShippingMethodController extends Controller
{
    public function index()
    {
        $methods = ShippingMethod::with('geoZone', 'customerGroup')
            ->withCount('rates')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.shipping-methods.index', compact('methods'));
    }

    public function create()
    {
        return view('admin.shipping-methods.form', [
            'method' => null,
            'geoZones' => GeoZone::where('is_active', true)->orderBy('name')->get(),
            'customerGroups' => CustomerGroup::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateMethod($request);
        $rates = $request->input('rates', []);

        $method = ShippingMethod::create($validated);

        if ($validated['type'] === 'weight_based' && !empty($rates)) {
            $this->syncRates($method, $rates);
        }

        return redirect()->route('admin.shipping-methods.index')
            ->with('success', 'Shipping method created.');
    }

    public function edit(ShippingMethod $shippingMethod)
    {
        return view('admin.shipping-methods.form', [
            'method' => $shippingMethod->load('rates'),
            'geoZones' => GeoZone::where('is_active', true)->orderBy('name')->get(),
            'customerGroups' => CustomerGroup::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, ShippingMethod $shippingMethod)
    {
        $validated = $this->validateMethod($request);
        $rates = $request->input('rates', []);

        $shippingMethod->update($validated);

        if ($validated['type'] === 'weight_based') {
            $this->syncRates($shippingMethod, $rates);
        } else {
            $shippingMethod->rates()->delete();
        }

        return redirect()->route('admin.shipping-methods.index')
            ->with('success', 'Shipping method updated.');
    }

    public function destroy(ShippingMethod $shippingMethod)
    {
        $shippingMethod->delete();

        return redirect()->route('admin.shipping-methods.index')
            ->with('success', 'Shipping method deleted.');
    }

    private function validateMethod(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:flat_rate,weight_based'],
            'geo_zone_id' => ['nullable', 'exists:geo_zones,id'],
            'customer_group_id' => ['nullable', 'exists:customer_groups,id'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['geo_zone_id'] = $validated['geo_zone_id'] ?: null;
        $validated['customer_group_id'] = $validated['customer_group_id'] ?: null;

        return $validated;
    }

    private function syncRates(ShippingMethod $method, array $rates): void
    {
        $method->rates()->delete();

        foreach ($rates as $rate) {
            if (empty($rate['weight_to'])) continue;

            $method->rates()->create([
                'weight_from' => $rate['weight_from'] ?? 0,
                'weight_to' => $rate['weight_to'],
                'cost' => $rate['cost'] ?? 0,
            ]);
        }
    }
}
