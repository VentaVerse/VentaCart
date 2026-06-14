<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
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
        $request->validate(['country_id' => 'required|integer|exists:countries,id']);

        $zones = Zone::where('country_id', $request->country_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return response()->json($zones);
    }
}
