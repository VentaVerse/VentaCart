<?php

namespace Extensions\PhilippineAddress\Http\Controllers;

use App\Http\Controllers\Controller;
use Extensions\PhilippineAddress\Models\PhBarangay;
use Extensions\PhilippineAddress\Models\PhCity;
use Extensions\PhilippineAddress\Models\PhProvince;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhAddressLookupController extends Controller
{
    public function provinces(): JsonResponse
    {
        $provinces = PhProvince::orderBy('name')->get(['id', 'name']);
        return response()->json($provinces);
    }

    public function cities(Request $request): JsonResponse
    {
        $request->validate(['province_id' => 'required|integer']);

        $cities = PhCity::where('ph_province_id', $request->province_id)
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get(['id', 'name', 'zip_code']);

        return response()->json($cities);
    }

    public function districts(Request $request): JsonResponse
    {
        $request->validate(['city_id' => 'required|integer']);

        $districts = PhCity::where('parent_id', $request->city_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($districts);
    }

    public function barangays(Request $request): JsonResponse
    {
        $request->validate(['city_id' => 'required|integer']);

        $barangays = PhBarangay::where('ph_city_id', $request->city_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($barangays);
    }
}
