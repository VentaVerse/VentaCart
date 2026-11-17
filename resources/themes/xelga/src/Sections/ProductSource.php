<?php

namespace Themes\Xelga\Sections;

use App\Models\Product;
use App\Support\ProductFeed;
use Illuminate\Database\Eloquent\Collection;

/**
 * The one place the theme asks core for a list of products: core's product
 * feed, which keeps to active products, applies the customer-group
 * visibility scope and loads what a card needs. This class only turns a
 * section's settings into the feed's criteria. Prices are never worked out
 * here; a card asks Pricing::display() for the amount the cart charges.
 */
final class ProductSource
{
    /** The section setting's source, as the feed's filter. */
    public const SOURCES = [
        'newest' => 'new',
        'best-sellers' => 'best_sellers',
        'on-sale' => 'on_sale',
        'most-viewed' => 'most_viewed',
        'category' => 'new',
        'brand' => 'new',
        'hand-picked' => 'new',
    ];

    /**
     * @param  array{filter?: string, ids?: list<int>, category?: int, brand?: int, limit?: int, variants?: bool}  $criteria
     * @return Collection<int, Product>
     */
    public static function get(array $criteria): Collection
    {
        return ProductFeed::get($criteria);
    }

    /**
     * The criteria for a product section's settings: where the products come
     * from and how many. Null when the section has nothing to ask for (a
     * category, brand or hand-picked source with nothing chosen).
     *
     * @param  array<string, mixed>  $settings
     */
    public static function criteriaFor(array $settings, int $limit, bool $variants = false): ?array
    {
        $source = (string) ($settings['source'] ?? 'newest');
        $criteria = [
            'filter' => self::SOURCES[$source] ?? 'new',
            'limit' => max(ProductFeed::LIMIT_MIN, min(ProductFeed::LIMIT_MAX, $limit)),
            'variants' => $variants,
        ];

        switch ($source) {
            case 'category':
                if (($id = self::id($settings['category'] ?? null)) === null) {
                    return null;
                }
                $criteria['category'] = $id;
                break;
            case 'brand':
                if (($id = self::id($settings['brand'] ?? null)) === null) {
                    return null;
                }
                $criteria['brand'] = $id;
                break;
            case 'hand-picked':
                $ids = ProductFeed::parseIds(array_values((array) ($settings['products'] ?? [])));
                if ($ids === []) {
                    return null;
                }
                $criteria['ids'] = $ids;
                break;
        }

        return $criteria;
    }

    /** A positive whole number given as a number or digits, else null. */
    public static function id(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }
        if (is_string($value) && preg_match('/^[1-9][0-9]{0,17}$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }
}
