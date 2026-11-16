<?php

namespace App\Support;

use App\Services\ThemeService;
use Illuminate\Database\Eloquent\Builder;

/**
 * How storefront product listings (category, brand and search pages) are
 * sorted and paged.
 *
 * SORTS is the only list of sort keys. The admin's "Default sort" select,
 * the storefront sort dropdowns and the controllers all read it, so a key the
 * admin can save is always one the storefront knows how to apply.
 *
 * Settings > Catalog stores the store-wide choices in the category_page
 * group. A sort asked for in the request still wins over the stored default;
 * a stored value that is missing or no longer valid falls back instead of
 * failing the page.
 */
final class ProductListing
{
    /** Sort key => label shown to the merchant and the shopper. */
    public const SORTS = [
        'date_added' => 'Date Added',
        'newest' => 'Newest',
        'name_asc' => 'Name A-Z',
        'name_desc' => 'Name Z-A',
        'price_asc' => 'Price: Low to High',
        'price_desc' => 'Price: High to Low',
    ];

    public const DEFAULT_SORT = 'date_added';

    /**
     * Search results only: best match first. It is not offered as the
     * store-wide default because category and brand pages have no search
     * term to match against.
     */
    public const RELEVANCE = 'relevance';

    /** The range the admin's "Products per page" field allows. */
    public const PER_PAGE_MIN = 4;

    public const PER_PAGE_MAX = 60;

    public const PER_PAGE_DEFAULT = 12;

    private const SETTINGS_GROUP = 'category_page';

    /** @return array<string, string> */
    public static function sorts(): array
    {
        return self::SORTS;
    }

    public static function isSort(mixed $key): bool
    {
        return is_string($key) && array_key_exists($key, self::SORTS);
    }

    /** The merchant's default sort, or date added when unset or unknown. */
    public static function defaultSort(): string
    {
        $stored = self::settings()['default_sort'] ?? null;

        return self::isSort($stored) ? $stored : self::DEFAULT_SORT;
    }

    /**
     * The sort for this request: the shopper's choice when it is a known key,
     * otherwise $fallback (the merchant's default when not given).
     */
    public static function sort(mixed $requested, ?string $fallback = null): string
    {
        if (self::isSort($requested)) {
            return $requested;
        }

        return $fallback ?? self::defaultSort();
    }

    /** The search page's sort: any listing sort, or relevance by default. */
    public static function searchSort(mixed $requested): string
    {
        return $requested === self::RELEVANCE ? self::RELEVANCE : self::sort($requested, self::RELEVANCE);
    }

    /**
     * Products per page: the merchant's setting, else the active theme's own
     * listing size, else 12. Always within the admin field's range, so an old
     * or hand-edited value can neither empty the page nor load hundreds of
     * products in one request.
     */
    public static function perPage(): int
    {
        $stored = self::settings()['products_per_page'] ?? null;

        if (! is_numeric($stored)) {
            $stored = ThemeOptions::get('listing_per_page');
        }

        $perPage = is_numeric($stored) ? (int) $stored : self::PER_PAGE_DEFAULT;

        return max(self::PER_PAGE_MIN, min(self::PER_PAGE_MAX, $perPage));
    }

    /**
     * Order $query by a sort key. Relevance (and anything unknown) lists the
     * newest products first, which is what search has always done.
     */
    public static function applySort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'date_added' => $query->latest('date_available'),
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'name_asc' => $query->orderBy('name', 'asc'),
            'name_desc' => $query->orderBy('name', 'desc'),
            'newest' => $query->latest(),
            default => $query->latest(),
        };
    }

    /** @return array<string, mixed> */
    private static function settings(): array
    {
        return app(ThemeService::class)->getGroup(self::SETTINGS_GROUP);
    }
}
