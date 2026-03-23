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

        $stats = [
            'regions' => DB::table('ph_regions')->count(),
            'provinces' => DB::table('ph_provinces')->count(),
            'cities' => DB::table('ph_cities')->count(),
            'barangays' => DB::table('ph_barangays')->count(),
        ];

        return view('ph-address::settings', compact('enabled', 'stats'));
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
}
