<?php

namespace Themes\Xelga\Sections;

use App\Models\Product;
use App\Services\CartService;
use App\Services\PriceResolver;
use App\Services\WishlistService;
use App\Support\DisplayPrice;
use App\Support\LowStock;
use App\Support\Pricing;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Themes\Xelga\Rendering\RenderContext;
use Themes\Xelga\Rendering\SectionData;

/**
 * The product page's main section: photos, price, variants, stock and the
 * buy button, from the bundle core's ProductController hands the page.
 *
 * No price is worked out here. The page shows Pricing::display() for one
 * unit, and for the product and each variant what Pricing::display()
 * answers at every discount quantity core lists for this shopper's
 * customer group, which is the amount the cart will charge. Each price
 * keeps only the quantities where it steps down, since a quantity that
 * lowers one variant's price may not lower another's. The browser only
 * picks the row for the quantity typed.
 */
final class ProductMain implements SectionData
{
    /** A sale ending further away than this is not a deadline worth counting down. */
    private const COUNTDOWN_MAX_DAYS = 366;

    public function resolve(array $settings, array $blocks, RenderContext $context): array
    {
        $product = $context->get('product', $context->subject);
        if (! $product instanceof Product) {
            return ['product' => null];
        }

        $displayPrice = $context->get('displayPrice');
        if (! $displayPrice instanceof DisplayPrice) {
            $displayPrice = Pricing::display($product);
        }

        $groupId = CartService::customerGroupId();
        $images = self::images($product);
        $variantTypes = self::variantTypes($product);
        $quantities = [1, ...PriceResolver::tierQuantities($product, $groupId)];
        $points = self::steps(self::points($product, null, $quantities));
        $units = self::units($product, $images, $quantities);
        $tiers = self::tierRows($points);
        $trackStock = (bool) $product->track_stock;
        $quantity = (int) $product->quantity;

        $soldOut = $trackStock && ($product->has_variants
            ? ($units !== [] && collect($units)->every(fn ($u) => $u['stock'] <= 0))
            : $quantity <= 0);

        return [
            'product' => $product,
            'images' => $images,
            'brand' => $product->relationLoaded('brand') && $product->brand && $product->brand->is_active ? $product->brand : null,
            'price' => [
                'now' => money($displayPrice->price),
                'was' => money($displayPrice->regularPrice),
                'below_regular' => $displayPrice->isSale(),
                'on_sale' => $displayPrice->isSpecial(),
                'saving' => $displayPrice->savingPercent(),
                'ends_at' => self::countdownEnd($displayPrice),
            ],
            'rating' => [
                'average' => round((float) $context->get('averageRating', 0), 1),
                'count' => (int) $context->get('reviewCount', 0),
            ],
            'short' => self::shortText($product),
            'variant_types' => $variantTypes,
            'has_variants' => (bool) $product->has_variants,
            'unavailable' => (bool) $product->has_variants && $units === [],
            'sold_out' => $soldOut,
            'stock' => self::stockNote($product, $trackStock, $quantity),
            'low_stock' => LowStock::threshold(),
            'tiers' => $tiers,
            // A variant can have volume prices the product's own price has not.
            'any_tiers' => $tiers !== [] || collect($units)->contains(fn ($u) => $u['tiers'] !== []),
            'min_qty' => max(1, (int) ($product->minimum_quantity ?? 1)),
            'max_qty' => $trackStock && ! $product->has_variants ? max(1, $quantity) : null,
            'can_wish' => Route::has('wishlist.add'),
            'wishlisted' => Route::has('wishlist.add') && in_array((int) $product->id, app(WishlistService::class)->ids(), true),
            'can_notify' => Route::has('notify-stock.store'),
            'customer' => self::customer(),
            'share_url' => route('product.show', $product->slug),
            'json' => [
                'id' => (int) $product->id,
                'name' => (string) $product->name,
                'track_stock' => $trackStock,
                'stock' => $quantity,
                'low_stock' => LowStock::threshold(),
                'has_variants' => (bool) $product->has_variants,
                'below_regular' => $displayPrice->isSale(),
                'on_sale' => $displayPrice->isSpecial(),
                'saving' => $displayPrice->savingPercent(),
                'price' => money($displayPrice->price),
                'was' => money($displayPrice->regularPrice),
                'points' => self::formatPoints($points),
                'tiers' => $tiers,
                'units' => $units,
                'types' => array_map(fn ($t) => ['id' => $t['id'], 'name' => $t['name']], $variantTypes),
            ],
        ];
    }

