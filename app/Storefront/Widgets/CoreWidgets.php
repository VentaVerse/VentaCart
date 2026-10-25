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
            'system.logo' => ['title' => 'Logo', 'shape' => 'block', 'schema' => [
                'align' => ['type' => 'select', 'label' => 'Alignment', 'default' => 'left',
                    'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right']],
            ]],
            // Empty choices follow the theme's own header settings, so a
            // placed piece matches the rest of the header until it is told
            // otherwise.
            'system.search' => ['title' => 'Search', 'shape' => 'block', 'schema' => [
                'placeholder' => ['type' => 'text', 'label' => 'Hint inside the field'],
                'style' => ['type' => 'select', 'label' => 'Style', 'default' => '',
                    'options' => ['' => 'As the header settings', 'bar' => 'Field with a button', 'pill' => 'Rounded field, icon inside', 'underline' => 'Underlined, no box']],
            ]],
            'system.cart' => ['title' => 'Cart', 'shape' => 'block', 'schema' => [
                'style' => ['type' => 'select', 'label' => 'Style', 'default' => '',
                    'options' => ['' => 'As the header settings', 'text' => 'Item count and total', 'icon' => 'Icon with a count', 'total' => 'Icon and total', 'button' => 'Filled button']],
                'label' => ['type' => 'text', 'label' => 'Button label'],
                'account' => ['type' => 'select', 'label' => 'Account link beside it', 'default' => '',
                    'options' => ['' => 'As the header settings', 'none' => 'None', 'icon' => 'Icon', 'label' => 'Icon and label']],
                'align' => ['type' => 'select', 'label' => 'Alignment', 'default' => 'right',
                    'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right']],
            ]],
            'system.nav' => ['title' => 'Main menu', 'shape' => 'strip', 'schema' => [
                'align' => ['type' => 'select', 'label' => 'Alignment', 'default' => '',
                    'options' => ['' => 'As the header settings', 'left' => 'Left', 'center' => 'Center', 'right' => 'Right']],
            ]],
            // The shopper's account link: Sign in for visitors, their first
            // name once signed in.
            'system.account' => ['title' => 'Account link', 'shape' => 'block', 'schema' => [
                'display' => ['type' => 'select', 'label' => 'Show as', 'default' => 'icon',
                    'options' => ['icon' => 'An icon', 'label' => 'An icon and a label']],
                'align' => ['type' => 'select', 'label' => 'Alignment', 'default' => 'right',
                    'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right']],
            ]],
            // The store's currency selector, as a piece of its own. It lists
            // the enabled currencies and draws nothing while there is one.
            'system.currency_menu' => ['title' => 'Currency menu', 'shape' => 'block', 'schema' => [
                'label' => ['type' => 'text', 'label' => 'Label before it'],
                'display' => ['type' => 'select', 'label' => 'Show as', 'default' => 'dropdown',
                    'options' => ['dropdown' => 'A dropdown', 'list' => 'A row of links']],
                'show' => ['type' => 'select', 'label' => 'Each currency as', 'default' => 'code',
                    'options' => ['code' => 'Code (USD)', 'symbol' => 'Symbol and code ($ USD)', 'name' => 'Name (US Dollar)']],
            ]],
            // A greeting, with the shopper's first name once signed in.
            'system.welcome_text' => ['title' => 'Welcome text', 'shape' => 'block', 'schema' => [
                'guest' => ['type' => 'text', 'label' => 'For visitors', 'default' => 'Welcome to our store'],
                'customer' => ['type' => 'text', 'label' => 'For signed-in customers ({name} is their first name)', 'default' => 'Welcome back, {name}'],
                'links' => ['type' => 'select', 'label' => 'Sign in and register links', 'default' => 'show',
                    'options' => ['show' => 'Show to visitors', 'hide' => 'Hide']],
            ]],
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
        // Either may also sit inside a page's own region, beside the pieces
        // it names. A product page keeps its name as a piece of its own, so
        // the title is not offered there.
        foreach ([
            'system.breadcrumbs' => ['title' => 'Breadcrumbs', 'shape' => 'strip', 'regions' => ['intro', 'product_main', 'category_main']],
            'system.page_title' => ['title' => 'Page title', 'shape' => 'block', 'regions' => ['intro', 'category_main']],
        ] as $key => $spec) {
            Widgets::register($key, $spec + [
                'module' => 'theme',
                'system' => true,
                'view' => 'widgets.' . $key,
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
        // product_main region. Each reads the page's data from PageContext.
        //
        // The price, variants, volume pricing and add to cart pieces work
        // together: choosing a variant or a quantity changes the price. A
        // theme gives them one shared script scope by wrapping the whole
        // region, which is why they are offered in product_main only.
        //
        // The gallery and buy box, and the description and reviews, are also
        // offered whole, for layouts arranged before the page came apart.
        $showHide = ['show' => 'Show', 'hide' => 'Hide'];
        foreach ([
            'system.product_images' => ['title' => 'Images', 'shape' => 'block'],
            'system.product_name' => ['title' => 'Product name', 'shape' => 'block'],
            'system.product_rating' => ['title' => 'Rating', 'shape' => 'block'],
            'system.product_info' => ['title' => 'Product info', 'shape' => 'block', 'schema' => [
                'brand' => ['type' => 'select', 'label' => 'Brand', 'default' => 'show', 'options' => $showHide],
                'shipping' => ['type' => 'select', 'label' => 'Free shipping note', 'default' => 'show', 'options' => $showHide],
                'stock' => ['type' => 'select', 'label' => 'Stock', 'default' => 'show', 'options' => $showHide],
            ]],
            'system.product_price' => ['title' => 'Price', 'shape' => 'block'],
            'system.product_countdown' => ['title' => 'Sale countdown', 'shape' => 'block'],
            'system.product_variants' => ['title' => 'Variants', 'shape' => 'block'],
            'system.product_volume_pricing' => ['title' => 'Volume pricing', 'shape' => 'block'],
            'system.product_add_to_cart' => ['title' => 'Add to cart', 'shape' => 'block'],
            'system.product_tags' => ['title' => 'Tags', 'shape' => 'strip'],
            'system.product_share' => ['title' => 'Share buttons', 'shape' => 'strip'],
            'system.product_description' => ['title' => 'Description', 'shape' => 'text'],
            'system.product_reviews' => ['title' => 'Reviews', 'shape' => 'text'],
            'system.product_buy' => ['title' => 'Gallery and buy box (all in one)', 'shape' => 'block'],
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

        // The category page's own pieces, for a theme that declares a
        // category_main region: the category's picture and text, its
        // children, and the paginated listing with its sort and view.
        foreach ([
            'system.category_image' => ['title' => 'Category image', 'shape' => 'banner'],
            'system.category_description' => ['title' => 'Category description', 'shape' => 'text'],
            'system.category_subcategories' => ['title' => 'Subcategories', 'shape' => 'strip'],
            'system.category_products' => ['title' => 'Product listing', 'shape' => 'grid'],
        ] as $key => $spec) {
            Widgets::register($key, $spec + [
                'module' => 'theme',
                'system' => true,
                'view' => 'widgets.' . $key,
                'regions' => ['category_main'],
                'pages' => ['category'],
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

        // One picture with words over it, linked: the single promotional tile
        // a banner group is too much machinery for.
        $align = ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'];
        Widgets::register('content.banner', [
            'title' => 'Banner',
            'shape' => 'banner',
            'module' => 'content',
            'view' => 'widgets.content.banner',
            'schema' => [
                'image' => ['type' => 'image', 'label' => 'Image', 'root' => 'banners'],
                'heading' => ['type' => 'text', 'label' => 'Heading'],
                'text' => ['type' => 'textarea', 'label' => 'Text'],
                'button' => ['type' => 'text', 'label' => 'Button'],
                'url' => ['type' => 'text', 'label' => 'Link'],
                'target' => ['type' => 'select', 'label' => 'Open the link', 'default' => 'same',
                    'options' => ['same' => 'In the same tab', 'new' => 'In a new tab']],
                'alignment' => ['type' => 'select', 'label' => 'Text alignment', 'default' => 'center', 'options' => $align],
                'position' => ['type' => 'select', 'label' => 'Text position', 'default' => 'middle',
                    'options' => ['top' => 'Top', 'middle' => 'Middle', 'bottom' => 'Bottom']],
                'overlay' => ['type' => 'select', 'label' => 'Darken the image', 'default' => '30',
                    'options' => ['0' => 'Not at all', '15' => 'A little', '30' => 'Some', '50' => 'Half', '70' => 'A lot']],
                'height' => ['type' => 'select', 'label' => 'Height', 'default' => 'image',
                    'options' => ['image' => 'As the image', 'sm' => 'Short', 'md' => 'Medium', 'lg' => 'Tall']],
                'hover' => ['type' => 'select', 'label' => 'On hover', 'default' => 'zoom',
                    'options' => ['zoom' => 'Zoom the image', 'none' => 'Nothing']],
            ],
        ]);

        // Other blocks shown as tabs or an accordion. The group holds the
        // blocks placed directly below it in the same column (see
        // App\Support\BlockGroup), so each one keeps its own settings form
        // and nothing about how a placement is stored changes.
        Widgets::register('content.block_group', [
            'title' => 'Block group',
            'shape' => 'tabs',
            'module' => 'content',
            'view' => 'widgets.content.block_group',
            'schema' => [
                'display' => ['type' => 'select', 'label' => 'Show as', 'default' => 'tabs',
                    'options' => ['tabs' => 'Tabs', 'accordion' => 'Accordion']],
                'count' => ['type' => 'number', 'label' => 'Blocks it holds (the ones below it)', 'default' => 2],
                'open' => ['type' => 'select', 'label' => 'Accordion at first', 'default' => 'first',
                    'options' => ['first' => 'First section open', 'none' => 'All closed']],
            ],
        ]);

        // A map from an address or a pair of coordinates, through the
        // public embed address, so a store needs no API key for it.
        Widgets::register('content.google_map', [
            'title' => 'Google map',
            'shape' => 'block',
            'module' => 'content',
            'view' => 'widgets.content.google_map',
            'schema' => [
                'location' => ['type' => 'text', 'label' => 'Address or coordinates (latitude, longitude)'],
                'zoom' => ['type' => 'number', 'label' => 'Zoom (1 to 21)', 'default' => 15],
                'height' => ['type' => 'number', 'label' => 'Height in pixels', 'default' => 400],
                'map_type' => ['type' => 'select', 'label' => 'Map', 'default' => 'roadmap',
                    'options' => ['roadmap' => 'Streets', 'satellite' => 'Satellite']],
            ],
            'resolve' => fn (array $config) => ['mapUrl' => self::mapUrl($config)],
        ]);

        // Raw HTML, drawn unescaped on purpose: it is how a merchant pastes
        // an embed or markup no other block offers. That is safe only
        // because of who can write it. A placement's config is written by
        // App\Services\LayoutSaver alone, which only the layout editors call,
        // and those sit behind the staff sign-in (the 'web' guard) and the
        // layout routes; no storefront or customer form reaches a placement.
        // Any new path that writes placements must keep this block to staff.
        Widgets::register('content.html', [
            'title' => 'HTML',
            'shape' => 'text',
            'module' => 'content',
            'view' => 'widgets.content.html',
            'schema' => [
                'html' => ['type' => 'textarea', 'label' => 'HTML', 'code' => true],
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
     * The embed address for a map block, or null when there is nothing to
     * show. Coordinates are recognised and passed as numbers; anything else
     * is searched for as an address. Google's public embed endpoint takes
     * either without a key.
     */
    public static function mapUrl(array $config): ?string
    {
        $location = trim((string) ($config['location'] ?? ''));
        if ($location === '') {
            return null;
        }

        if (preg_match('/^\s*(-?\d{1,2}(?:\.\d+)?)\s*,\s*(-?\d{1,3}(?:\.\d+)?)\s*$/', $location, $m)
            && abs((float) $m[1]) <= 90 && abs((float) $m[2]) <= 180) {
            $location = (float) $m[1] . ',' . (float) $m[2];
        }

        return 'https://maps.google.com/maps?' . http_build_query([
            'q' => mb_substr($location, 0, 200),
            'z' => max(1, min(21, (int) ($config['zoom'] ?? 15) ?: 15)),
            't' => ($config['map_type'] ?? 'roadmap') === 'satellite' ? 'k' : 'm',
            'output' => 'embed',
        ]);
    }

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
