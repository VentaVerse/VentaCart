<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\BadgeService;
use App\Support\Pricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecentlyViewedController extends Controller
{
    public function index(Request $request, BadgeService $badgeService): JsonResponse
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
            // Ratings belong to the reviews extension; without it there are none.
            ->when(\App\Support\Reviews::enabled(), fn ($rq) => $rq
                ->withAvg(['reviews as reviews_avg_rating' => fn ($q) => $q->where('status', 'approved')], 'rating')
                ->withCount(['reviews as reviews_count' => fn ($q) => $q->where('status', 'approved')]))
            ->get()
            ->map(function ($product) use ($badgeService) {
                $galleryImage = $product->images->sortBy('sort_order')->first();
                $image = $product->image ? (object)['path' => $product->image] : $galleryImage;
                // What the cart will charge, and the special behind it.
                $pricing = Pricing::display($product);
                $salePrice = $pricing->isSale() ? $pricing->price : null;
                $overridePrice = ! $pricing->isSale() && $pricing->special !== null ? $pricing->price : null;
                $displayPrice = $pricing->isSale() ? $pricing->regularPrice : $pricing->price;

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'price' => $displayPrice,
                    'sale_price' => $salePrice,
                    'override_price' => $overridePrice,
                    'formatted_price' => money($displayPrice),
                    'formatted_sale_price' => $salePrice !== null ? money($salePrice) : null,
                    'pricing' => $pricing->toArray(),
                    'url' => route('product.show', $product->slug),
                    'image' => $image ? storage_url($image->path) : null,
                    'badges' => $badgeService->getBadgesForProduct($product),
                    'reviews_avg' => $product->reviews_avg_rating ? round($product->reviews_avg_rating, 1) : null,
                    'reviews_count' => (int) $product->reviews_count,
                ];
            });

        // Maintain the order from $ids
        $ordered = collect($ids)->map(function ($id) use ($products) {
            return $products->firstWhere('id', $id);
        })->filter()->values();

        return response()->json($ordered);
    }
}
