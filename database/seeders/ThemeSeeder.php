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
        // Settings
        $settings = [
            'identity' => [
                'site_name' => 'Venta',
                'logo' => null,
                'favicon' => null,
            ],
            'colors' => [
                'primary' => '#E2293D',
                'secondary' => '#0F3A8D',
                'nav_bg' => '#0F3A8D',
                'nav_text' => '#FFFFFF',
                'footer_bg' => '#1E293B',
                'footer_text' => '#FFFFFF',
                'announcement_bg' => '#E2293D',
                'announcement_text' => '#FFFFFF',
            ],
            'fonts' => [
                'heading' => 'Oswald',
                'body' => 'Roboto Slab',
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

        foreach ($settings as $group => $items) {
            foreach ($items as $key => $value) {
                Setting::updateOrCreate(
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
                    'width' => 'full',
                    'autoplay' => true,
                    'speed' => 5000,
                    'slides' => [],
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
        if (NavMenuItem::count() === 0) {
            $categories = \App\Models\Category::whereNull('parent_id')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->take(6)
                ->get();

            $sort = 1;
            foreach ($categories as $cat) {
                NavMenuItem::create([
                    'label' => $cat->name,
                    'type' => 'category',
                    'category_id' => $cat->id,
                    'sort_order' => $sort++,
                ]);
            }

            NavMenuItem::create([
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

        // Default Currency
        if (Currency::count() === 0) {
            Currency::create([
                'code' => 'PHP',
                'name' => 'Philippine Peso',
                'symbol' => '₱',
                'rate' => 1.000000,
                'is_default' => true,
                'is_enabled' => true,
            ]);
        }
    }
}
