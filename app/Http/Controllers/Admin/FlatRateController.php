<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FlatShippingRate;
use Illuminate\Http\Request;

class FlatRateController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'geo_zone_id' => ['nullable', 'exists:geo_zones,id'],
            'cost' => ['required', 'numeric', 'min:0'],
        ]);

        FlatShippingRate::create([
            'geo_zone_id' => $validated['geo_zone_id'] ?: null,
            'cost' => $validated['cost'],
            'is_active' => true,
        ]);

        return redirect()->route('admin.shipping-settings.index', ['tab' => 'flat-rate'])
            ->with('success', 'Flat rate added.');
    }

    public function destroy(FlatShippingRate $flatShippingRate)
    {
        $flatShippingRate->delete();

        return redirect()->route('admin.shipping-settings.index', ['tab' => 'flat-rate'])
            ->with('success', 'Flat rate deleted.');
    }
}
