<?php

namespace App\Storefront\Widgets;

use App\Models\Brand;
use App\Models\Product;
use App\Support\Widgets;
use Illuminate\Support\Facades\DB;

/**
 * The widgets Venta itself ships.
 *
 * These eight are the section types the home page already had. Lifting them
 * out of HomeController's switch and home.blade.php's if/elseif chain is what
 * lets them appear anywhere, and it is also the first real test of the
 * registry: if the contract cannot carry the things we already built, it
 * certainly cannot carry a third party's.
 */
class CoreWidgets
{
    public static function register(): void
    {
        Widgets::register('catalog.products', [
            'title' => 'Product grid',
            'module' => 'catalog',
            'view' => 'widgets.catalog.products',
            'schema' => [
                'filter' => ['type' => 'select', 'label' => 'Show',
                    'options' => ['new' => 'Newest', 'featured' => 'Featured', 'specials' => 'On sale', 'best_sellers' => 'Best sellers']],
                'limit' => ['type' => 'number', 'label' => 'How many', 'default' => 12],
            ],
            'resolve' => fn (array $config) => ['products' => self::products($config)],
        ]);

        Widgets::register('catalog.brands', [
            'title' => 'Brand strip',
            'module' => 'catalog',
            'view' => 'widgets.catalog.brands',
            // Full width only. A carousel with side arrows in a 1/8 rail is
            // not a smaller carousel, it is a broken one.
            'regions' => ['header_top', 'content_top', 'content_bottom', 'footer_top'],
            'resolve' => fn () => ['brands' => Brand::where('is_active', true)
                ->whereNotNull('image')->where('image', '!=', '')
                ->orderBy('sort_order')->orderBy('name')->get()],
        ]);

        Widgets::register('catalog.category_tabs', [
            'title' => 'Category tabs',
            'module' => 'catalog',
            'view' => 'widgets.catalog.category_tabs',
            // Full width only. A carousel with side arrows in a 1/8 rail is
            // not a smaller carousel, it is a broken one.
            'regions' => ['header_top', 'content_top', 'content_bottom', 'footer_top'],
        ]);

        Widgets::register('catalog.recently_viewed', [
            'title' => 'Recently viewed',
            'module' => 'catalog',
            'view' => 'widgets.catalog.recently_viewed',
        ]);

        Widgets::register('content.text', [
            'title' => 'Text block',
            'module' => 'content',
            'view' => 'widgets.content.text',
            'schema' => [
                'alignment' => ['type' => 'select', 'label' => 'Alignment',
                    'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right']],
            ],
        ]);

        Widgets::register('blog.posts', [
            'title' => 'Blog posts',
            'module' => 'blog',
            'view' => 'widgets.blog.posts',
        ]);
    }

    /** Lifted verbatim from HomeController::getProductsForSection(). */
    private static function products(array $config)
    {
        $limit = $config['limit'] ?? 12;
        $filter = $config['filter'] ?? 'new';

        $query = Product::where('is_active', true)->with(['images', 'specials'])
            ->withAvg(['reviews as reviews_avg_rating' => fn ($q) => $q->where('status', 'approved')], 'rating')
            ->withCount(['reviews as reviews_count' => fn ($q) => $q->where('status', 'approved')]);

        switch ($filter) {
            case 'new':
                $query->whereNotNull('date_available')->latest('date_available');
                break;
            case 'specials':
                $query->whereHas('specials', function ($q) {
                    $q->where('display_mode', 'sale')
                        ->where(fn ($q2) => $q2->whereNull('date_start')->orWhere('date_start', '<=', now()))
                        ->where(fn ($q2) => $q2->whereNull('date_end')->orWhere('date_end', '>=', now()));
                });
                break;
            case 'best_sellers':
                $prefix = DB::getTablePrefix();
                $query->withCount(['orderProducts as total_revenue' => function ($q) use ($prefix) {
                    $q->select(DB::raw("COALESCE(SUM({$prefix}order_products.price * {$prefix}order_products.quantity), 0)"));
                }])->orderByDesc('total_revenue');
                break;
            default:
                $query->latest();
        }

        return $query->limit($limit)->get();
    }
}
