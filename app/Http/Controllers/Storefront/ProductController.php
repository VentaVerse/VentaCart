<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ThemeService;
use App\Services\CartService;

class ProductController extends Controller
{
    public function show(Product $product, ThemeService $theme)
    {
        if (!$product->is_active) {
            abort(404);
        }

        $product->load([
            'images',
            'brand',
            'categories',
            'tags',
            'specials',
            'variantTypes.values',
            'variants',
            'approvedReviews.images',
        ]);

        // Increment views
        $product->increment('views');

        // Active special (sale pricing)
        $activeSpecial = $product->specials
            ->where('display_mode', 'sale')
            ->filter(function ($s) {
                return (!$s->date_start || $s->date_start <= now())
                    && (!$s->date_end || $s->date_end >= now());
            })
            ->sortBy('priority')
            ->first();

        // Below-product modules from settings
        $settings = $theme->getGroup('product_page');
        $belowModules = json_decode($settings['below_modules'] ?? '[]', true) ?: [];

        $relatedProducts = collect();
        $sameBrandProducts = collect();

        if (in_array('related', $belowModules) || in_array('you_might_like', $belowModules)) {
            $categoryIds = $product->categories->pluck('id');
            $relatedProducts = Product::where('is_active', true)
                ->where('id', '!=', $product->id)
                ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $categoryIds))
                ->with('images', 'specials')
                ->inRandomOrder()
                ->limit(8)
                ->get();
        }

        if (in_array('same_brand', $belowModules) && $product->brand_id) {
            $sameBrandProducts = Product::where('is_active', true)
                ->where('id', '!=', $product->id)
                ->where('brand_id', $product->brand_id)
                ->with('images', 'specials')
                ->inRandomOrder()
                ->limit(8)
                ->get();
        }

        // Breadcrumb from first category
        $breadcrumbCategory = $product->categories->first();
        $breadcrumbs = [];
        if ($breadcrumbCategory) {
            $current = $breadcrumbCategory;
            while ($current) {
                array_unshift($breadcrumbs, $current);
                $current = $current->parent;
            }
        }

        // Build variant matrix for Alpine.js addToCart component
        $variantMatrix = [];
        $variantTypes = [];
        $valueStockMap = [];
        if ($product->has_variants && $product->variantTypes->isNotEmpty()) {
            $variantTypes = $product->variantTypes->map(function ($type) {
                return [
                    'id' => $type->id,
                    'name' => $type->name,
                    'values' => $type->values->map(function ($v) {
                        return ['id' => $v->id, 'name' => $v->value];
                    })->values()->all(),
                ];
            })->values()->all();

            // Map each value ID to its variant stock (for single-type products)
            $valueStockMap = [];

            foreach ($product->variants as $variant) {
                $valueIds = $variant->values->pluck('id')->sort()->values();
                $key = $valueIds->implode('_');

                // Determine variant price (check for active special on variant)
                $variantPrice = $variant->price ?? $product->price;
                if ($activeSpecial) {
                    $variantPrice = $activeSpecial->price;
                }

                $variantMatrix[$key] = [
                    'variant_id' => $variant->id,
                    'sku' => $variant->sku,
                    'price' => (float) $variantPrice,
                    'quantity' => $variant->quantity,
                    'label' => $variant->values->pluck('value')->implode(' / '),
                ];

                // For single-type variants, map value_id → quantity directly
                if ($product->variantTypes->count() === 1) {
                    foreach ($variant->values as $val) {
                        $valueStockMap[$val->id] = $variant->quantity;
                    }
                }
            }
        }

        // Review data
        $approvedReviews = $product->approvedReviews;
        $averageRating = $approvedReviews->avg('rating') ?: 0;
        $reviewCount = $approvedReviews->count();

        // Determine the effective price for the addToCart component
        $effectivePrice = $activeSpecial ? (float) $activeSpecial->price : (float) $product->price;

        return view('product.show', compact(
            'product', 'activeSpecial', 'settings',
            'relatedProducts', 'sameBrandProducts',
            'breadcrumbs', 'belowModules',
            'variantMatrix', 'variantTypes', 'effectivePrice',
            'valueStockMap', 'approvedReviews', 'averageRating', 'reviewCount'
        ));
    }
}
