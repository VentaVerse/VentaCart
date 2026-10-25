<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\FooterColumn;
use App\Models\HomepageSection;
use App\Models\NavMenuItem;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class ThemeSeeder extends Seeder
{
    public function run(): void
    {
        // Colors and fonts come from the one theme's theme.json. There is no
        // theme choice to read: looks are presets over this contract.
        $activeTheme = \App\Services\ThemeService::THEME;
        $themePath = resource_path("themes/{$activeTheme}/theme.json");
        $themeJson = file_exists($themePath) ? json_decode(file_get_contents($themePath), true) : [];

        $themeColors = $themeJson['colors'] ?? [
            'primary' => '#F5A623',
            'secondary' => '#E2293D',
            'bg' => '#0D0D0D',
            'text' => '#C8C8C8',
            'text_heading' => '#E8E8E8',
            'text_muted' => '#999999',
            'card_bg' => '#1A1A1A',
            'border' => '#2A2A2A',
            'btn_text' => '#0D0D0D',
            'nav_bg' => '#111111',
            'nav_text' => '#E8E8E8',
            'footer_bg' => '#080808',
            'footer_text' => '#B0B0B0',
            'announcement_bg' => '#F5A623',
            'announcement_text' => '#0D0D0D',
        ];

        // Settings
        $settings = [
            'identity' => [
                'site_name' => 'VentaCart',
                // Filled in below, only for a store that has never had one.
                'logo' => null,
                'favicon' => null,
                'active_theme' => $activeTheme,
                'active_preset' => 'standard',
            ],
            'colors' => $themeColors,
            'fonts' => [
                'heading' => $themeJson['fonts']['heading'] ?? 'Inter',
                'body' => $themeJson['fonts']['body'] ?? 'Inter',
                'heading_stack' => $themeJson['fonts']['heading_stack'] ?? 'sans-serif',
                'body_stack' => $themeJson['fonts']['body_stack'] ?? 'sans-serif',
            ],
            'header' => [
                'announcement_enabled' => '0',
                'announcement_text' => '',
                'announcement_link' => '',
                'topbar_links' => json_encode([
                    ['label' => 'Delivery & Payment', 'url' => '/delivery'],
                ]),
            ],
            'badges' => [
                'new_count' => '10',
                'hot_count' => '5',
            ],
            'filters' => [
                'price_enabled' => '1',
                'availability_enabled' => '1',
                'brand_enabled' => '1',
                'rating_enabled' => '0',
                'on_sale_enabled' => '1',
                'tags_enabled' => '1',
            ],
            'product_page' => [
                'show_ratings' => '1',
                'show_stock' => '1',
                'show_brand' => '1',
                'show_badges' => '1',
                'thumbnail_position' => 'left',
                'below_modules' => json_encode(['related', 'you_might_like', 'same_brand', 'related_articles']),
            ],
            'category_page' => [
                'products_per_page' => '12',
                'columns_mobile' => '2',
                'columns_tablet' => '3',
                'columns_desktop' => '4',
                'default_sort' => 'default',
            ],
            'blog' => [
                'posts_per_page' => '12',
                'default_view' => 'grid',
                'excerpt_length' => '150',
                'show_view_count' => '1',
                'show_related_articles' => '1',
            ],
            'currency' => [
                'show_selector' => '0',
            ],
        ];

        // firstOrCreate, NOT updateOrCreate. These are DEFAULTS, and a store
        // that has answered for itself owns that answer forever: seeders run
        // again on every upgrade, so overwriting here would reset the site
        // name, blank the logo and favicon, and throw away the palette every
        // time somebody deployed. A key the store genuinely lacks is still
        // filled, which is the whole job. Same contract the blocks below have
        // always had, and the one AutomationSeeder documents.
        // A brand new store opens wearing VentaCart's own logo and favicon.
        // The upload screen deletes the file it replaces, so the store gets a
        // copy on the public disk and the shipped original stays untouched.
        // Only a key the store has never had gets one: a store that removed
        // its logo keeps its null, the same answer-is-owned rule as below.
        foreach (['logo', 'favicon'] as $mark) {
            if (! Setting::where('group', 'identity')->where('key', $mark)->exists()) {
                $settings['identity'][$mark] = $this->shipMark($mark);
            }
        }

        foreach ($settings as $group => $items) {
            foreach ($items as $key => $value) {
                Setting::firstOrCreate(
                    ['group' => $group, 'key' => $key],
                    ['value' => $value]
                );
            }
        }

        // Homepage Sections
        if (HomepageSection::count() === 0) {
            HomepageSection::create([
                'type' => 'banner',
                'title' => 'Hero Slider',
                'config' => [
                    'banner_group' => 'homepage-hero',
                ],
                'sort_order' => 1,
            ]);

            HomepageSection::create([
                'type' => 'brands',
                'title' => 'Brands',
                'config' => [
                    'per_page' => 8,
                    'autoplay' => true,
                    'speed' => 3000,
                ],
                'sort_order' => 2,
            ]);

            HomepageSection::create([
                'type' => 'products',
                'title' => 'New Arrivals',
                'config' => [
                    'filter' => 'new',
                    'limit' => 12,
                    'columns_mobile' => 2,
                    'columns_tablet' => 3,
                    'columns_desktop' => 6,
                ],
                'sort_order' => 3,
            ]);

            HomepageSection::create([
                'type' => 'products',
                'title' => 'On Sale',
                'config' => [
                    'filter' => 'specials',
                    'limit' => 6,
                    'columns_mobile' => 2,
                    'columns_tablet' => 3,
                    'columns_desktop' => 6,
                ],
                'sort_order' => 4,
            ]);
        }

        // Nav Menu Items
        $mainMenu = \App\Models\NavMenu::main();

        if (NavMenuItem::count() === 0) {
            $categories = \App\Models\Category::whereNull('parent_id')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->take(6)
                ->get();

            $sort = 1;
            foreach ($categories as $cat) {
                NavMenuItem::create([
                    'menu_id' => $mainMenu->id,
                    'label' => $cat->name,
                    'type' => 'category',
                    'category_id' => $cat->id,
                    'sort_order' => $sort++,
                ]);
            }

            NavMenuItem::create([
                'menu_id' => $mainMenu->id,
                'label' => 'Blog',
                'type' => 'custom',
                'url' => '/blog',
                'sort_order' => $sort,
            ]);
        }

        // Footer Columns
        if (FooterColumn::count() === 0) {
            FooterColumn::create([
                'title' => 'Information',
                'type' => 'links',
                'config' => [
                    'items' => [
                        ['label' => 'About Us', 'url' => '/about'],
                        ['label' => 'Privacy Policy', 'url' => '/privacy'],
                        ['label' => 'Terms & Conditions', 'url' => '/terms'],
                    ],
                ],
                'sort_order' => 1,
            ]);

            FooterColumn::create([
                'title' => 'Customer Service',
                'type' => 'links',
                'config' => [
                    'items' => [
                        ['label' => 'Contact Us', 'url' => '/contact'],
                        ['label' => 'Returns', 'url' => '/returns'],
                        ['label' => 'Sitemap', 'url' => '/sitemap'],
                    ],
                ],
                'sort_order' => 2,
            ]);

            FooterColumn::create([
                'title' => 'Follow Us',
                'type' => 'social',
                'config' => [
                    'items' => [
                        ['platform' => 'facebook', 'url' => '#'],
                        ['platform' => 'instagram', 'url' => '#'],
                    ],
                ],
                'sort_order' => 3,
            ]);
        }

        // Default Currency — generic platform default. Sites that operate in
        // a different currency override STORE_CURRENCY in .env before seeding,
        // or add their own currency in admin and disable USD afterwards.
        if (Currency::count() === 0) {
            $currencyDefaults = [
                'USD' => ['name' => 'US Dollar',        'symbol' => '$'],
                'EUR' => ['name' => 'Euro',             'symbol' => '€'],
                'GBP' => ['name' => 'British Pound',    'symbol' => '£'],
                'PHP' => ['name' => 'Philippine Peso',  'symbol' => '₱'],
                'JPY' => ['name' => 'Japanese Yen',     'symbol' => '¥'],
                'AUD' => ['name' => 'Australian Dollar','symbol' => 'A$'],
                'CAD' => ['name' => 'Canadian Dollar',  'symbol' => 'C$'],
            ];
            $code = strtoupper(env('STORE_CURRENCY', 'USD'));
            $info = $currencyDefaults[$code] ?? ['name' => $code, 'symbol' => $code];

            Currency::create([
                'code' => $code,
                'name' => $info['name'],
                'symbol' => $info['symbol'],
                'rate' => 1.000000,
                'is_default' => true,
                'is_enabled' => true,
            ]);
        }
    }

    /** Copy a shipped VentaCart mark onto the public disk; its path, or null when it is missing. */
    private function shipMark(string $mark): ?string
    {
        $source = resource_path("branding/ventacart-{$mark}.png");
        if (! is_file($source)) {
            return null;
        }

        $path = "identity/ventacart-{$mark}.png";
        \Illuminate\Support\Facades\Storage::disk('public')->put($path, file_get_contents($source));

        return $path;
    }
}
