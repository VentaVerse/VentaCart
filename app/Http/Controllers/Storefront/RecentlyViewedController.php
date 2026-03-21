<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecentlyViewedController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);

        if (empty($ids) || !is_array($ids)) {
            return response()->json([]);
        }

        // Limit to 12, sanitize to integers
        $ids = array_slice(array_map('intval', $ids), 0, 12);

        $products = Product::where('is_active', true)
            ->whereIn('id', $ids)
            ->with(['images', 'specials'])
            ->get()
            ->map(function ($product) {
                $image = $product->images->sortBy('sort_order')->first();
                $special = $product->specials
                    ->where('display_mode', 'sale')
                    ->filter(function ($s) {
                        return (!$s->date_start || $s->date_start <= now())
                            && (!$s->date_end || $s->date_end >= now());
                    })
                    ->sortBy('priority')
                    ->first();

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'price' => (float) $product->price,
                    'sale_price' => $special ? (float) $special->price : null,
                    'url' => route('product.show', $product->slug),
                    'image' => $image ? asset('storage/' . $image->path) : null,
                ];
            });

        // Maintain the order from $ids
        $ordered = collect($ids)->map(function ($id) use ($products) {
            return $products->firstWhere('id', $id);
        })->filter()->values();

        return response()->json($ordered);
    }
}
