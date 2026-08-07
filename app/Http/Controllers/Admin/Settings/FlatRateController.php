<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use App\Models\FlatShippingRate;
use App\Models\GeoZone;
use Illuminate\Http\Request;

class FlatRateController extends Controller
{
    public function create()
    {
        return view('admin.flat-rates.form', [
            'rate' => null,
            'geoZones' => GeoZone::where('is_active', true)->orderBy('name')->get(),
            'customerGroups' => CustomerGroup::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'geo_zone_id' => ['nullable', 'exists:geo_zones,id'],
            'customer_group_id' => ['nullable', 'exists:customer_groups,id'],
            'cost' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['geo_zone_id'] = $validated['geo_zone_id'] ?: null;
        $validated['customer_group_id'] = $validated['customer_group_id'] ?: null;

        FlatShippingRate::create($validated);

        return redirect()->route('admin.shipping-settings.index', ['tab' => 'flat-rate'])
            ->with('success', 'Flat rate created.');
    }

    public function edit(FlatShippingRate $flatRate)
    {
        return view('admin.flat-rates.form', [
            'rate' => $flatRate,
            'geoZones' => GeoZone::where('is_active', true)->orderBy('name')->get(),
            'customerGroups' => CustomerGroup::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, FlatShippingRate $flatRate)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'geo_zone_id' => ['nullable', 'exists:geo_zones,id'],
            'customer_group_id' => ['nullable', 'exists:customer_groups,id'],
            'cost' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['geo_zone_id'] = $validated['geo_zone_id'] ?: null;
        $validated['customer_group_id'] = $validated['customer_group_id'] ?: null;

        $flatRate->update($validated);

        return redirect()->route('admin.shipping-settings.index', ['tab' => 'flat-rate'])
            ->with('success', 'Flat rate updated.');
    }

    public function destroy(FlatShippingRate $flatRate)
    {
        $flatRate->delete();

        return redirect()->route('admin.shipping-settings.index', ['tab' => 'flat-rate'])
            ->with('success', 'Flat rate deleted.');
    }
}
