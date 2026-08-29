@php
    // Resolved before <html> because the tag carries the page type: the
    // preview reads it from there to say which page it is looking at.
    $vPageType = \App\Support\StorefrontPageType::current();
    $vPreview = \App\Support\ThemePreview::active();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"@if ($vPageType) data-page-type="{{ $vPageType }}"@endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $pageTitle }}</title>

    @if ($metaDescription)
        <meta name="description" content="{{ $metaDescription }}">
    @elseif (!empty($defaultMetaDescription))
        <meta name="description" content="{{ $defaultMetaDescription }}">
    @endif
    @if (!empty($defaultMetaKeywords))
        <meta name="keywords" content="{{ $defaultMetaKeywords }}">
    @endif
    @if (!empty($googleSiteVerification))
        <meta name="google-site-verification" content="{{ $googleSiteVerification }}">
    @endif

    @php
        // Canonical: prefer the controller's explicit value. Fallback strips
        // noise query params (sort, order, limit, etc.) so listings indexed
        // with different sort options canonicalize back to one URL. Keep
        // `page` because paginated pages have distinct content.
        $__canonical = $canonical ?? (function () {
            $allowed = collect(request()->query())->only(['page'])->filter();
            $base = url()->current();
            return $allowed->isEmpty() ? $base : $base . '?' . http_build_query($allowed->all());
        })();
    @endphp
    <link rel="canonical" href="{{ $__canonical }}">
    @isset($robotsMeta)
        <meta name="robots" content="{{ $robotsMeta }}">
    @endisset

    {{-- OpenGraph --}}
    <meta property="og:site_name" content="{{ $siteName }}">
    @hasSection('og_title')
        <meta property="og:title" content="@yield('og_title')">
    @else
        <meta property="og:title" content="{{ $ogTitle ?? $pageTitle }}">
    @endif
    @hasSection('og_description')
        <meta property="og:description" content="@yield('og_description')">
    @elseif (!empty($ogDescription ?? $metaDescription ?? ''))
        <meta property="og:description" content="{{ $ogDescription ?? $metaDescription }}">
    @endif
    @hasSection('og_image')
        <meta property="og:image" content="@yield('og_image')">
    @endif
    <meta property="og:type" content="website">

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">

    @if ($vFavicon = \App\Support\StoreIdentity::faviconUrl())
        <link rel="icon" href="{{ $vFavicon }}">
    @endif

    {{-- Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="{{ $googleFontsUrl }}">

    @php
        $activeTheme = app(\App\Services\ThemeService::class)->getActiveTheme();
        // theme.json is the base contract: every token defaults from it, DB values override.
        $vThemeMeta = json_decode((string) @file_get_contents(resource_path("themes/{$activeTheme}/theme.json")), true) ?: [];
        $vBaseColors = $vThemeMeta['colors'] ?? [];
        // Per-page-type overrides. Emitted AFTER the store's own values in
        // the same :root block, so the cascade does the overriding and no
        // token needs two sources of truth.
        $vPageColors = $theme->pageOverrides($vPageType, 'colors');
        $vPageVars = $theme->pageOverrides($vPageType, 'vars');
        $vOption = function (string $key, $fallback = null) use ($theme, $vThemeMeta) {
            return $theme->getThemeSetting($key, $vThemeMeta['settings'][$key]['default'] ?? $fallback);
        };
        $vRgba = function (string $hex, float $alpha) {
            $hex = ltrim($hex, '#');
            if (strlen($hex) === 3) {
                $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
            }
            if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
                return 'transparent';
            }
            [$r, $g, $b] = sscanf($hex, '%02x%02x%02x');
            return "rgba({$r}, {$g}, {$b}, {$alpha})";
        };
        $vGlowAlpha = ['none' => 0.0, 'low' => 0.15, 'medium' => 0.25, 'high' => 0.35][$vOption('glow_intensity', 'none')] ?? 0.0;
        $vNoise = filter_var($vOption('noise_texture', false), FILTER_VALIDATE_BOOL);
        $vLeds = filter_var($vOption('led_indicators', true), FILTER_VALIDATE_BOOL);
        $vReveal = filter_var($vOption('scroll_reveal', true), FILTER_VALIDATE_BOOL);
        $vHeadingStack = $theme->get('fonts.heading_stack', $vThemeMeta['fonts']['heading_stack'] ?? 'sans-serif');
        $vBodyStack = $theme->get('fonts.body_stack', $vThemeMeta['fonts']['body_stack'] ?? 'sans-serif');
    @endphp
    @vite([
        'resources/themes/' . $activeTheme . '/css/theme.css',
        'resources/themes/' . $activeTheme . '/js/theme.js'
    ])
    @if ($vPreview)
        @vite('resources/js/preview.js')
    @endif

    {{-- CSS Variables from Theme Settings (AFTER @vite so DB values override compiled defaults) --}}
    <style>
        :root {
            @foreach ($vBaseColors as $vKey => $vDefault)
                --color-{{ str_replace('_', '-', $vKey) }}: {{ $theme->get("colors.{$vKey}", $vDefault) }};
            @endforeach
            --glow-primary: {{ $vGlowAlpha > 0 ? $vRgba($theme->get('colors.primary', $vBaseColors['primary'] ?? '#2563EB'), $vGlowAlpha) : 'transparent' }};
            --glow-secondary: {{ $vGlowAlpha > 0 ? $vRgba($theme->get('colors.secondary', $vBaseColors['secondary'] ?? '#DC2626'), $vGlowAlpha + 0.05) : 'transparent' }};
            @foreach (($vThemeMeta['vars'] ?? []) as $vKey => $vDefault)
                --{{ str_replace('_', '-', $vKey) }}: {{ $theme->get("vars.{$vKey}", $vDefault) }};
            @endforeach
            --font-heading: '{{ $headingFont }}', {{ $vHeadingStack }};
            --font-body: '{{ $bodyFont }}', {{ $vBodyStack }};
            --font-price: '{{ $theme->get('fonts.price', $headingFont) }}', {{ $theme->get('fonts.price_stack', $vHeadingStack) }};
            --size-logo: {{ $theme->get('fonts.size_logo', '32') }}px;
            --size-section-title: {{ $theme->get('fonts.size_section_title', '24') }}px;
            --size-product-name: {{ $theme->get('fonts.size_product_name', '14') }}px;
            --size-price: {{ $theme->get('fonts.size_price', '16') }}px;
            @foreach ($vPageColors as $vKey => $vValue)
                --color-{{ str_replace('_', '-', $vKey) }}: {{ $vValue }};
            @endforeach
            @foreach ($vPageVars as $vKey => $vValue)
                --{{ str_replace('_', '-', $vKey) }}: {{ $vValue }};
            @endforeach
            --size-body: {{ $theme->get('fonts.size_body', '14') }}px;
            --size-nav: {{ $theme->get('fonts.size_nav', '14') }}px;
        }
    </style>
    <script>
        window.__countryIso2Map = @json(\App\Models\Country::where('is_active', true)->pluck('iso2', 'id'));
        window.__phoneCodes = @json(\App\Models\Country::where('is_active', true)->pluck('phone_code', 'id'));
        window.__addressProviders = @json($addressProviders ?? []);
        window.storageUrl = function(path) {
            if (!path) return '';
            return '/storage/' + path.split('/').map(encodeURIComponent).join('/');
        };
        window.__currency = { symbol: @json($activeCurrency?->symbol ?? '₱'), rate: {{ (float) ($activeCurrency?->rate ?? 1) }} };
        window.money = function(amount) {
            return window.__currency.symbol + (Number(amount) * window.__currency.rate).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        };
    </script>
    @stack('head')
</head>
<body x-data="mobileNav()" class="venta{{ $vNoise ? ' noise-bg' : '' }}{{ $vLeds ? '' : ' v-noleds' }}{{ $vGlowAlpha > 0 ? ' v-glow' : '' }}" data-reveal="{{ $vReveal ? '1' : '0' }}">

    @if (session('impersonating_customer_id') && Auth::guard('web')->check())
        <div class="impersonation-bar">
            <div class="container-store flex items-center justify-between py-2 text-sm">
                <span>
                    Viewing as <strong>{{ Auth::guard('customer')->user()->first_name ?? '' }} {{ Auth::guard('customer')->user()->last_name ?? '' }}</strong>
                    ({{ Auth::guard('customer')->user()->customerGroup->name ?? 'No Group' }})
                </span>
                <a href="{{ route('admin.impersonate.stop') }}" class="impersonation-bar-link">
                    Back to Admin
                </a>
            </div>
        </div>
    @endif

    {{-- Announcement Bar --}}
    @if ($theme->get('header.announcement_enabled'))
        <div class="announcement-bar">
            @if ($theme->get('header.announcement_link'))
                <a href="{{ $theme->get('header.announcement_link') }}">
                    {{ $theme->get('header.announcement_text') }}
                </a>
            @else
                {{ $theme->get('header.announcement_text') }}
            @endif
        </div>
    @endif

    @php
        $vHeaderLayout = (string) $vOption('header_layout', 'marketplace');
        if (! in_array($vHeaderLayout, ['marketplace', 'centered', 'inline', 'search-under'], true)) {
            $vHeaderLayout = 'marketplace';
        }
    @endphp
    @include('partials.header.' . $vHeaderLayout)

    {{-- Mobile Navigation --}}
    <template x-if="open">
        <div>
            <div class="mobile-nav-overlay" @click="close()"></div>
            <div class="mobile-nav" :class="open ? 'translate-x-0' : '-translate-x-full'">
                <div class="flex items-center justify-between p-5 border-b border-[var(--color-border)]">
                    <span class="text-[var(--color-primary)] text-lg font-bold" class="font-heading">MENU</span>
                    <button @click="close()" class="text-[var(--color-text-muted)] hover:text-[var(--color-primary)] transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                @foreach ($navTree as $node)
                    @include('partials.header.parts.mobile-node', ['node' => $node])
                @endforeach
            </div>
        </div>
    </template>

    {{-- Site-wide regions live HERE, not in each page: a slot that exists on
         every page should not depend on 23 views each remembering to call it. --}}
    <x-storefront.region name="header_top" />

    {{-- Page Content --}}
    <main class="relative z-[1]">
        {{ $slot }}
    </main>

    <x-storefront.region name="footer_top" />

    @if (\App\Support\PageSetting::on('show_footer'))

    {{-- Footer --}}
    @php
        $vFooterLayout = (string) $vOption('footer_layout', 'columns');
        if (! in_array($vFooterLayout, ['columns', 'centered', 'split'], true)) {
            $vFooterLayout = 'columns';
        }
    @endphp
    @include('partials.footer.' . $vFooterLayout)

    {{-- Back to Top --}}
    <button
        x-data="backToTop()"
        x-show="visible"
        @click="scrollUp()"
        x-cloak
        x-transition.opacity
        class="back-to-top"
        title="Back to top"
    >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
    </button>
    @endif


    @stack('scripts')
</body>
</html>
