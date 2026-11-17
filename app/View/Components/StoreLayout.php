<?php

namespace App\View\Components;

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

        $wishlistIds = app(\App\Services\WishlistService::class)->ids();

        // Shared with every view the page draws, not only the layout: a
        // theme's partials read the same bundle.
        \Illuminate\Support\Facades\View::share([
            'siteName' => $siteName,
            'navTree' => \App\Support\NavTree::for(\App\Models\NavMenu::MAIN),
            'footerColumns' => $theme->getFooterColumns(),
            'currencies' => $theme->getActiveCurrencies(),
            'defaultCurrency' => $theme->getDefaultCurrency(),
            'activeCurrency' => $theme->getActiveCurrency(),
            'cartCount' => $cartService->getCount(),
            'cartSubtotal' => $cartService->getSubtotal(),
            'customerLoggedIn' => auth('customer')->check(),
            'customerUser' => auth('customer')->user(),
            'wishlistIds' => $wishlistIds,
            'wishlistCount' => count($wishlistIds),
        ]);

        return view('layouts.store', [
            'theme' => $theme,
            'pageTitle' => $pageTitle,
            'siteName' => $siteName,
            // The composed main menu, resolved: what the header draws now.
            'navTree' => \App\Support\NavTree::for(\App\Models\NavMenu::MAIN),
            'footerColumns' => $theme->getFooterColumns(),
            'currencies' => $theme->getActiveCurrencies(),
            'defaultCurrency' => $theme->getDefaultCurrency(),
            'activeCurrency' => $theme->getActiveCurrency(),
            'cartCount' => $cartService->getCount(),
            'cartSubtotal' => $cartService->getSubtotal(),
            'customerLoggedIn' => auth('customer')->check(),
            'customerUser' => auth('customer')->user(),
            'wishlistIds' => $wishlistIds,
            'wishlistCount' => count($wishlistIds),
            'defaultMetaDescription' => $seoDefaults['default_meta_description'] ?? '',
            'defaultMetaKeywords' => $seoDefaults['default_meta_keywords'] ?? '',
            'googleSiteVerification' => $seoDefaults['google_site_verification'] ?? '',
        ]);
    }
}
