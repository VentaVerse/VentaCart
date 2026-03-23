<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Zone;
use App\Services\AddressProviderRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressLookupController extends Controller
{
    public function countries(AddressProviderRegistry $registry): JsonResponse
    {
        $countries = Country::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'iso2', 'name', 'sort_order']);

        // Merge extension-provided countries (e.g. PhilippineAddress provides 'PH')
        foreach ($registry->all() as $provider) {
            if (!$countries->contains('iso2', $provider->countryCode())) {
                $countries->push((object) [
                    'id' => null,
                    'iso2' => $provider->countryCode(),
                    'name' => $provider->countryName(),
                    'sort_order' => 0,
                ]);
            }
        }

        // Sort by sort_order first (lowest first), then alphabetically
        return response()->json($countries->sortBy([['sort_order', 'asc'], ['name', 'asc']])->values());
    }

    public function zones(Request $request): JsonResponse
    {
        $request->validate(['country_code' => 'required|string|size:2']);

        $zones = Zone::where('country_code', $request->country_code)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return response()->json($zones);
    }

    public function providers(AddressProviderRegistry $registry): JsonResponse
    {
        return response()->json($registry->toFrontendManifest());
    }
}
