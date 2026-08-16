<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\GeoZone;
use App\Models\GeoZoneEntry;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LocalizationController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->input('tab', session('tab', 'countries'));

        $countries = Country::withCount('zones')->orderBy('sort_order')->orderBy('name')->get();

        $selectedCountry = (int) $request->input('country', Country::where('iso2', 'PH')->value('id'));
        $zones = Zone::where('country_id', $selectedCountry)->orderBy('name')->get();
        $countryList = Country::orderBy('sort_order')->orderBy('name')->pluck('name', 'id');

        $geoZones = GeoZone::whereNull('source')->withCount('entries')->orderBy('sort_order')->get();

        return view('admin.settings.localization.index', compact(
            'tab', 'countries', 'zones', 'selectedCountry', 'countryList', 'geoZones'
        ));
    }

    // --- Countries ---

    public function toggleCountry(Country $country)
    {
        $country->update(['is_active' => !$country->is_active]);
        $message = $country->name . ' ' . ($country->is_active ? 'enabled' : 'disabled') . '.';

        if (request()->wantsJson()) {
            return response()->json([
                'is_active' => $country->is_active,
                'message' => $message,
            ]);
        }

        return redirect()->route('admin.localization.index', ['tab' => 'countries'])->with('success', $message);
    }

    public function updateCountry(Request $request, Country $country)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'iso2' => ['required', 'string', 'size:2'],
            'iso3' => ['required', 'string', 'size:3'],
            'phone_code' => ['nullable', 'string', 'max:10'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $country->update($validated);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'message' => $country->name . ' updated.']);
        }

        return redirect()->route('admin.localization.index', ['tab' => 'countries'])->with('success', $country->name . ' updated.');
    }

    // --- Zones ---

    public function toggleZone(Zone $zone)
    {
        $zone->update(['is_active' => !$zone->is_active]);
        $message = $zone->name . ' ' . ($zone->is_active ? 'enabled' : 'disabled') . '.';

        if (request()->wantsJson()) {
            return response()->json([
                'is_active' => $zone->is_active,
                'message' => $message,
            ]);
        }

        return redirect()->route('admin.localization.index', ['tab' => 'zones'])->with('success', $message);
    }

    public function storeZone(Request $request)
    {
        $validated = $request->validate([
            'country_id' => ['required', 'integer', 'exists:countries,id'],
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

        $geoZone = GeoZone::create($validated);

        return redirect()->route('admin.localization.edit-geo-zone', $geoZone)->with('success', 'Geo zone created, now add entries.');
    }

    public function editGeoZone(GeoZone $geoZone)
    {
        $countries = Country::orderBy('sort_order')->orderBy('name')->get();

        // Load existing entries with labels
        $entries = $geoZone->entries()->get()->map(function ($entry) {
            $label = '';
            if ($entry->country_id) {
                $country = Country::find($entry->country_id);
                $label = $country ? $country->name : 'Unknown';
                if ($entry->zone_id) {
                    $zone = Zone::find($entry->zone_id);
                    $label .= ' → ' . ($zone ? $zone->name : 'Unknown');
                } else {
                    $label .= ' (all zones)';
                }
            }
            return [
                'id' => $entry->id,
                'country_id' => $entry->country_id,
                'zone_id' => $entry->zone_id,
                'label' => $label,
            ];
        });

        return view('admin.settings.localization.geo-zone-edit', compact('geoZone', 'countries', 'entries'));
    }

    /**
     * One save commits the whole page (v3 doctrine): the zone's own
     * fields and, when the payload carries them, its entries, in one
     * transaction. The flash reports what was actually persisted;
     * rows with no country are dropped and said out loud, never
     * counted as saved.
     */
    public function updateGeoZone(Request $request, GeoZone $geoZone)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'entries' => ['sometimes', 'array'],
            'entries.*.country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'entries.*.zone_id' => ['nullable', 'integer', 'exists:zones,id'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $saved = 0;
        $dropped = 0;

        DB::transaction(function () use ($request, $geoZone, $validated, &$saved, &$dropped) {
            $geoZone->update(collect($validated)->except('entries')->all());

            if (!$request->has('entries')) {
                return;
            }

            $geoZone->entries()->delete();
            foreach ($request->input('entries', []) as $entry) {
                if (empty($entry['country_id'])) {
                    $dropped++;
                    continue;
                }
                GeoZoneEntry::create([
                    'geo_zone_id' => $geoZone->id,
                    'country_id' => (int) $entry['country_id'],
                    'zone_id' => $entry['zone_id'] ?: null,
                ]);
                $saved++;
            }
        });

        $message = 'Zone saved.';
        if ($request->has('entries')) {
            $message = "Zone saved with {$saved} " . ($saved === 1 ? 'entry' : 'entries') . '.';
            if ($dropped > 0) {
                $message .= " {$dropped} " . ($dropped === 1 ? 'row' : 'rows') . ' without a country were not saved.';
            }
        }

        return back()->with('success', $message);
    }

    public function syncGeoZoneEntries(Request $request, GeoZone $geoZone)
    {
        $request->validate([
            'entries' => ['present', 'array'],
            'entries.*.country_id' => ['nullable', 'integer'],
            'entries.*.zone_id' => ['nullable', 'integer'],
        ]);

        $geoZone->entries()->delete();

        $entries = $request->input('entries', []);
        foreach ($entries as $entry) {
            if (empty($entry['country_id'])) {
                continue;
            }

            GeoZoneEntry::create([
                'geo_zone_id' => $geoZone->id,
                'country_id' => $entry['country_id'] ?: null,
                'zone_id' => $entry['zone_id'] ?: null,
            ]);
        }

        return back()->with('success', count($entries) . ' entries saved for ' . $geoZone->name . '.');
    }

    public function toggleGeoZone(GeoZone $geoZone)
    {
        $geoZone->update(['is_active' => !$geoZone->is_active]);
        $message = $geoZone->name . ' ' . ($geoZone->is_active ? 'enabled' : 'disabled') . '.';

        if (request()->wantsJson()) {
            return response()->json([
                'is_active' => $geoZone->is_active,
                'message' => $message,
            ]);
        }

        return redirect()->route('admin.localization.index', ['tab' => 'geo-zones'])->with('success', $message);
    }

    public function destroyGeoZone(GeoZone $geoZone)
    {
        $geoZone->entries()->delete();
        $geoZone->delete();
        return back()->with('success', 'Geo zone deleted.')->with('tab', 'geo-zones');
    }
}
