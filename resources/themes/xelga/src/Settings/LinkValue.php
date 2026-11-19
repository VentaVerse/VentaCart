<?php

namespace Themes\Xelga\Settings;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Support\ProductListing;
use App\Support\SafeInput;
use Illuminate\Support\Facades\Route;

/**
 * A structured link: where a button or menu photo goes, stored as what it
 * points at rather than as a typed address, so a renamed category or
 * product still leads to the right page.
 *
 *   {"type": "category", "id": 12}
 *   {"type": "url", "href": "https://example.com/size-guide"}
 *   {"type": "home"}     also search, cart, account, wishlist, blog, brands, contact
 *   {"type": "these-products"}
 *
 * "These products" is the listing page of the products a section shows:
 * a product section's "Products to show" (newest, best sellers, a
 * category and so on) decides where it goes, so the link always agrees
 * with the section. Only a url setting that opts in with
 * "follows_products": true takes it, and the section resolves it with
 * forProducts(), since the link alone does not know the section.
 */
final class LinkValue
{
    /** Link types that need nothing but their name. */
    public const PLAIN = ['home', 'search', 'cart', 'account', 'wishlist', 'blog', 'brands', 'contact'];

    /** The link to the listing of the products a section shows. */
    public const THESE_PRODUCTS = 'these-products';

    /** Link types that point at one core record by id. */
    public const RECORD = ['category', 'product', 'brand', 'page'];

    /** @var array<string, ?string> resolved URLs for this request */
    private static array $memo = [];

    /**
     * The clean value, or null when nothing usable was given. A plain
     * string that is a safe link is kept as a web address. "These
     * products" is kept only where the setting follows a section's
     * products ($followsProducts).
     *
     * @return array{type: string, id?: int, href?: string}|null
     */
    public static function clean(mixed $value, bool $followsProducts = false): ?array
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        if (is_string($value)) {
            $href = SafeInput::link($value);

            return $href === '' ? null : ['type' => 'url', 'href' => $href];
        }

        if (! is_array($value) || ! is_string($value['type'] ?? null)) {
            return null;
        }

        $type = $value['type'];

        if (in_array($type, self::PLAIN, true) || ($followsProducts && $type === self::THESE_PRODUCTS)) {
            return ['type' => $type];
        }

        if (in_array($type, self::RECORD, true)) {
            $id = $value['id'] ?? null;
            if ((is_int($id) || (is_string($id) && ctype_digit($id))) && (int) $id > 0 && (int) $id <= PHP_INT_MAX) {
                return ['type' => $type, 'id' => (int) $id];
            }

            return null;
        }

        if ($type === 'url') {
            $href = SafeInput::link($value['href'] ?? '');

            return $href === '' ? null : ['type' => 'url', 'href' => $href];
        }

        return null;
    }

    /**
     * The address the link leads to for the shopper looking at the page,
     * or null when its target is gone or hidden from them; the link is
     * then not drawn.
     */
    public static function resolve(mixed $value): ?string
    {
        $link = self::clean($value);
        if ($link === null) {
            return null;
        }

        $key = json_encode($link);
        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }

        return self::$memo[$key] = self::lookup($link);
    }

    /**
     * resolve() for a link beside a list of products: "These products"
     * becomes the listing page of what the section shows ($settings holds
     * its source, category and brand); any other link resolves as usual.
     */
    public static function forProducts(mixed $value, array $settings): ?string
    {
        if (self::clean($value, true) !== ['type' => self::THESE_PRODUCTS]) {
            return self::resolve($value);
        }

        $source = (string) ($settings['source'] ?? '');
        $id = fn (string $key): ?int => is_numeric($settings[$key] ?? null) && (int) $settings[$key] > 0 ? (int) $settings[$key] : null;
        $search = fn (array $query) => Route::has('search.results') ? route('search.results', $query, false) : null;

        return match ($source) {
            'newest' => $search(['sort' => self::NEWEST_SORT]),
            'best-sellers' => $search(['sort' => 'best_sellers']),
            'most-viewed' => $search(['sort' => 'most_viewed']),
            'on-sale' => $search([ProductListing::ON_SALE => 1]),
            'category' => ($c = $id('category')) ? self::resolve(['type' => 'category', 'id' => $c]) : null,
            'brand' => ($b = $id('brand')) ? self::resolve(['type' => 'brand', 'id' => $b]) : null,
            // Products picked by hand have no page that lists just them.
            default => null,
        };
    }

    /**
     * The listing sort that matches the section's "Newest": core's product
     * feed lists newest by date available, which is the listings' date_added.
     */
    private const NEWEST_SORT = 'date_added';

    public static function flush(): void
    {
        self::$memo = [];
    }

    private static function lookup(array $link): ?string
    {
        $route = fn (string $name, array $params = []) => Route::has($name) ? route($name, $params, false) : null;

        return match ($link['type']) {
            'url' => $link['href'],
            'home' => $route('home') ?? '/',
            'search' => $route('search.results'),
            'cart' => $route('cart.index'),
            'account' => $route('account.dashboard'),
            'wishlist' => $route('wishlist.index'),
            'blog' => $route('blog.index'),
            'brands' => $route('brand.index'),
            'contact' => $route('contact'),
            // Through the models, so the visibility rules apply: a product
            // hidden from this shopper's group simply has no link.
            'category' => ($c = Category::query()->where('is_active', true)->find($link['id'])) ? $route('category.show', ['category' => $c->slug]) : null,
            'product' => ($p = Product::query()->where('is_active', true)->find($link['id'])) ? $route('product.show', ['product' => $p->slug]) : null,
            'brand' => ($b = Brand::query()->where('is_active', true)->find($link['id'])) ? $route('brand.show', ['brand' => $b->slug]) : null,
            'page' => ($g = Page::query()->active()->find($link['id'])) ? $route('page.show', ['slug' => $g->slug]) : null,
            default => null,
        };
    }
}
