<?php

namespace Themes\Xelga\Sections;

use App\Models\Product;
use App\Services\BadgeService;
use App\Services\WishlistService;
use App\Support\Pricing;
use App\Support\ProductAttributes;
use App\Support\Reviews;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * What the shared product card shows, worked out for a whole list at once
 * so no card asks the database for anything on its own.
 *
 * Every number on a card comes from core: the price and sale from
 * Pricing::display() (the amount the cart charges this shopper), the New
 * badge from core's badge rules, stock from the product's own count, the
 * rating from the review aggregates core's listing loads.
 */
final class ProductCards
{
    /** Variant groups whose values are drawn as color dots. */
    public const SWATCH_GROUPS = ['color', 'colour', 'colors', 'colours', 'fabric', 'finish', 'shade'];

    /** Most dots one card shows before "+3". */
    public const SWATCH_MAX = 5;

    /** @var \WeakMap<object, list<int>>|null wishlist ids, kept for the request they were read in */
    private static ?\WeakMap $wishlist = null;

    /**
     * @param  iterable<Product>  $products  loaded through ProductSource, with images, specials, brand and card attributes
     * @return list<array<string, mixed>>
     */
    public static function for(iterable $products, bool $swatches = false): array
    {
        $products = Collection::make($products)->filter(fn ($p) => $p instanceof Product)->values();
        if ($products->isEmpty()) {
            return [];
        }

        // Core's badge rules can look at categories; load them for the
        // whole list in one query instead of one per card.
        (new \Illuminate\Database\Eloquent\Collection($products->all()))->loadMissing(['categories:id']);
        if ($swatches) {
            (new \Illuminate\Database\Eloquent\Collection($products->all()))->loadMissing(['variantTypes.values']);
        }

        $badges = app(BadgeService::class);
        $wishlist = self::wishlistIds();
        $reviews = Reviews::enabled();
        $hasRoute = Route::has('product.show');

        return $products->map(fn (Product $p) => self::card($p, $badges, $wishlist, $reviews, $hasRoute, $swatches))->all();
    }

    /** @param list<int> $wishlist */
    private static function card(Product $product, BadgeService $badgeService, array $wishlist, bool $reviews, bool $hasRoute, bool $swatches): array
    {
        $price = Pricing::display($product);
        $images = $product->relationLoaded('images') ? $product->images : collect();
        $main = $product->image ?: $images->first()?->path;
        $alt = $images->pluck('path')->first(fn ($path) => $path && $path !== $main);

        $isNew = false;
        foreach ($badgeService->getBadgesForProduct($product) as $badge) {
            if (($badge['type'] ?? null) === 'new') {
                $isNew = true;
            }
        }

        $soldOut = (bool) $product->track_stock && (int) $product->quantity <= 0;
        $count = (int) ($product->reviews_count ?? 0);
        $spec = array_map(fn (array $a) => (string) $a['value'], ProductAttributes::card($product));

        return [
            'id' => (int) $product->id,
            'name' => (string) $product->name,
            'url' => $hasRoute ? route('product.show', $product->slug) : '#',
            'image' => $main ? ['src' => storage_url($main), 'path' => $main] : null,
            'alt_image' => $alt ? ['src' => storage_url($alt), 'path' => $alt] : null,
            'brand' => $product->relationLoaded('brand') && $product->brand && $product->brand->is_active ? (string) $product->brand->name : null,
            'spec' => implode(', ', array_slice(array_filter($spec, fn ($v) => trim($v) !== ''), 0, 2)),
            'price' => money($price->price),
            'regular_price' => money($price->regularPrice),
            'price_value' => $price->price,
            'on_sale' => $price->isSale(),
            'saving' => $price->savingPercent(),
            'ends_at' => $price->endsAt,
            'is_new' => $isNew,
            'sold_out' => $soldOut,
            'has_variants' => (bool) $product->has_variants,
            // The cart refuses less than the product's minimum, or more than tracked stock.
            'min_qty' => max(1, (int) ($product->minimum_quantity ?? 1)),
            'max_qty' => $product->track_stock ? max(1, (int) $product->quantity) : 99,
            'rating' => $reviews && $count > 0 ? round((float) ($product->reviews_avg_rating ?? 0), 1) : null,
            'reviews' => $count,
            'wishlisted' => in_array((int) $product->id, $wishlist, true),
            'swatches' => $swatches ? self::swatches($product) : [],
        ];
    }

    /**
     * The values of the product's color or fabric variant group, as dots.
     *
     * @return array{items: list<array{label: string, key: string}>, more: int}|array{}
     */
    private static function swatches(Product $product): array
    {
        if (! $product->relationLoaded('variantTypes')) {
            return [];
        }
        $group = $product->variantTypes->first(fn ($t) => in_array(mb_strtolower(trim((string) $t->name)), self::SWATCH_GROUPS, true));
        if ($group === null || ! $group->relationLoaded('values')) {
            return [];
        }

        $items = [];
        foreach ($group->values as $value) {
            $label = trim((string) $value->value);
            if ($label !== '') {
                $items[] = ['label' => $label, 'key' => Swatch::key($label)];
            }
        }

        return $items === [] ? [] : [
            'group' => (string) $group->name,
            'items' => array_slice($items, 0, self::SWATCH_MAX),
            'more' => max(0, count($items) - self::SWATCH_MAX),
        ];
    }

    /** @return list<int> */
    private static function wishlistIds(): array
    {
        self::$wishlist ??= new \WeakMap();
        $request = request();
        if (! isset(self::$wishlist[$request])) {
            self::$wishlist[$request] = Route::has('wishlist.add') ? app(WishlistService::class)->ids() : [];
        }

        return self::$wishlist[$request];
    }
}
