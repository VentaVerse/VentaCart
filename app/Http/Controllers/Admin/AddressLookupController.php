<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Zone;
use App\Services\Address\DeepAddressData;
use Illuminate\Http\JsonResponse;
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

    /**
     * Deep sub-levels (e.g. PH city / district / barangay), the admin's own
     * authenticated twin of the public storefront route
     * (Storefront\DeepAddressLookupController::level). Same country, same
     * level key, same parent contract, same response shape, because both
     * delegate to the SAME service: the data logic lives once, in
     * App\Services\Address\DeepAddressData, reading the bundled files under
     * database/data/. An admin form must never have to call a public
     * storefront route to fill a dropdown.
     */
    public function deep(string $country, string $level, Request $request, DeepAddressData $data): JsonResponse
    {
        return response()->json(
            $data->level($country, $level, $request->query('parent'))
        );
    }
}
