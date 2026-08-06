<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Shipping\ShippingQuoteService;
use Illuminate\Http\Request;

class ShippingTestController extends Controller
{
    public function __invoke(Request $request, ShippingQuoteService $quotes)
    {
        $data = $request->validate([
            'country_id' => ['required', 'integer'],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:20'],
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

        $address = [
            'country_id' => $data['country_id'],
            'province' => $data['province'] ?? '',
            'city' => $data['city'] ?? '',
            'postcode' => $data['postcode'] ?? '',
        ];

        $results = $quotes->getQuotes($address, collect([$item]), (float) $data['subtotal']);

        $freeMatched = collect($results)->contains(fn ($q) => ($q['service_type'] ?? null) === 'free_shipping');

        return back()->with('rate_test', [
            'input' => $data,
            'quotes' => $results,
            'winner' => $results[0]['key'] ?? null,
            'explanation' => $results === []
                ? 'No methods quoted — check provider enablement, zones, and weight tiers.'
                : ($freeMatched ? 'A free-shipping rule matched — all paid methods are suppressed.' : 'Methods sorted by the quote chain; the customer sees all of these.'),
        ]);
    }
}
