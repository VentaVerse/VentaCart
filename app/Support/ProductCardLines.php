<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

/**
 * Short lines extensions add to product cards, such as "Sold by Demo Shop",
 * drawn by a theme in one fixed place on every card: under the product name,
 * before the price.
 *
 * A callable receives every product of a list at once (a category page, a
 * product rail, the wishlist), so it can read what it needs in one query,
 * and answers fragments keyed by product id. A fragment is an Htmlable (a
 * rendered view, an HtmlString), printed as given, or a plain string, which
 * is text and is escaped. Markup comes only from an Htmlable, so a name a
 * seller typed that ends up in a string can never run as script on the
 * storefront. A callable that throws is reported and left out; the page
 * still draws.
 */
final class ProductCardLines
{
    /** A line's name, printed as its data-ext attribute. */
    private const NAME = '/^[a-z][a-z0-9-]{0,39}$/';

    /** @var array<string, callable> by name, in the order added */
    private static array $lines = [];

    /**
     * From an extension's service provider.
     *
     * @param  string  $name  the extension's name, or another short name of its own (lower case letters, digits and hyphens)
     * @param  callable(Collection<int, Product>): array<int, Htmlable|string>  $fn
     */
    public static function add(string $name, callable $fn): void
    {
        if (preg_match(self::NAME, $name) !== 1) {
            Log::warning('A product card line was refused: its name must be lower case letters, digits and hyphens.', ['name' => $name]);

            return;
        }
        if (isset(self::$lines[$name])) {
            Log::warning('A product card line was refused: the name is taken.', ['name' => $name]);

            return;
        }

        self::$lines[$name] = $fn;
    }

    public static function has(): bool
    {
        return self::$lines !== [];
    }

    public static function forget(): void
    {
        self::$lines = [];
    }

    /**
     * The lines of each card of a list, by product id: every extension's
     * fragment wrapped in its own element, in the order the lines were
     * added. Each callable runs once for the whole list; only products in
     * the list are kept, and a product with no fragment is left out.
     *
     * @param  iterable<Product>  $products  the list's products (a collection, an array or a paginator)
     * @return array<int, HtmlString>
     */
    public static function for(iterable $products): array
    {
        if (! self::has()) {
            return [];
        }

        $products = $products instanceof Paginator ? collect($products->items()) : collect($products);
        $products = $products->filter(fn ($product) => $product instanceof Product)->values();
        if ($products->isEmpty()) {
            return [];
        }
        $ids = array_flip($products->map(fn (Product $product) => (int) $product->getKey())->all());

        $out = [];
        foreach (self::$lines as $name => $fn) {
            try {
                $answer = $fn($products);
            } catch (\Throwable $e) {
                report($e);
                continue;
            }
            if (! is_array($answer)) {
                continue;
            }
            foreach ($answer as $id => $fragment) {
                $html = self::html($fragment);
                if ($html !== '' && is_int($id) && isset($ids[$id])) {
                    $out[$id] = ($out[$id] ?? '') . '<div class="card__ext-line" data-ext="' . e($name) . '">' . $html . '</div>';
                }
            }
        }

        return array_map(fn (string $html) => new HtmlString($html), $out);
    }

    private static function html(mixed $fragment): string
    {
        return match (true) {
            $fragment instanceof Htmlable => trim((string) $fragment->toHtml()),
            is_string($fragment) => e(trim($fragment)),
            default => '',
        };
    }
}
