<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\Category;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The storefront's product listing for blocks, carousels and tabs.
 *
 * Every storefront list that is not a category, brand or search page should
 * come from here, so the rules a shopper is held to live in one place:
 *
 *  - only active products;
 *  - only products the viewer may see: the query goes through Product, so the
 *    customer-group visibility scope applies exactly as it does on category
 *    pages (a product kept for one group never reaches a guest);
 *  - prices are not computed here: the products carry their specials, and a
 *    view asks Pricing::display() for the amount the cart will charge.
 *
 * Criteria (all optional; they narrow one another):
 *
 *   filter         'new' (default): newest first, by date available
 *                  'best_sellers':  most revenue from order lines first (the ranking
 *                                   is cached for an hour, see bestSellerIds())
 *                  'on_sale':       only products on sale for the shopper looking (their customer group, one unit;
 *                                 see Product::scopeOnSaleFor())
 *                  'most_viewed':   most viewed first
 *                  'random':        a random pick
 *                  An unknown value is read as 'new', so a stored block with a
 *                  value this class no longer offers still shows products.
 *   ids            list<int> of hand-picked product ids. The products come
 *                  back in the order given; ids that are unknown, inactive or
 *                  hidden from the viewer are dropped. With ids the filter's
 *                  ordering is not used, though 'on_sale' still narrows.
 *                  An empty list returns nothing.
 *   category       int, a category id. A category that is missing or
 *                  switched off returns nothing rather than everything.
 *   subcategories  bool, default true: include the category's descendants.
 *   brand          int, a brand id. Missing or switched off returns nothing.
 *   limit          int, 1 to 48, default 12. Out-of-range values are clamped.
 *   variants       bool, default false: also eager-load the active variants.
 *
 * Returned: an Eloquent Collection of Product, with images, specials, brand
 * and the card attributes loaded, and reviews_avg_rating / reviews_count set
 * while the reviews extension is enabled.
 */
final class ProductFeed
{
    public const FILTERS = ['new', 'best_sellers', 'on_sale', 'most_viewed', 'random'];

    public const LIMIT_MIN = 1;

    public const LIMIT_MAX = 48;

    public const LIMIT_DEFAULT = 12;

    /** The most hand-picked ids one call reads. */
    public const IDS_MAX = 100;

    /**
     * @param  array{filter?: string, ids?: array<int|string>, category?: int|string|null, subcategories?: bool, brand?: int|string|null, limit?: int|string|null, variants?: bool}  $criteria
     * @return Collection<int, Product>
     */
    public static function get(array $criteria = []): Collection
    {
        $limit = self::limit($criteria['limit'] ?? null);
        $filter = in_array($criteria['filter'] ?? null, self::FILTERS, true) ? $criteria['filter'] : 'new';
        $ids = array_key_exists('ids', $criteria) ? self::ids($criteria['ids']) : null;

        if ($ids === []) {
            return new Collection();
        }

        $query = Product::query()
            ->where('is_active', true)
            ->with(self::relations((bool) ($criteria['variants'] ?? false)));

        if (! self::narrowToCategory($query, $criteria) || ! self::narrowToBrand($query, $criteria)) {
            return new Collection();
        }

        if (Reviews::enabled()) {
            $query->withAvg(['reviews as reviews_avg_rating' => fn ($q) => $q->where('status', 'approved')], 'rating')
                ->withCount(['reviews as reviews_count' => fn ($q) => $q->where('status', 'approved')]);
        }

        if ($filter === 'on_sale') {
            // On sale for the shopper looking: their customer group, one unit.
            $query->onSaleFor(CartService::customerGroupId());
        }

        if ($ids !== null) {
            $position = array_flip($ids);

            return $query->whereIn('id', $ids)->get()
                ->sortBy(fn (Product $product) => $position[$product->id])
                ->take($limit)
                ->values();
        }

        self::order($query, $filter);

        return $query->limit($limit)->get();
    }

    /** Parse a typed list such as "12, 7 31" into ids, keeping order and dropping repeats. @return list<int> */
    public static function parseIds(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        return self::ids($value);
    }

    private static function limit(mixed $value): int
    {
        if (! is_numeric($value)) {
            return self::LIMIT_DEFAULT;
        }

        return max(self::LIMIT_MIN, min(self::LIMIT_MAX, (int) $value));
    }

    /** @return list<int> */
    private static function ids(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $id) {
            if ((is_int($id) || (is_string($id) && ctype_digit($id))) && (int) $id > 0) {
                $out[(int) $id] = true;
            }
            if (count($out) >= self::IDS_MAX) {
                break;
            }
        }

