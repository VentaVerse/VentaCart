<?php

namespace Extensions\Theme;

use App\Services\ThemeService;

/**
 * Which colour controls the theme editor shows, and how they are grouped.
 *
 * This lived inside index.blade.php, where nothing could test it and the
 * grouping could not be reworked without editing markup. It is a map, not
 * markup, so it lives here: the editor renders whatever this returns.
 *
 * The contract worth keeping is coverage. Every colour a theme declares in its
 * theme.json must appear here exactly once, or the editor quietly stops being
 * the whole theme: a merchant sees a control for some colours and hardcoded
 * results for others, with nothing on screen to say which is which.
 * ThemeTokenCoverageTest enforces both directions.
 */
class ColorGroups
{
    /** @return array<string, array<string, string>> group label => [token => control label] */
    public static function all(): array
    {
        return [
            'Storefront' => [
                'primary' => 'Buttons & Links',
                'secondary' => 'Sale Badges & Accents',
                'btn_text' => 'Text on Buttons',
                'price' => 'Product Prices',
            ],
            'Product Cards' => [
                'badge_new' => '"New" Badge',
                'badge_hot' => '"Hot" Badge',
                'badge_free_shipping' => '"Free Shipping" Badge',
                'star_rating' => 'Star Ratings',
                'sale_price' => 'Sale Price',
                'card_hover' => 'Card Hover Effect',
                'brand_tile_bg' => 'Brand Tile Background',
                'overlay_btn_bg' => 'Buttons Over Images',
            ],
            'Page' => [
                'bg' => 'Page Background',
                'card_bg' => 'Product Card Background',
                'border' => 'Lines & Dividers',
                'input_bg' => 'Form Field Background',
                'input_border' => 'Form Field Borders',
                'breadcrumb_bg' => 'Breadcrumb Bar',
                'dropdown_bg' => 'Dropdown & Popup Background',
                'sidebar_bg' => 'Sidebar Background',
                'success' => 'Success / In Stock',
                'error' => 'Error / Out of Stock',
            ],
            'Checkout' => [
                'checkout_option_bg' => 'Payment & Shipping Cards',
                'review_bg' => 'Review Cards',
            ],
            'Status Messages' => [
                'danger_bg' => 'Error Panel Background',
                'danger_border' => 'Error Panel Border',
                'danger_text' => 'Error Panel Text',
                'danger_accent' => 'Error Accents',
                'success_bg' => 'Success Panel Background',
                'success_text' => 'Success Panel Text',
                'success_accent' => 'Success Accents',
                'warning' => 'Warning / Pending',
            ],
            'Text' => [
                'text' => 'Paragraph Text',
                'text_heading' => 'Titles & Headings',
                'text_muted' => 'Hints & Small Descriptions',
                'content_text' => 'Product Description Text',
            ],
            'Header' => [
                'nav_bg' => 'Background',
                'nav_text' => 'Cart, Menu & Account Text',
                'search_bg' => 'Search Bar',
                'announcement_bg' => 'Announcement Background',
                'announcement_text' => 'Announcement Text',
                'logo_text' => 'Logo / Store Name',
            ],
            'Footer' => [
                'footer_bg' => 'Background',
                'footer_text' => 'Links & Text',
                'footer_heading' => 'Section Titles',
                'payment_badge_bg' => 'Payment Method Chips',
            ],
        ];
    }

    /** Every token the editor exposes, flattened. */
    public static function tokens(): array
    {
        return array_merge(...array_values(array_map('array_keys', self::all())));
    }

    /** Every colour the active theme declares in its theme.json. */
    public static function themeTokens(?string $theme = null): array
    {
        $path = app(ThemeService::class)->getThemePath($theme) . '/theme.json';
        $meta = json_decode((string) @file_get_contents($path), true) ?: [];

        return array_keys($meta['colors'] ?? []);
    }
}
