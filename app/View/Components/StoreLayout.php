<?php

namespace App\View\Components;

use App\Models\Category;
use App\Models\Setting;
use App\Services\CartService;
use App\Services\ThemeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\Component;
use Illuminate\View\View;

class StoreLayout extends Component
{
    public function __construct(
        public ?string $title = null,
        public ?string $metaDescription = null,
    ) {}

    public function render(): View
    {
        $theme = app(ThemeService::class);
        $cartService = app(CartService::class);

        $siteName = $theme->get('identity.site_name', 'Store');

        // Load general SEO settings
        $seoDefaults = Cache::remember('general_seo_defaults', 3600, function () {
            return Setting::where('group', 'general')
                ->whereIn('key', ['default_meta_title', 'default_meta_description', 'default_meta_keywords', 'google_site_verification'])
                ->pluck('value', 'key')
                ->toArray();
        });

        $pageTitle = $this->title
            ? "{$this->title} - {$siteName}"
            : ($seoDefaults['default_meta_title'] ?? $siteName);

        $headingFont = $theme->get('fonts.heading', 'Oswald');
        $bodyFont = $theme->get('fonts.body', 'Roboto Slab');
        $googleFontsUrl = $this->buildGoogleFontsUrl($headingFont, $bodyFont);

        $navItems = $theme->getNavItems();

        // Build 3-level subcategory map for category nav items
        $navSubcategories = Cache::rememberForever('nav_subcategories', function () use ($navItems) {
            $categoryIds = $navItems->where('type', 'category')->pluck('category_id')->filter();
            if ($categoryIds->isEmpty()) {
                return [];
            }

            // Level 2: direct children of nav categories
            $children = Category::whereIn('parent_id', $categoryIds)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            // Level 3: grandchildren
            $childIds = $children->pluck('id');
            $grandchildren = Category::whereIn('parent_id', $childIds)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->groupBy('parent_id');

            // Attach grandchildren to each child
            $result = [];
            foreach ($children->groupBy('parent_id') as $parentId => $group) {
                $result[$parentId] = $group->map(function ($child) use ($grandchildren) {
                    $arr = $child->toArray();
                    $arr['children'] = isset($grandchildren[$child->id])
                        ? $grandchildren[$child->id]->toArray()
                        : [];
                    return $arr;
                })->toArray();
            }

            return $result;
        });

        return view('layouts.store', [
            'theme' => $theme,
            'pageTitle' => $pageTitle,
            'siteName' => $siteName,
            'googleFontsUrl' => $googleFontsUrl,
            'headingFont' => $headingFont,
            'bodyFont' => $bodyFont,
            'navItems' => $navItems,
            'navSubcategories' => $navSubcategories,
            'footerColumns' => $theme->getFooterColumns(),
            'currencies' => $theme->getActiveCurrencies(),
            'defaultCurrency' => $theme->getDefaultCurrency(),
            'cartCount' => $cartService->getCount(),
            'cartSubtotal' => $cartService->getSubtotal(),
            'customerLoggedIn' => auth('customer')->check(),
            'customerUser' => auth('customer')->user(),
            'defaultMetaDescription' => $seoDefaults['default_meta_description'] ?? '',
            'defaultMetaKeywords' => $seoDefaults['default_meta_keywords'] ?? '',
            'googleSiteVerification' => $seoDefaults['google_site_verification'] ?? '',
        ]);
    }

    private function buildGoogleFontsUrl(string $headingFont, string $bodyFont): string
    {
        $fonts = [];

        $headingParam = str_replace(' ', '+', $headingFont);
        $fonts[] = "family={$headingParam}:wght@400;500;600;700";

        if ($bodyFont !== $headingFont) {
            $bodyParam = str_replace(' ', '+', $bodyFont);
            $fonts[] = "family={$bodyParam}:wght@300;400;500;700";
        }

        return 'https://fonts.googleapis.com/css2?' . implode('&', $fonts) . '&display=swap';
    }
}
