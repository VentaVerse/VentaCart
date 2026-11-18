<?php

namespace Themes\Xelga\Sections;

use App\Models\Product;
use App\Support\ProductFeed;
use Illuminate\Support\Facades\Cookie;
use Themes\Xelga\Preview\PreviewMode;
use Themes\Xelga\Rendering\RenderContext;
use Themes\Xelga\Rendering\SectionData;

/**
 * Recently viewed: the products this browser opened last, newest first.
 * The list is a cookie of product ids written by the product page; the
 * products themselves come from core's product feed, so a product the
 * shopper may no longer see (hidden for their group, switched off) simply
 * drops out. The product being viewed is left out of its own page.
 *
 * In the editor's preview an empty list would hide the section, so the
 * merchant could not see or style it: there the store's latest products
 * stand in, through the same feed. Shoppers never get stand-ins.
 */
final class RecentlyViewed implements SectionData
{
    public const COOKIE = 'xelga_recently_viewed';

    /** Most ids the cookie keeps. */
    public const KEEP = 12;

    /** How long the list is kept, in minutes (thirty days). */
    public const MINUTES = 43200;

    public function resolve(array $settings, array $blocks, RenderContext $context): array
    {
        $count = max(2, min(12, (int) ($settings['count'] ?? 4)));
        $current = $context->get('product', $context->subject);
        $ids = array_values(array_filter(self::ids(), fn ($id) => ! ($current instanceof Product && $id === (int) $current->id)));

        $products = $ids === [] ? collect() : ProductFeed::get(['ids' => array_slice($ids, 0, self::KEEP), 'limit' => $count]);
        if ($products->isEmpty() && PreviewMode::active()) {
            $products = self::standIns($count, $current instanceof Product ? (int) $current->id : null);
        }

        return [
            'cols' => $context->fitColumns($settings['cols'] ?? 4),
            'cards' => ProductCards::for($products, Swatches::wanted($context), CardLines::wanted($context)),
            'empty' => 'Shoppers see the products they opened last here. It stays hidden until they have opened one.',
        ];
    }

    /** The store's latest products, for the editor's preview only; never the one being viewed. */
    private static function standIns(int $count, ?int $exclude)
    {
        return ProductFeed::get(['filter' => 'new', 'limit' => $count + 1])
            ->reject(fn (Product $p) => (int) $p->id === $exclude)
            ->take($count)
            ->values();
    }

    /** The ids in the cookie, newest first. @return list<int> */
    public static function ids(): array
    {
        $raw = request()->cookie(self::COOKIE);
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        return array_slice(ProductFeed::parseIds($raw), 0, self::KEEP);
    }

    /** Put a product at the front of the list, for the next page this shopper opens. */
    public static function remember(Product $product): void
    {
        $ids = [(int) $product->id, ...array_filter(self::ids(), fn ($id) => $id !== (int) $product->id)];
        Cookie::queue(self::COOKIE, implode(',', array_slice($ids, 0, self::KEEP)), self::MINUTES, null, null, null, true, false, 'lax');
    }
}
