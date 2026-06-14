<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Address\DeepAddressData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public reference lookups for deep address levels (e.g. PH city/barangay),
 * read on-demand from bundled files. One generic endpoint serves every level
 * of every deep country:
 *
 *   GET /address-lookup/deep/{country}/{level}            (root level)
 *   GET /address-lookup/deep/{country}/{level}?parent=ID  (dependent level)
 */
class DeepAddressLookupController extends Controller
{
    public function level(string $country, string $level, Request $request, DeepAddressData $data): JsonResponse
    {
        return response()->json(
            $data->level($country, $level, $request->query('parent'))
        );
    }
}
