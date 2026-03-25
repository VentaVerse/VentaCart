<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\StockNotification;
use App\Services\TurnstileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockNotificationController extends Controller
{
    public function store(Request $request, TurnstileService $turnstile): JsonResponse
    {
        if (!$turnstile->verify($request->input('cf-turnstile-response'), $request->ip())) {
            return response()->json([
                'success' => false,
                'message' => 'Captcha verification failed. Please try again.',
            ], 422);
        }

        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'variant_id' => ['nullable', 'exists:product_variants,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $exists = StockNotification::where('email', $validated['email'])
            ->where('product_id', $validated['product_id'])
            ->where('product_variant_id', $validated['variant_id'] ?? null)
            ->where('status', 'waiting')
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => "You're already on the waitlist for this product.",
            ], 409);
        }

        StockNotification::create([
            'store_id' => (int) config('app.store_id', 1),
            'product_id' => $validated['product_id'],
            'product_variant_id' => $validated['variant_id'] ?? null,
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        return response()->json([
            'success' => true,
            'message' => "We'll notify you when this item is back in stock!",
        ]);
    }
}
