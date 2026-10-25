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
            // A switched-off variant is not for sale, so the page never hears of it.
            'variants' => fn ($query) => $query->where('is_active', true),
        ]);

        // Offer only the values a switched-on variant actually uses: a value
        // left in the picker whose variants are all off would lead the
        // shopper to a choice that cannot be bought.
        if ($product->has_variants) {
            $product->loadMissing('variants.values');
            $offeredValueIds = $product->variants->flatMap(fn ($variant) => $variant->values->pluck('id'))->unique();
            foreach ($product->variantTypes as $type) {
                $type->setRelation('values', $type->values->whereIn('id', $offeredValueIds)->values());
            }
            $product->setRelation('variantTypes', $product->variantTypes->filter(fn ($type) => $type->values->isNotEmpty())->values());
        }

        if (\App\Support\Reviews::enabled()) {
            $product->load('approvedReviews.images');
        }

        // Increment views
        $product->increment('views');

        // What the shopper can actually buy. A parent's stored quantity is the
        // sum of all its variants, switched-off ones included, so the page
        // counts only the variants it offers. Display only: never saved.
        if ($product->has_variants) {
            $product->setAttribute('quantity', (int) $product->variants->sum('quantity'));
        }

        // The price the cart will charge for one unit, and the special behind
        // it, chosen the way the cart chooses (the default customer group's
        // for guests). A sale special shows the regular price struck
        // through; any other special is a plain price.
        $customerGroupId = CartService::customerGroupId();
        $displayPrice = \App\Support\Pricing::display($product);
        $activeSpecial = $displayPrice->isSale() ? $displayPrice->special : null;
        $overrideSpecial = $activeSpecial === null ? $displayPrice->special : null;

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
                ->with('images', 'specials', \App\Support\ProductAttributes::CARD_RELATION)
                ->when(\App\Support\Reviews::enabled(), fn ($rq) => $rq

                    ->withAvg(['reviews as reviews_avg_rating' => fn($q) => $q->where('status', 'approved')], 'rating')

                    ->withCount(['reviews as reviews_count' => fn($q) => $q->where('status', 'approved')]))
                ->inRandomOrder()
                ->limit(8)
                ->get();
        }

        if (in_array('same_brand', $belowModules) && $product->brand_id) {
            $sameBrandProducts = Product::where('is_active', true)
                ->where('id', '!=', $product->id)
                ->where('brand_id', $product->brand_id)
                ->with('images', 'specials', \App\Support\ProductAttributes::CARD_RELATION)
                ->when(\App\Support\Reviews::enabled(), fn ($rq) => $rq

                    ->withAvg(['reviews as reviews_avg_rating' => fn($q) => $q->where('status', 'approved')], 'rating')

                    ->withCount(['reviews as reviews_count' => fn($q) => $q->where('status', 'approved')]))
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

                // Variant price = its own absolute (already includes the modifier).
                $regularVariantPrice = (float) ($variant->price ?? $product->price);

                // A product special ripples through the variant's modifier,
                // exactly as the cart charges it:
                //   effective_variant = special.price + (variant.price - product.price)
                // So a 720 to 500 product sale on variants priced 720/1000/1280
                // yields 500/780/1060, not a flat 500 across all variants.
                $variantPrice = CartService::resolvePrice($product, $regularVariantPrice, 1, $customerGroupId);

                $variantMatrix[$key] = [
                    'variant_id' => $variant->id,
                    'sku' => $variant->sku,
                    'price' => (float) $variantPrice,
                    'regular_price' => (float) $regularVariantPrice,
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

        // Review data (empty without the reviews extension: no stars,
        // no review section, no error)
        $approvedReviews = \App\Support\Reviews::enabled() ? $product->approvedReviews : collect();
        $averageRating = $approvedReviews->avg('rating') ?: 0;
        $reviewCount = $approvedReviews->count();

        // The effective price for the addToCart component.
        $effectivePrice = $displayPrice->price;

        // Quantity price tiers for the current customer group — one row per
        // quantity threshold (matching CartService::resolvePrice tie-break),
        // sorted ascending. Drives the tier table + reactive price on the page.
        $priceTiers = $product->specials
            ->filter(function ($s) use ($customerGroupId) {
                if (! $s->isActiveAt()) return false;
                if ($s->customer_group_id !== null && $s->customer_group_id != $customerGroupId) return false;
                return true;
            })
            ->groupBy('quantity')
            ->map(fn ($g) => $g->sortBy([['priority', 'asc'], ['price', 'asc']])->first())
            ->sortBy('quantity')
            ->map(fn ($s) => ['qty' => (int) $s->quantity, 'price' => (float) $s->price])
            ->values()
            ->all();

        $hasFreeShipping = app(\App\Services\BadgeService::class)->getBadgesForProduct($product);
        $hasFreeShipping = collect($hasFreeShipping)->where('type', 'free_shipping')->isNotEmpty();

        $ogTitle = $product->og_title ?: null;
        $ogDescription = $product->og_description ?: null;

        view()->share('canonical', route('product.show', $product->slug));

        $attributeGroups = \App\Support\ProductAttributes::groups($product);

        $page = compact(
            'attributeGroups',
            'product', 'activeSpecial', 'overrideSpecial', 'displayPrice', 'settings',
            'relatedProducts', 'sameBrandProducts',
            'breadcrumbs', 'belowModules',
            'variantMatrix', 'variantTypes', 'effectivePrice', 'priceTiers',
            'valueStockMap', 'approvedReviews', 'averageRating', 'reviewCount',
            'hasFreeShipping', 'ogTitle', 'ogDescription'
        );

        // The page's pieces may be widgets in a theme's product region; they
        // read the same bundle the page view gets.
        \App\Support\PageContext::set($page);

        return view('product.show', $page);
    }

    /**
     * The quick view fragment. The theme owns the view; a theme without one
     * has no quick view, and this answers 404 rather than half a page.
     */
    public function quickView(Product $product)
    {
        abort_unless($product->is_active, 404);
        abort_unless(view()->exists('product.quick-view'), 404);

        $product->load(['images', 'specials', 'brand']);

        return view('product.quick-view', ['product' => $product]);
    }
}
