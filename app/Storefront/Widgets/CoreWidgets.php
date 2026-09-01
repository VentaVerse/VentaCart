<?php

namespace App\Storefront\Widgets;

use App\Models\Brand;
use App\Models\Category;
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
            'shape' => 'grid',
            'module' => 'catalog',
            'view' => 'widgets.catalog.products',
            'schema' => [
                'filter' => ['type' => 'select', 'label' => 'Show',
                    'options' => ['new' => 'Newest', 'featured' => 'Featured', 'specials' => 'On sale', 'best_sellers' => 'Best sellers',
                        'most_viewed' => 'Most viewed', 'random' => 'Random']],
                'limit' => ['type' => 'number', 'label' => 'How many', 'default' => 12],
                'layout' => ['type' => 'select', 'label' => 'Layout', 'default' => 'grid',
                    'options' => ['grid' => 'Grid', 'slider' => 'Slider']],
                'columns' => ['type' => 'select', 'label' => 'Per row', 'default' => '4',
                    'options' => ['2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6']],
                'card' => ['type' => 'select', 'label' => 'Card style', 'default' => 'default',
                    'options' => ['default' => 'Default', 'compact' => 'Compact', 'overlay' => 'Image with overlay']],
            ],
            'resolve' => fn (array $config) => ['products' => self::products($config)],
        ]);

        // Tabs of product listings: Latest | Featured | Best sellers, the
        // arrangement every marketplace homepage carries.
        Widgets::register('catalog.product_tabs', [
            'title' => 'Product tabs',
            'shape' => 'tabs',
            'module' => 'catalog',
            'view' => 'widgets.catalog.product_tabs',
            'schema' => [
                'tabs' => ['type' => 'rows', 'label' => 'Tabs', 'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Tab'],
                    'filter' => ['type' => 'select', 'label' => 'Show', 'options' => ['new' => 'Newest', 'featured' => 'Featured', 'specials' => 'On sale', 'best_sellers' => 'Best sellers', 'most_viewed' => 'Most viewed', 'random' => 'Random']],
                    'limit' => ['type' => 'number', 'label' => 'How many'],
                ]],
                'columns' => ['type' => 'select', 'label' => 'Per row', 'default' => '4',
                    'options' => ['2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6']],
            ],
            'resolve' => function (array $config) {
                $tabs = [];
                foreach (is_array($config['tabs'] ?? null) ? $config['tabs'] : [] as $tab) {
                    if (! is_array($tab)) {
                        continue;
                    }
                    $tabs[] = [
                        'title' => (string) ($tab['title'] ?? ''),
                        'products' => self::products(['filter' => $tab['filter'] ?? 'new', 'limit' => $tab['limit'] ?? 8]),
                    ];
                }

                return ['tabs' => $tabs];
            },
        ]);

        // Products that shared an order with the one being viewed. Product
        // pages only: anywhere else there is no product to relate to.
        Widgets::register('catalog.also_bought', [
            'title' => 'Also bought',
            'shape' => 'grid',
            'module' => 'catalog',
            'view' => 'widgets.catalog.also_bought',
            'pages' => ['product'],
            'schema' => [
                'limit' => ['type' => 'number', 'label' => 'How many', 'default' => 4],
                'columns' => ['type' => 'select', 'label' => 'Per row', 'default' => '4',
                    'options' => ['2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6']],
            ],
            'resolve' => fn (array $config) => ['products' => self::alsoBought($config)],
        ]);

        Widgets::register('catalog.brands', [
            'title' => 'Brand strip',
            'shape' => 'strip',
            'module' => 'catalog',
            'view' => 'widgets.catalog.brands',
            // Full width only. A carousel with side arrows in a 1/8 rail is
            // not a smaller carousel, it is a broken one.
            'regions' => ['header_top', 'content_top', 'content_bottom', 'footer_top'],
            'resolve' => fn () => ['brands' => Brand::where('is_active', true)
                ->whereNotNull('image')->where('image', '!=', '')
                ->orderBy('sort_order')->orderBy('name')->get()],
        ]);

        // Departments as pictures you can click, which every shop front has
        // and this one had no way to draw: category_tabs below renders a
        // placeholder, and a gallery is images without links.
        Widgets::register('catalog.categories', [
            'title' => 'Category grid',
            'shape' => 'grid',
            'module' => 'catalog',
            'view' => 'widgets.catalog.categories',
            'schema' => [
                'show' => ['type' => 'select', 'label' => 'Which', 'default' => 'top',
                    'options' => ['top' => 'Top level only', 'all' => 'All categories']],
                'limit' => ['type' => 'number', 'label' => 'How many', 'default' => 8],
                'columns' => ['type' => 'select', 'label' => 'Per row', 'default' => '4',
                    'options' => ['2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6']],
            ],
            'resolve' => function (array $config) {
                $query = Category::query()
                    ->where('is_active', true)
                    // A department with no picture is a grey box in a grid of
                    // photographs, so it waits until it has one.
                    ->whereNotNull('image')->where('image', '!=', '');

                if (($config['show'] ?? 'top') !== 'all') {
                    $query->whereNull('parent_id');
                }

                return ['categories' => $query
                    ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
                    ->orderBy('sort_order')->orderBy('name')
                    ->limit(max(1, min(24, (int) ($config['limit'] ?? 8))))
                    ->get()];
            },
        ]);

        Widgets::register('catalog.category_tabs', [
            'title' => 'Category tabs',
            'shape' => 'tabs',
            'module' => 'catalog',
            'view' => 'widgets.catalog.category_tabs',
            // Full width only. A carousel with side arrows in a 1/8 rail is
            // not a smaller carousel, it is a broken one.
            'regions' => ['header_top', 'content_top', 'content_bottom', 'footer_top'],
        ]);

        Widgets::register('catalog.recently_viewed', [
            'title' => 'Recently viewed',
            'shape' => 'grid',
            'module' => 'catalog',
            'view' => 'widgets.catalog.recently_viewed',
        ]);

        Widgets::register('content.text', [
            'title' => 'Text block',
            'shape' => 'text',
            'module' => 'content',
            'view' => 'widgets.content.text',
            'schema' => [
                'alignment' => ['type' => 'select', 'label' => 'Alignment',
                    'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right']],
            ],
        ]);

        // The theme's own header pieces, placeable where a theme declares a
        // header region. Views come from the theme; core only names them.
        foreach ([
            'system.header' => ['title' => 'Header (as arranged)', 'shape' => 'block'],
            'system.topbar' => ['title' => 'Top bar', 'shape' => 'strip'],
            'system.logo' => ['title' => 'Logo', 'shape' => 'block'],
            'system.search' => ['title' => 'Search', 'shape' => 'block'],
            'system.cart' => ['title' => 'Cart', 'shape' => 'block'],
            'system.nav' => ['title' => 'Main menu', 'shape' => 'strip'],
        ] as $key => $spec) {
            Widgets::register($key, $spec + [
                'module' => 'theme',
                'system' => true,
                'view' => 'widgets.' . str_replace('.', '.', $key),
                'regions' => ['header'],
            ]);
        }
        // The intro area: the page's trail and its title, drawn by the
        // theme from what the page said about itself (App\Support\PageIntro).
        foreach ([
            'system.breadcrumbs' => ['title' => 'Breadcrumbs', 'shape' => 'strip'],
            'system.page_title' => ['title' => 'Page title', 'shape' => 'block'],
        ] as $key => $spec) {
            Widgets::register($key, $spec + [
                'module' => 'theme',
                'system' => true,
                'view' => 'widgets.' . $key,
                'regions' => ['intro'],
            ]);
        }
        Widgets::register('system.quick_view', [
            'title' => 'Quick view', 'shape' => 'block', 'module' => 'theme', 'system' => true,
            'view' => 'widgets.system.quick_view', 'regions' => ['quickview'], 'pages' => [\App\Support\LayoutPage::QUICKVIEW],
        ]);
        Widgets::register('system.footer', [
            'title' => 'Footer (as arranged)', 'shape' => 'block', 'module' => 'theme', 'system' => true,
            'view' => 'widgets.system.footer', 'regions' => ['footer'],
        ]);

        // The product page's own pieces, for a theme that declares a
        // product_main region. The gallery and buy box stay one piece: they
        // share the scope that moves the image when a variant is chosen.
        foreach ([
            'system.product_buy' => ['title' => 'Gallery and buy box', 'shape' => 'block'],
            'system.product_tabs' => ['title' => 'Description and reviews', 'shape' => 'block'],
            'system.product_related' => ['title' => 'Related products', 'shape' => 'strip'],
            'system.product_brand' => ['title' => 'More from the brand', 'shape' => 'strip'],
        ] as $key => $spec) {
            Widgets::register($key, $spec + [
                'module' => 'theme',
                'system' => true,
                'view' => 'widgets.' . $key,
                'regions' => ['product_main'],
                'pages' => ['product'],
            ]);
        }

        Widgets::register('content.cta', [
            'title' => 'Call to action',
            'shape' => 'banner',
            'module' => 'content',
            'view' => 'widgets.content.cta',
            'schema' => [
                'text' => ['type' => 'textarea', 'label' => 'Text'],
                'button' => ['type' => 'text', 'label' => 'Button'],
                'url' => ['type' => 'text', 'label' => 'Link'],
                'alignment' => ['type' => 'select', 'label' => 'Alignment', 'default' => 'center',
                    'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right']],
            ],
        ]);

        Widgets::register('content.icon_list', [
            'title' => 'Icon list',
            'shape' => 'strip',
            'module' => 'content',
            'view' => 'widgets.content.icon_list',
            'schema' => [
                'items' => ['type' => 'rows', 'label' => 'Items', 'fields' => [
                    'icon' => ['type' => 'select', 'label' => 'Icon', 'options' => self::ICONS],
                    'title' => ['type' => 'text', 'label' => 'Title'],
                    'text' => ['type' => 'text', 'label' => 'Text'],
                ]],
                'columns' => ['type' => 'select', 'label' => 'Per row', 'default' => '4',
                    'options' => ['2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6']],
            ],
        ]);

        Widgets::register('content.separator', [
            'title' => 'Separator',
            'shape' => 'block',
            'module' => 'content',
            'view' => 'widgets.content.separator',
            'schema' => [
                'style' => ['type' => 'select', 'label' => 'Style', 'default' => 'space',
                    'options' => ['space' => 'Space', 'line' => 'Line']],
                'size' => ['type' => 'select', 'label' => 'Size', 'default' => 'md',
                    'options' => ['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large']],
            ],
        ]);

        Widgets::register('content.gallery', [
            'title' => 'Gallery',
            'shape' => 'grid',
            'module' => 'content',
            'view' => 'widgets.content.gallery',
            'schema' => [
                'images' => ['type' => 'images', 'label' => 'Images', 'root' => 'banners'],
                'columns' => ['type' => 'select', 'label' => 'Per row', 'default' => '4',
                    'options' => ['1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6']],
                'lightbox' => ['type' => 'select', 'label' => 'On click', 'default' => 'lightbox',
                    'options' => ['lightbox' => 'Open larger', 'none' => 'Nothing']],
            ],
        ]);

        Widgets::register('content.accordion', [
            'title' => 'Accordion',
            'shape' => 'tabs',
            'module' => 'content',
            'view' => 'widgets.content.accordion',
            'schema' => [
                'items' => ['type' => 'rows', 'label' => 'Sections', 'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Title'],
                    'text' => ['type' => 'textarea', 'label' => 'Text'],
                ]],
                'open' => ['type' => 'select', 'label' => 'At first', 'default' => 'first',
                    'options' => ['first' => 'First section open', 'none' => 'All closed']],
            ],
        ]);

        // Any composed menu, anywhere: a footer menu under the content, a
        // second bar above the header.
        Widgets::register('nav.menu', [
            'title' => 'Menu',
            'shape' => 'strip',
            'module' => 'navigation',
            'view' => 'widgets.nav.menu',
            'schema' => [
                'menu' => ['type' => 'select', 'label' => 'Which menu',
                    'options' => fn () => \App\Models\NavMenu::orderBy('name')->pluck('name', 'key')->all()],
                'direction' => ['type' => 'select', 'label' => 'Direction', 'default' => 'horizontal',
                    'options' => ['horizontal' => 'Across', 'vertical' => 'Down']],
            ],
            'resolve' => fn (array $config) => ['tree' => \App\Support\NavTree::for((string) ($config['menu'] ?? \App\Models\NavMenu::MAIN))],
        ]);

        Widgets::register('social.facebook', [
            'title' => 'Facebook page',
            'shape' => 'block',
            'module' => 'social',
            'view' => 'widgets.social.facebook',
            'schema' => [
                'url' => ['type' => 'text', 'label' => 'Page URL'],
                'height' => ['type' => 'number', 'label' => 'Height', 'default' => 400],
            ],
        ]);

        Widgets::register('social.twitter', [
            'title' => 'X timeline',
            'shape' => 'block',
            'module' => 'social',
            'view' => 'widgets.social.twitter',
            'schema' => [
                'username' => ['type' => 'text', 'label' => 'Username'],
                'height' => ['type' => 'number', 'label' => 'Height', 'default' => 400],
            ],
        ]);

        // Blog posts read the blog extension's table when it is present; the
        // widget stays registered so a placement survives the extension being
        // switched off, rendering nothing until it is back.
        Widgets::register('blog.posts', [
            'title' => 'Blog posts',
            'shape' => 'cards',
            'module' => 'blog',
            'view' => 'widgets.blog.posts',
            'schema' => [
                'limit' => ['type' => 'number', 'label' => 'How many', 'default' => 3],
                'columns' => ['type' => 'select', 'label' => 'Per row', 'default' => '3',
                    'options' => ['2' => '2', '3' => '3', '4' => '4']],
            ],
            'resolve' => function (array $config) {
                if (! class_exists(\Extensions\Blog\Models\BlogPost::class)) {
                    return ['posts' => collect()];
                }

                return ['posts' => \Extensions\Blog\Models\BlogPost::query()
                    ->where('is_published', true)
                    ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
                    ->orderByDesc('published_at')->orderByDesc('id')
                    ->limit(max(1, min(24, (int) ($config['limit'] ?? 3))))
                    ->get()];
            },
        ]);
    }

    /** Lifted verbatim from HomeController::getProductsForSection(). */
    /** The icons an icon list may pick from; drawn by the theme. */
    public const ICONS = [
        'truck' => 'Delivery', 'shield' => 'Guarantee', 'refresh' => 'Returns', 'chat' => 'Support',
        'star' => 'Star', 'box' => 'Parcel', 'card' => 'Payment', 'clock' => 'Time', 'pin' => 'Location', 'gift' => 'Gift',
    ];

    /**
     * Products that shared an order with the product being viewed, most
     * often first. Nothing off a product page: there is nothing to relate to.
     */
    private static function alsoBought(array $config)
    {
        // The product the page is about, from the route's own parameter.
        $current = request()->route()?->parameter('product');
        $productId = $current instanceof Product ? $current->id
            : (is_scalar($current) ? Product::where('slug', (string) $current)->value('id') : null);

        if (! $productId) {
            return collect();
        }

        $orderIds = \App\Models\OrderProduct::where('product_id', $productId)->pluck('order_id');
        if ($orderIds->isEmpty()) {
            return collect();
        }

        $ids = \App\Models\OrderProduct::whereIn('order_id', $orderIds)
            ->where('product_id', '!=', $productId)
            ->whereNotNull('product_id')
            ->select('product_id', DB::raw('COUNT(*) as n'))
            ->groupBy('product_id')
            ->orderByDesc('n')
            ->limit(max(1, min(24, (int) ($config['limit'] ?? 4))))
            ->pluck('product_id');

        if ($ids->isEmpty()) {
            return collect();
        }

        return Product::where('is_active', true)->whereIn('id', $ids)->with(['images', 'specials'])->get()
            ->sortBy(fn ($p) => $ids->search($p->id))->values();
    }

    private static function products(array $config)
    {
        $limit = max(1, min(48, (int) ($config['limit'] ?? 12)));
        $filter = $config['filter'] ?? 'new';

        $query = Product::where('is_active', true)->with(['images', 'specials']);
        // Ratings ride along only while the reviews extension is present: the
        // relation names its model, and a store that switched reviews off
        // must still list products.
        if (class_exists(\Extensions\Reviews\Models\Review::class)) {
            $query->withAvg(['reviews as reviews_avg_rating' => fn ($q) => $q->where('status', 'approved')], 'rating')
                ->withCount(['reviews as reviews_count' => fn ($q) => $q->where('status', 'approved')]);
        }

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
            case 'most_viewed':
                $query->orderByDesc('views')->orderByDesc('id');
                break;
            case 'random':
                $query->inRandomOrder();
                break;
            case 'best_sellers':
                $prefix = DB::getTablePrefix();
                $query->withCount(['orderProducts as total_revenue' => function ($q) use ($prefix) {
                    $q->select(DB::raw("COALESCE(SUM({$prefix}order_products.price * {$prefix}order_products.quantity), 0)"));
                }])
                    // Everything that has sold nothing has sold the same
                    // amount, so a store with no orders yet would return
                    // whatever the database felt like. Views break the tie,
                    // which is the closest thing to a seller a new shop has.
                    ->orderByDesc('total_revenue')->orderByDesc('views')->orderByDesc('id');
                break;
            default:
                $query->latest();
        }

        return $query->limit($limit)->get();
    }
}