    /**
     * The product's photos in core's order: the main photo first, then the
     * gallery by sort order, then any variant photo not already shown.
     *
     * @return list<array{path: string, src: string, srcset: string, alt: string}>
     */
    public static function images(Product $product): array
    {
        $paths = [];
        if ($product->image) {
            $paths[] = (string) $product->image;
        }
        if ($product->relationLoaded('images')) {
            foreach ($product->images->sortBy('sort_order') as $image) {
                $paths[] = (string) $image->path;
            }
        }
        if ($product->has_variants && $product->relationLoaded('variants')) {
            foreach ($product->variants as $variant) {
                if ($variant->image) {
                    $paths[] = (string) $variant->image;
                }
            }
        }

        $paths = array_values(array_unique(array_filter($paths, fn ($p) => trim($p) !== '')));
        $count = count($paths);

        return array_map(fn (string $path, int $i) => [
            'path' => $path,
            'src' => storage_url($path),
            'srcset' => image_srcset($path),
            'alt' => $count > 1 ? "{$product->name}, photo " . ($i + 1) . " of {$count}" : (string) $product->name,
        ], $paths, array_keys($paths));
    }

    /**
     * The variant groups the page offers (core already left out values no
     * switched-on variant uses), with whether each is a color group.
     *
     * @return list<array{id: int, name: string, color: bool, values: list<array{id: int, label: string, key: string}>}>
     */
    private static function variantTypes(Product $product): array
    {
        if (! $product->has_variants || ! $product->relationLoaded('variantTypes')) {
            return [];
        }

        $out = [];
        foreach ($product->variantTypes as $type) {
            $values = [];
            foreach ($type->values as $value) {
                $label = trim((string) $value->value);
                if ($label !== '') {
                    $values[] = ['id' => (int) $value->id, 'label' => $label, 'key' => Swatch::key($label)];
                }
            }
            if ($values !== []) {
                $name = trim((string) $type->name);
                $out[] = [
                    'id' => (int) $type->id,
                    'name' => $name,
                    'color' => in_array(mb_strtolower($name), ProductCards::SWATCH_GROUPS, true),
                    'values' => $values,
                ];
            }
        }

        return $out;
    }

    /**
     * Every switched-on variant the shopper can choose, keyed the way core's
     * page keys them (value ids in numeric order joined by "_"), with its
     * stock, its photo, and its own price and volume rows at each quantity
     * where its price steps down.
     *
     * @param  list<array{path: string}>  $images
     * @param  list<int>  $quantities
     * @return array<string, array<string, mixed>>
     */
    private static function units(Product $product, array $images, array $quantities): array
    {
        if (! $product->has_variants || ! $product->relationLoaded('variants')) {
            return [];
        }

        $paths = array_column($images, 'path');
        $product->loadMissing('variants.values');

        $out = [];
        foreach ($product->variants as $variant) {
            $ids = $variant->values->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            if ($ids === []) {
                continue;
            }
            $regular = (float) ($variant->price ?? $product->price);
            $points = self::steps(self::points($product, (int) $variant->id, $quantities));
            $image = $variant->image ? array_search((string) $variant->image, $paths, true) : false;

            $out[implode('_', $ids)] = [
                'id' => (int) $variant->id,
                'values' => $ids,
                'label' => $variant->values->pluck('value')->implode(' / '),
                'stock' => (int) $variant->quantity,
                'image' => $image === false ? null : $image,
                'was' => money($regular),
                'points' => self::formatPoints($points),
                'tiers' => self::tierRows($points),
            ];
        }

        return $out;
    }

