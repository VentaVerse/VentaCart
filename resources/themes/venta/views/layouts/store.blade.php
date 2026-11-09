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

    {{-- The theme's font --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap">

    @php
        $activeTheme = app(\App\Services\ThemeService::class)->getActiveTheme();
    @endphp
    @vite([
        'resources/themes/' . $activeTheme . '/css/theme.css',
        'resources/themes/' . $activeTheme . '/js/theme.js'
    ])
    @if ($vPreview)
        @vite('resources/js/preview.js')
    @endif

    <script>
        window.__countryIso2Map = @json(\App\Models\Country::where('is_active', true)->pluck('iso2', 'id'));
        window.__phoneCodes = @json(\App\Models\Country::where('is_active', true)->pluck('phone_code', 'id'));
        window.__addressProviders = @json($addressProviders ?? []);
        window.__wishlist = @json($wishlistIds ?? []);
        window.storageUrl = function(path) {
            if (!path) return '';
            return '/storage/' + path.split('/').map(encodeURIComponent).join('/');
        };
        window.__currency = { symbol: @json((string) $activeCurrency?->symbol), rate: {{ (float) ($activeCurrency?->rate ?? 1) }} };
        window.money = function(amount) {
            return window.__currency.symbol + (Number(amount) * window.__currency.rate).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        };
    </script>
    @stack('head')
</head>
<body x-data="mobileNav()" class="venta">

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
            @if ($vAnnouncementLink = \App\Support\SafeInput::link($theme->get('header.announcement_link')))
                <a href="{{ $vAnnouncementLink }}">
                    {{ $theme->get('header.announcement_text') }}
                </a>
            @else
                {{ $theme->get('header.announcement_text') }}
            @endif
        </div>
    @endif

    @include('partials.header.marketplace')

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
    @include('partials.footer.columns')

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
