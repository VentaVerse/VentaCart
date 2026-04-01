<?php

namespace Extensions\PhilippineAddress\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PhAddressSettingsController extends Controller
{
    public function index()
    {
        $enabled = Setting::where('group', 'extensions')
            ->where('key', 'ph_address_enabled')
            ->value('value') !== '0';

        $linkedCountryId = Setting::where('group', 'ph_address')
            ->where('key', 'country_id')
            ->value('value');

        $countries = \App\Models\Country::orderBy('sort_order')->orderBy('name')->get(['id', 'name']);

        $stats = [
            'regions' => DB::table('ph_regions')->count(),
            'provinces' => DB::table('ph_provinces')->count(),
            'cities' => DB::table('ph_cities')->count(),
            'barangays' => DB::table('ph_barangays')->count(),
        ];

        return view('ph-address::settings', compact('enabled', 'linkedCountryId', 'countries', 'stats'));
    }

    public function toggle(Request $request)
    {
        $enabled = $request->boolean('enabled');

        Setting::updateOrCreate(
            ['group' => 'extensions', 'key' => 'ph_address_enabled'],
            ['value' => $enabled ? '1' : '0']
        );

        return redirect()->route('admin.ph-address.settings')
            ->with('success', 'Philippine Address extension ' . ($enabled ? 'enabled' : 'disabled') . '.');
    }

    public function linkCountry(Request $request)
    {
        $validated = $request->validate([
            'country_id' => 'nullable|integer|exists:countries,id',
        ]);

        Setting::updateOrCreate(
            ['group' => 'ph_address', 'key' => 'country_id'],
            ['value' => $validated['country_id'] ?: null]
        );

        return redirect()->route('admin.ph-address.settings')
            ->with('success', 'Country link updated.');
    }
}
