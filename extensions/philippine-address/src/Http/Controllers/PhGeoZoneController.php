<?php

namespace Extensions\PhilippineAddress\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\GeoZone;
use App\Models\GeoZoneEntry;
use App\Models\Zone;
use Extensions\PhilippineAddress\Models\PhRegion;
use Illuminate\Http\Request;

class PhGeoZoneController extends Controller
{
    private const SOURCE = 'ph-address';

    public function index()
    {
        $geoZones = GeoZone::where('source', self::SOURCE)
            ->withCount('entries')
            ->orderBy('sort_order')
            ->get();

        return view('ph-address::geo-zones.index', compact('geoZones'));
    }

    public function create()
    {
        $regions = PhRegion::with(['provinces' => function ($q) {
            $q->orderBy('name');
        }])->orderBy('name')->get();

        return view('ph-address::geo-zones.form', [
            'geoZone' => null,
            'regions' => $regions,
            'selectedZoneIds' => [],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'zone_ids' => 'required|array|min:1',
            'zone_ids.*' => 'integer|exists:zones,id',
            'is_active' => 'boolean',
        ]);

        $geoZone = GeoZone::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'source' => self::SOURCE,
        ]);

        $this->syncEntries($geoZone, $validated['zone_ids']);

        return redirect()->route('admin.ph-address.geo-zones.index')
            ->with('success', 'PH geo zone created.');
    }

    public function edit(GeoZone $geoZone)
    {
        abort_unless($geoZone->source === self::SOURCE, 404);

        $regions = PhRegion::with(['provinces' => function ($q) {
            $q->orderBy('name');
        }])->orderBy('name')->get();

        $selectedZoneIds = $geoZone->entries()->whereNotNull('zone_id')->pluck('zone_id')->toArray();

        return view('ph-address::geo-zones.form', compact('geoZone', 'regions', 'selectedZoneIds'));
    }

    public function update(Request $request, GeoZone $geoZone)
    {
        abort_unless($geoZone->source === self::SOURCE, 404);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'zone_ids' => 'required|array|min:1',
            'zone_ids.*' => 'integer|exists:zones,id',
            'is_active' => 'boolean',
        ]);

        $geoZone->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->syncEntries($geoZone, $validated['zone_ids']);

        return redirect()->route('admin.ph-address.geo-zones.index')
            ->with('success', 'PH geo zone updated.');
    }

    public function destroy(GeoZone $geoZone)
    {
        abort_unless($geoZone->source === self::SOURCE, 404);

        $geoZone->entries()->delete();
        $geoZone->delete();

        return redirect()->route('admin.ph-address.geo-zones.index')
            ->with('success', 'PH geo zone deleted.');
    }

    private function syncEntries(GeoZone $geoZone, array $zoneIds): void
    {
        $geoZone->entries()->delete();

        $phCountry = Country::where('iso2', 'PH')->first();
        if (!$phCountry) return;

        foreach ($zoneIds as $zoneId) {
            GeoZoneEntry::create([
                'geo_zone_id' => $geoZone->id,
                'country_id' => $phCountry->id,
                'zone_id' => $zoneId,
            ]);
        }
    }
}