    /**
     * What the cart charges this shopper for one unit of the product (or of
     * one variant) at each quantity.
     *
     * @param  list<int>  $quantities
     * @return list<array{qty: int, price: float}>
     */
    private static function points(Product $product, ?int $variantId, array $quantities): array
    {
        return array_map(fn (int $qty) => [
            'qty' => $qty,
            'price' => Pricing::display($product, $variantId, $qty)->price,
        ], $quantities);
    }

    /**
     * The first point, then each one whose price is below the point kept
     * before it: the quantities where buying more lowers the price.
     *
     * @param  list<array{qty: int, price: float}>  $points
     * @return list<array{qty: int, price: float}>
     */
    private static function steps(array $points): array
    {
        $out = [];
        foreach ($points as $point) {
            $last = $out[count($out) - 1] ?? null;
            if ($last === null || $point['price'] < $last['price']) {
                $out[] = $point;
            }
        }

        return $out;
    }

    /** @param list<array{qty: int, price: float}> $points */
    private static function formatPoints(array $points): array
    {
        return array_map(fn (array $p) => ['qty' => $p['qty'], 'price' => money($p['price'])], $points);
    }

    /**
     * The volume price rows: each quantity from two up at which the price
     * changes, as "6 to 11" and "12 or more".
     *
     * @param  list<array{qty: int, price: float}>  $points
     * @return list<array{qty: int, label: string, price: string}>
     */
    private static function tierRows(array $points): array
    {
        $rows = array_values(array_filter($points, fn ($p) => $p['qty'] > 1));
        $out = [];
        foreach ($rows as $i => $row) {
            $next = $rows[$i + 1]['qty'] ?? null;
            $out[] = [
                'qty' => $row['qty'],
                'label' => $next === null ? "{$row['qty']} or more" : ($next - 1 === $row['qty'] ? (string) $row['qty'] : "{$row['qty']} to " . ($next - 1)),
                'price' => money($row['price']),
            ];
        }

        return $out;
    }

    private static function countdownEnd(DisplayPrice $price): ?string
    {
        if (! $price->isSpecial() || $price->endsAt === null) {
            return null;
        }
        if ($price->endsAt->isPast() || $price->endsAt->diffInDays(now()) > self::COUNTDOWN_MAX_DAYS) {
            return null;
        }

        return $price->endsAtIso();
    }

    /** The start of the description as plain text. */
    private static function shortText(Product $product): string
    {
        $text = html_entity_decode(strip_tags((string) $product->description), ENT_QUOTES | ENT_HTML5);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** @return array{level: string, count: ?int} */
    private static function stockNote(Product $product, bool $trackStock, int $quantity): array
    {
        if (! $trackStock) {
            return ['level' => 'in', 'count' => null];
        }
        if ($product->has_variants) {
            return ['level' => $quantity > 0 ? 'in' : 'out', 'count' => null];
        }

        return match (true) {
            $quantity <= 0 => ['level' => 'out', 'count' => 0],
            $quantity <= LowStock::threshold() => ['level' => 'low', 'count' => $quantity],
            default => ['level' => 'in', 'count' => $quantity],
        };
    }

    /** The signed-in shopper's name and email, to fill the back in stock form. */
    private static function customer(): array
    {
        $customer = auth('customer')->user();

        return [
            'name' => trim((string) ($customer?->first_name ?? '') . ' ' . (string) ($customer?->last_name ?? '')),
            'email' => (string) ($customer?->email ?? ''),
        ];
    }

    /** The short description cut at a word near the length the block asks for. */
    public static function cut(string $text, int $length): string
    {
        return Str::limit($text, max(40, $length), '...');
    }
}
