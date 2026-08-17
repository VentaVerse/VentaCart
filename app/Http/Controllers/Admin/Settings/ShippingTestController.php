<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Services\Shipping\ShippingQuoteService;
use Illuminate\Http\Request;

class ShippingTestController extends Controller
{
    public function __invoke(Request $request, ShippingQuoteService $quotes)
    {
        $data = $request->validate([
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'barangay' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'customer_group_id' => ['nullable', 'integer', 'exists:customer_groups,id'],
            'weight' => ['required', 'numeric', 'min:0.01', 'max:1000'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $item = (object) [
            'weight' => (float) $data['weight'],
            'length' => 10,
            'width' => 10,
            'height' => 10,
            'quantity' => $data['quantity'] ?? 1,
        ];

        // The same address shape the storefront's estimate sends
        // (CartController::estimateShipping), plus the tester's explicit
        // customer group standing in for the logged-in customer's.
        $address = [
            'country_id' => $data['country_id'],
            'province' => $data['province'] ?? '',
            'city' => $data['city'] ?? '',
            'barangay' => $data['barangay'] ?? '',
            'postcode' => $data['postcode'] ?? '',
        ];

        $results = $quotes->getQuotes($address, collect([$item]), (float) $data['subtotal'], [
            'customer_group_id' => $data['customer_group_id'] ?? null,
        ]);

        $freeMatched = collect($results)->contains(fn ($q) => ($q['service_type'] ?? null) === 'free_shipping');

        return back()->with('rate_test', [
            'input' => $data,
            'quotes' => $results,
            'winner' => $results[0]['key'] ?? null,
            'explanation' => $results === []
                ? 'No methods quoted. Check provider enablement, zones, and weight tiers.'
                : ($freeMatched ? 'A free-shipping rule matched. All paid methods are suppressed.' : 'Methods sorted by the quote chain; the customer sees all of these.'),
        ]);
    }
}
