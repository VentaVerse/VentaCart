<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\PhBarangay;
use App\Models\PhCity;
use App\Models\PhProvince;
use App\Models\Zone;
use Illuminate\Http\Request;

class AddressLookupController extends Controller
{
    public function countries()
    {
        $countries = Country::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'iso2', 'name']);

        return response()->json($countries);
    }

    public function zones(Request $request)
    {
        $request->validate(['country_code' => 'required|string|size:2']);

        $zones = Zone::where('country_code', $request->country_code)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return response()->json($zones);
    }

    public function phProvinces()
    {
        $provinces = PhProvince::orderBy('name')->get(['id', 'name']);

        return response()->json($provinces);
    }

    public function phCities(Request $request)
    {
        $request->validate(['province_id' => 'required|integer']);

        // Exclude districts (child cities) — they show via phDistricts endpoint
        $cities = PhCity::where('ph_province_id', $request->province_id)
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get(['id', 'name', 'zip_code']);

        return response()->json($cities);
    }

    public function phDistricts(Request $request)
    {
        $request->validate(['city_id' => 'required|integer']);

        $districts = PhCity::where('parent_id', $request->city_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($districts);
    }

    public function phBarangays(Request $request)
    {
        $request->validate(['city_id' => 'required|integer']);

        $barangays = PhBarangay::where('ph_city_id', $request->city_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($barangays);
    }
}
