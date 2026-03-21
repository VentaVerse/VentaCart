<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\GeoZone;
use App\Models\GeoZoneEntry;
use App\Models\PhRegion;
use App\Models\Zone;
use Illuminate\Http\Request;

class LocalizationController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->input('tab', session('tab', 'countries'));

        $countries = Country::orderBy('sort_order')->orderBy('name')->get();

        $selectedCountry = $request->input('country', 'PH');
        $zones = Zone::where('country_code', $selectedCountry)->orderBy('name')->get();
        $countryList = Country::where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name', 'iso2');

        $geoZones = GeoZone::withCount('entries')->orderBy('sort_order')->get();

        $phRegions = PhRegion::with(['provinces' => function ($q) {
            $q->orderBy('name');
        }])->orderBy('name')->get();

        return view('admin.localization.index', compact(
            'tab', 'countries', 'zones', 'selectedCountry', 'countryList', 'geoZones', 'phRegions'
        ));
    }

    // --- Countries ---

    public function toggleCountry(Country $country)
    {
        $country->update(['is_active' => !$country->is_active]);
        return back()->with('success', $country->name . ' ' . ($country->is_active ? 'enabled' : 'disabled') . '.')->with('tab', 'countries');
    }

    // --- Zones ---

    public function storeZone(Request $request)
    {
        $validated = $request->validate([
            'country_code' => ['required', 'string', 'size:2'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:10'],
        ]);

        Zone::create($validated + ['is_active' => true]);

        return back()->with('success', 'Zone added.')->with('tab', 'zones');
    }

    public function updateZone(Request $request, Zone $zone)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:10'],
            'is_active' => ['boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $zone->update($validated);

        return back()->with('success', 'Zone updated.')->with('tab', 'zones');
    }

    public function destroyZone(Zone $zone)
    {
        $zone->delete();
        return back()->with('success', 'Zone deleted.')->with('tab', 'zones');
    }

    // --- Geo Zones ---

    public function storeGeoZone(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = true;

        GeoZone::create($validated);

        return back()->with('success', 'Geo zone created.')->with('tab', 'geo-zones');
    }

    public function editGeoZone(GeoZone $geoZone)
    {
        $phRegions = PhRegion::with('provinces')->orderBy('name')->get();
        $assignedRegionIds = $geoZone->entries()->pluck('ph_region_id')->filter()->toArray();

        return view('admin.localization.geo-zone-edit', compact('geoZone', 'phRegions', 'assignedRegionIds'));
    }

    public function updateGeoZone(Request $request, GeoZone $geoZone)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $geoZone->update($validated);

        return back()->with('success', 'Geo zone updated.');
    }

    public function syncGeoZoneEntries(Request $request, GeoZone $geoZone)
    {
        $regionIds = $request->input('ph_region_ids', []);

        // Delete existing entries and recreate
        $geoZone->entries()->delete();

        foreach ($regionIds as $regionId) {
            GeoZoneEntry::create([
                'geo_zone_id' => $geoZone->id,
                'ph_region_id' => $regionId,
            ]);
        }

        return back()->with('success', 'Region assignments updated for ' . $geoZone->name . '.');
    }

    public function toggleGeoZone(GeoZone $geoZone)
    {
        $geoZone->update(['is_active' => !$geoZone->is_active]);
        return back()->with('success', $geoZone->name . ' ' . ($geoZone->is_active ? 'enabled' : 'disabled') . '.')->with('tab', 'geo-zones');
    }

    public function destroyGeoZone(GeoZone $geoZone)
    {
        $geoZone->entries()->delete();
        $geoZone->delete();
        return back()->with('success', 'Geo zone deleted.')->with('tab', 'geo-zones');
    }
}
