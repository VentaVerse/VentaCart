<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

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

    // PH Address extension endpoints — these delegate to extension models
    // via the service container bindings. Return empty if extension not installed.

    public function phProvinces()
    {
        if (!Schema::hasTable('ph_provinces')) {
            return response()->json([]);
        }

        $model = app(\Extensions\PhilippineAddress\Models\PhProvince::class);
        return response()->json($model->newQuery()->orderBy('name')->get(['id', 'name']));
    }

    public function phCities(Request $request)
    {
        $request->validate(['province_id' => 'required|integer']);

        if (!Schema::hasTable('ph_cities')) {
            return response()->json([]);
        }

        $model = app(\Extensions\PhilippineAddress\Models\PhCity::class);
        return response()->json(
            $model->newQuery()
                ->where('ph_province_id', $request->province_id)
                ->whereNull('parent_id')
                ->orderBy('name')
                ->get(['id', 'name', 'zip_code'])
        );
    }

    public function phDistricts(Request $request)
    {
        $request->validate(['city_id' => 'required|integer']);

        if (!Schema::hasTable('ph_cities')) {
            return response()->json([]);
        }

        $model = app(\Extensions\PhilippineAddress\Models\PhCity::class);
        return response()->json(
            $model->newQuery()
                ->where('parent_id', $request->city_id)
                ->orderBy('name')
                ->get(['id', 'name'])
        );
    }

    public function phBarangays(Request $request)
    {
        $request->validate(['city_id' => 'required|integer']);

        if (!Schema::hasTable('ph_barangays')) {
            return response()->json([]);
        }

        $model = app(\Extensions\PhilippineAddress\Models\PhBarangay::class);
        return response()->json(
            $model->newQuery()
                ->where('ph_city_id', $request->city_id)
                ->orderBy('name')
                ->get(['id', 'name'])
        );
    }
}