        return array_keys($out);
    }

    /** @return array<int|string, mixed> */
    private static function relations(bool $variants): array
    {
        $with = ['images', 'specials', 'brand', ProductAttributes::CARD_RELATION];

        if ($variants) {
            $with['variants'] = fn ($q) => $q->where('is_active', true);
        }

        return $with;
    }

    /** False when the named category cannot be shown, which means no products at all. */
    private static function narrowToCategory(Builder $query, array $criteria): bool
    {
        $id = $criteria['category'] ?? null;
        if ($id === null || $id === '') {
            return true;
        }

        $category = is_numeric($id)
            ? Category::whereKey((int) $id)->where('is_active', true)->first()
            : null;

        if (! $category) {
            return false;
        }

        $ids = ($criteria['subcategories'] ?? true) ? $category->selfAndDescendantIds() : [$category->id];
        $query->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $ids));

        return true;
    }

    /** False when the named brand cannot be shown, which means no products at all. */
    private static function narrowToBrand(Builder $query, array $criteria): bool
    {
        $id = $criteria['brand'] ?? null;
        if ($id === null || $id === '') {
            return true;
        }

        $exists = is_numeric($id) && Brand::whereKey((int) $id)->where('is_active', true)->exists();
        if (! $exists) {
            return false;
        }

        $query->where('brand_id', (int) $id);

        return true;
    }

    /** Products ranked for best sellers; the rest follow by views. */
    public const BEST_SELLERS_RANKED = 500;

    /** How long the best-seller ranking is kept before it is summed again. */
    public const BEST_SELLERS_TTL = 3600;

    public const BEST_SELLERS_CACHE_KEY = 'product_feed_best_seller_ids';

    /**
     * Product ids by revenue from order lines, highest first, for the whole
     * store. Summing every order line is the one costly part of the list
     * and the answer is the same for every shopper, so it is cached for an
     * hour (a sale moves a product up at most that much later) and kept for
     * the request.
     *
     * @return list<int>
     */
    public static function bestSellerIds(): array
    {
        return RequestCache::remember(self::BEST_SELLERS_CACHE_KEY, fn () => Cache::remember(self::BEST_SELLERS_CACHE_KEY, self::BEST_SELLERS_TTL, function () {
            $prefix = DB::getTablePrefix();

            return OrderProduct::query()
                ->whereNotNull('product_id')
                ->groupBy('product_id')
                ->havingRaw("SUM({$prefix}order_products.price * {$prefix}order_products.quantity) > 0")
                ->orderByRaw("SUM({$prefix}order_products.price * {$prefix}order_products.quantity) DESC")
                ->orderByDesc('product_id')
                ->limit(self::BEST_SELLERS_RANKED)
                ->pluck('product_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }));
    }

    /**
     * Order a product query by best sellers: the ranked products first, in
     * the order of bestSellerIds(), then the rest by views, then the newest
     * id, so the order is the same on every page of a listing.
     *
     * The ranking comes from a cached list, so a page never sums the
     * store's order lines; the query's own filters (visibility, category,
     * brand) still apply.
     */
    public static function orderByBestSellers(Builder $query): Builder
    {
        $ranked = self::bestSellerIds();
        if ($ranked !== []) {
            $column = $query->getQuery()->getGrammar()->wrap($query->getModel()->getQualifiedKeyName());
            $bindings = [];
            foreach ($ranked as $position => $id) {
                array_push($bindings, $id, $position);
            }
            $bindings[] = count($ranked);
            $query->orderByRaw("CASE {$column} " . str_repeat('WHEN ? THEN ? ', count($ranked)) . 'ELSE ? END', $bindings);
        }

        // Everything that has sold nothing has sold the same amount, so a
        // store with no orders yet would return whatever the database felt
        // like. Views break the tie, which is the closest thing to a seller
        // a new shop has.
        return $query->orderByDesc($query->qualifyColumn('views'))->orderByDesc($query->qualifyColumn('id'));
    }

    /** Order a product query by views, most first, then the newest id. */
    public static function orderByMostViewed(Builder $query): Builder
    {
        return $query->orderByDesc($query->qualifyColumn('views'))->orderByDesc($query->qualifyColumn('id'));
    }

    private static function order(Builder $query, string $filter): void
    {
        switch ($filter) {
            case 'best_sellers':
                self::orderByBestSellers($query);
                break;
            case 'most_viewed':
                self::orderByMostViewed($query);
                break;
            case 'random':
                $query->inRandomOrder();
                break;
            case 'on_sale':
                // On sale narrows; the list itself reads newest first.
                $query->latest('date_available')->orderByDesc('id');
                break;
            default:
                $query->whereNotNull('date_available')->latest('date_available')->orderByDesc('id');
        }
    }
}
