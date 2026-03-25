<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\BadgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        $currency = \App\Models\Setting::where('key', 'currency_symbol')->value('value') ?? '₱';

        $products = Product::where('is_active', true)
            ->whereIn('id', $ids)
            ->with(['images', 'specials'])
            ->withAvg(['reviews as reviews_avg_rating' => fn($q) => $q->where('status', 'approved')], 'rating')
            ->withCount(['reviews as reviews_count' => fn($q) => $q->where('status', 'approved')])
            ->get()
            ->map(function ($product) use ($badgeService, $currency) {
                $galleryImage = $product->images->sortBy('sort_order')->first();
                $image = $product->image ? (object)['path' => $product->image] : $galleryImage;
                $customerGroupId = Auth::guard('customer')->user()?->customer_group_id;

                $price = (float) $product->price;
                $salePrice = null;
                $overridePrice = null;

                // Sale special (shows strikethrough)
                $activeSpecial = $product->specials
                    ->where('display_mode', 'sale')
                    ->filter(function ($s) use ($customerGroupId) {
                        if ($s->date_start && $s->date_start > now()) return false;
                        if ($s->date_end && $s->date_end < now()) return false;
                        if ($s->customer_group_id !== null && $s->customer_group_id != $customerGroupId) return false;
                        if ($s->quantity > 1) return false;
                        return true;
                    })
                    ->sortBy('priority')
                    ->first();

                if ($activeSpecial) {
                    $salePrice = (float) $activeSpecial->price;
                }

                // Override special (clean price replacement)
                if (!$salePrice) {
                    $overrideSpecial = $product->specials
                        ->where('display_mode', 'override')
                        ->filter(function ($s) use ($customerGroupId) {
                            if ($s->date_start && $s->date_start > now()) return false;
                            if ($s->date_end && $s->date_end < now()) return false;
                            if ($s->customer_group_id !== null && $s->customer_group_id != $customerGroupId) return false;
                            if ($s->quantity > 1) return false;
                            return true;
                        })
                        ->sortBy('priority')
                        ->first();

                    if ($overrideSpecial) {
                        $overridePrice = (float) $overrideSpecial->price;
                    }
                }

                $displayPrice = $overridePrice ?? $price;

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'price' => $displayPrice,
                    'sale_price' => $salePrice,
                    'override_price' => $overridePrice,
                    'formatted_price' => $currency . number_format($displayPrice, 2),
                    'formatted_sale_price' => $salePrice ? $currency . number_format($salePrice, 2) : null,
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
