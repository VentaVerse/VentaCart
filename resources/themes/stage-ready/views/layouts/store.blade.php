<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $pageTitle }}</title>

    @if ($metaDescription)
        <meta name="description" content="{{ $metaDescription }}">
    @endif

    <link rel="canonical" href="{{ url()->current() }}">

    {{-- OpenGraph --}}
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:locale" content="en_PH">
    @hasSection('og_title')
        <meta property="og:title" content="@yield('og_title')">
    @else
        <meta property="og:title" content="{{ $pageTitle }}">
    @endif
    @hasSection('og_description')
        <meta property="og:description" content="@yield('og_description')">
    @endif
    @hasSection('og_image')
        <meta property="og:image" content="@yield('og_image')">
    @endif
    <meta property="og:type" content="website">

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">

    @if ($theme->get('identity.favicon'))
        <link rel="icon" href="{{ asset('storage/' . $theme->get('identity.favicon')) }}">
    @endif

    {{-- Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="{{ $googleFontsUrl }}">

    {{-- CSS Variables from Theme Settings --}}
    <style>
        :root {
            --color-primary: {{ $theme->get('colors.primary', '#F5A623') }};
            --color-secondary: {{ $theme->get('colors.secondary', '#E2293D') }};
            --color-nav-bg: {{ $theme->get('colors.nav_bg', '#111111') }};
            --color-nav-text: {{ $theme->get('colors.nav_text', '#E8E8E8') }};
            --color-footer-bg: {{ $theme->get('colors.footer_bg', '#080808') }};
            --color-footer-text: {{ $theme->get('colors.footer_text', '#B0B0B0') }};
            --color-announcement-bg: {{ $theme->get('colors.announcement_bg', '#F5A623') }};
            --color-announcement-text: {{ $theme->get('colors.announcement_text', '#0D0D0D') }};
            --font-heading: '{{ $headingFont }}', sans-serif;
            --font-body: '{{ $bodyFont }}', monospace;
            --glow-primary: {{ $theme->get('colors.primary', '#F5A623') }}40;
            --glow-secondary: {{ $theme->get('colors.secondary', '#E2293D') }}4D;
        }
    </style>

    @php $activeTheme = app(\App\Services\ThemeService::class)->getActiveTheme(); @endphp
    @vite([
        'resources/themes/' . $activeTheme . '/css/theme.css',
        'resources/themes/' . $activeTheme . '/js/theme.js'
    ])
    <script>
        window.__phoneCodes = @json(\App\Models\Country::where('is_active', true)->pluck('phone_code', 'iso2'));
        window.__addressProviders = @json($addressProviders ?? []);
        @foreach ($addressProviders ?? [] as $code => $provider)
            window.__phoneCodes['{{ $code }}'] = '{{ $provider['phoneCode'] }}';
        @endforeach
        window.storageUrl = function(path) {
            if (!path) return '';
            return '/storage/' + path.split('/').map(encodeURIComponent).join('/');
        };
    </script>
    @stack('head')
</head>
<body x-data="mobileNav()" class="noise-bg">

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

    {{-- Top Bar --}}
    <div class="topbar">
        <div class="container-store flex items-center justify-between">
            <div class="flex items-center gap-5">
                @php
                    $topbarLinks = $theme->getJson('header.topbar_links', []);
                @endphp
                @foreach ($topbarLinks as $link)
                    <a href="{{ $link['url'] ?? '#' }}">{{ $link['label'] ?? '' }}</a>
                @endforeach
            </div>
            <div class="flex items-center gap-5">
                {{-- Currency Selector --}}
                @if ($currencies->count() > 1)
                    <div class="currency-selector" x-data="currencySelector({ current: '{{ $defaultCurrency?->code ?? 'PHP' }}', symbol: '{{ $defaultCurrency?->symbol ?? '₱' }}' })">
                        <button @click="toggle()" type="button">
                            <span class="led led-green mr-1"></span>
                            <span x-text="current"></span>
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div class="dropdown" x-show="open" @click.away="open = false" x-cloak>
                            @foreach ($currencies as $currency)
                                <button @click="select('{{ $currency->code }}', '{{ $currency->symbol }}')" :class="{ 'active': current === '{{ $currency->code }}' }">
                                    {{ $currency->symbol }} {{ $currency->code }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @elseif ($defaultCurrency)
                    <span class="text-xs text-[#555]">
                        <span class="led led-green mr-1"></span>
                        {{ $defaultCurrency->code }}
                    </span>
                @endif

                {{-- Account Link --}}
                @if ($customerLoggedIn)
                    <a href="{{ route('account.dashboard') }}" class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        {{ $customerUser->first_name }}
                    </a>
                @else
                    <a href="{{ route('customer.login') }}" class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        Account
                    </a>
                @endif
            </div>
        </div>
    </div>

    {{-- Header --}}
    <header class="site-header">
        <div class="container-store flex items-center justify-between">
            {{-- Mobile menu toggle --}}
            <button @click="toggle()" class="lg:hidden p-2 text-[#777] hover:text-[var(--color-primary)] transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>

            {{-- Logo --}}
            <a href="{{ route('home') }}" class="logo flex-shrink-0">
                @if ($theme->get('identity.logo'))
                    <img src="{{ asset('storage/' . $theme->get('identity.logo')) }}" alt="{{ $siteName }}">
                @else
                    <span class="text-logo">
                        <span class="text-logo-new">New</span>
                        <span class="text-logo-gear">Gear</span>
                        <span class="text-logo-day">Day</span>
                    </span>
                @endif
            </a>

            {{-- Search Bar with Autocomplete --}}
            <div class="search-bar hidden md:flex" x-data="searchAutocomplete()" @click.away="close()">
                <form action="{{ route('search.results') }}" method="GET" class="flex w-full relative">
                    <input
                        type="text"
                        name="q"
                        placeholder="Search pedals, cables, parts..."
                        value="{{ request('q') }}"
                        x-model="query"
                        @input.debounce.300ms="search()"
                        @focus="if (results.length) showResults = true"
                        @keydown.escape="close()"
                        autocomplete="off"
                    >
                    <button type="submit">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </button>

                    {{-- Autocomplete Dropdown --}}
                    <div class="search-dropdown" x-show="showResults" x-cloak x-transition.opacity>
                        <template x-if="loading">
                            <div class="search-dropdown-loading">
                                <div class="skeleton-bar"></div>
                                <div class="skeleton-bar"></div>
                                <div class="skeleton-bar"></div>
                            </div>
                        </template>
                        <template x-if="!loading && results.length > 0">
                            <div>
                                <template x-for="item in results" :key="item.slug">
                                    <a :href="item.url" class="search-result-item">
                                        <div class="search-result-img">
                                            <img :src="item.image || ''" :alt="item.name" x-show="item.image" loading="lazy">
                                            <div x-show="!item.image" class="w-full h-full flex items-center justify-center text-[#333]">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            </div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="text-sm text-[#C8C8C8] truncate" x-text="item.name"></div>
                                            <div class="text-sm font-bold" style="font-family: var(--font-heading); color: var(--color-primary); letter-spacing: 0.05em;" x-text="'{{ $defaultCurrency?->symbol ?? '₱' }}' + item.price"></div>
                                        </div>
                                    </a>
                                </template>
                            </div>
                        </template>
                        <template x-if="!loading && results.length === 0 && searched">
                            <div class="px-4 py-6 text-center text-sm text-[#555]">No products found</div>
                        </template>
                    </div>
                </form>
            </div>

            {{-- Cart --}}
            <div class="relative flex-shrink-0" x-data="miniCart({{ $cartCount }}, {{ $cartSubtotal }}, '{{ $defaultCurrency?->symbol ?? '₱' }}')" @mouseenter="onHover()" @mouseleave="onLeave()">
                <a href="{{ route('cart.index') }}" class="cart-btn" x-on:cart-updated.window="onCartUpdated($event.detail)">
                    <span class="hidden sm:inline font-mono text-xs cart-text" x-text="count + ' item(s) - ' + symbol + subtotal.toFixed(2)">{{ $cartCount }} item(s) - {{ $defaultCurrency?->symbol ?? '₱' }}{{ number_format($cartSubtotal, 2) }}</span>
                    <div class="relative">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
                        <span class="cart-badge absolute -top-1.5 -right-1.5 text-[10px] font-bold w-5 h-5 rounded-full flex items-center justify-center" style="background: var(--color-primary); color: #0D0D0D; box-shadow: 0 0 8px var(--glow-primary);" x-text="count">{{ $cartCount }}</span>
                    </div>
                </a>

                {{-- Mini Cart Dropdown --}}
                <div x-show="open" x-transition.opacity.duration.200ms class="mini-cart-dropdown" @mouseenter="clearLeaveTimer()" @mouseleave="onLeave()">
                    <template x-if="loading">
                        <div class="mini-cart-loading">Loading...</div>
                    </template>
                    <template x-if="!loading && items.length === 0">
                        <div class="mini-cart-empty">Your cart is empty</div>
                    </template>
                    <template x-if="!loading && items.length > 0">
                        <div>
                            <div class="mini-cart-items">
                                <template x-for="(item, i) in items" :key="i">
                                    <div class="mini-cart-item">
                                        <div class="mini-cart-item-image">
                                            <template x-if="item.image">
                                                <img :src="storageUrl(item.image)" :alt="item.name" loading="lazy">
                                            </template>
                                            <template x-if="!item.image">
                                                <div class="mini-cart-item-noimage">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                </div>
                                            </template>
                                        </div>
                                        <div class="mini-cart-item-info">
                                            <span class="mini-cart-item-name" x-text="item.name"></span>
                                            <span class="mini-cart-item-variant" x-show="item.variant_label" x-text="item.variant_label"></span>
                                        </div>
                                        <div class="mini-cart-item-qty" x-text="'x' + item.quantity"></div>
                                        <div class="mini-cart-item-total" x-text="symbol + item.line_total.toFixed(2)"></div>
                                        <button @click="removeItem(item.id, i)" class="mini-cart-item-remove" title="Remove">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </template>
                            </div>
                            <div class="mini-cart-footer">
                                <div class="mini-cart-subtotal">
                                    <span>Subtotal</span>
                                    <span x-text="symbol + cartSubtotal.toFixed(2)"></span>
                                </div>
                                <a href="{{ route('cart.index') }}" class="mini-cart-viewcart">VIEW CART</a>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </header>

    {{-- Mobile Search --}}
    <div class="md:hidden bg-[#111] border-b border-[#222] px-4 py-2">
        <div x-data="searchAutocomplete()" @click.away="close()">
            <form action="{{ route('search.results') }}" method="GET" class="relative">
                <input type="text" name="q" placeholder="Search products..." value="{{ request('q') }}"
                    x-model="query" @input.debounce.300ms="search()" @focus="if (results.length) showResults = true" @keydown.escape="close()"
                    autocomplete="off"
                    class="w-full bg-[#1A1A1A] border border-[#2A2A2A] rounded-lg text-sm text-[#C8C8C8] placeholder-[#555] px-4 py-2.5 pr-10 focus:outline-none focus:border-[var(--color-primary)]">
                <button type="submit" class="absolute right-3 top-1/2 -translate-y-1/2 text-[#555]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </button>

                {{-- Mobile Autocomplete Dropdown --}}
                <div class="search-dropdown" x-show="showResults" x-cloak x-transition.opacity>
                    <template x-if="loading">
                        <div class="search-dropdown-loading">
                            <div class="skeleton-bar"></div>
                            <div class="skeleton-bar"></div>
                        </div>
                    </template>
                    <template x-if="!loading && results.length > 0">
                        <div>
                            <template x-for="item in results" :key="item.slug">
                                <a :href="item.url" class="search-result-item">
                                    <div class="search-result-img">
                                        <img :src="item.image || ''" :alt="item.name" x-show="item.image" loading="lazy">
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm text-[#C8C8C8] truncate" x-text="item.name"></div>
                                        <div class="text-sm font-bold" style="color: var(--color-primary);" x-text="'{{ $defaultCurrency?->symbol ?? '₱' }}' + item.price"></div>
                                    </div>
                                </a>
                            </template>
                        </div>
                    </template>
                    <template x-if="!loading && results.length === 0 && searched">
                        <div class="px-4 py-6 text-center text-sm text-[#555]">No products found</div>
                    </template>
                </div>
            </form>
        </div>
    </div>

    {{-- Navigation Bar (Desktop) --}}
    <nav class="nav-bar hidden lg:block">
        <div class="container-store flex items-center justify-center">
            @foreach ($navItems as $item)
                @php $subs = $navSubcategories[$item->category_id] ?? []; @endphp
                @if (count($subs) > 0)
                    <div class="nav-dropdown-wrap">
                        <a href="{{ $item->resolved_url }}" class="nav-dropdown-trigger">
                            {{ $item->label }}
                            <svg class="nav-dropdown-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </a>
                        <div class="nav-dropdown">
                            <a href="{{ $item->resolved_url }}" class="nav-dropdown-item nav-dropdown-all">All {{ $item->label }}</a>
                            @foreach ($subs as $sub)
                                @if (!empty($sub['children']))
                                    <div class="nav-dropdown-parent">
                                        <a href="{{ route('category.show', $sub['slug']) }}" class="nav-dropdown-item nav-dropdown-has-children">
                                            {{ $sub['name'] }}
                                            <svg class="nav-submenu-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                        </a>
                                        <div class="nav-submenu">
                                            <a href="{{ route('category.show', $sub['slug']) }}" class="nav-dropdown-item nav-dropdown-all">All {{ $sub['name'] }}</a>
                                            @foreach ($sub['children'] as $grandchild)
                                                <a href="{{ route('category.show', $grandchild['slug']) }}" class="nav-dropdown-item">{{ $grandchild['name'] }}</a>
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    <a href="{{ route('category.show', $sub['slug']) }}" class="nav-dropdown-item">{{ $sub['name'] }}</a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @else
                    <a href="{{ $item->resolved_url }}" @if ($item->new_tab) target="_blank" rel="noopener" @endif>
                        {{ $item->label }}
                    </a>
                @endif
            @endforeach
        </div>
    </nav>

    {{-- Mobile Navigation --}}
    <template x-if="open">
        <div>
            <div class="mobile-nav-overlay" @click="close()"></div>
            <div class="mobile-nav" :class="open ? 'translate-x-0' : '-translate-x-full'">
                <div class="flex items-center justify-between p-5 border-b border-[#1A1A1A]">
                    <span class="text-[var(--color-primary)] text-lg tracking-[0.2em]" class="font-heading">MENU</span>
                    <button @click="close()" class="text-[#555] hover:text-[var(--color-primary)] transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                @foreach ($navItems as $item)
                    @php $subs = $navSubcategories[$item->category_id] ?? []; @endphp
                    <a href="{{ $item->resolved_url }}" @if ($item->new_tab) target="_blank" rel="noopener" @endif>
                        {{ $item->label }}
                    </a>
                    @if (count($subs) > 0)
                        <div class="mobile-nav-subs">
                            @foreach ($subs as $sub)
                                <a href="{{ route('category.show', $sub['slug']) }}">{{ $sub['name'] }}</a>
                                @if (!empty($sub['children']))
                                    <div class="mobile-nav-subs">
                                        @foreach ($sub['children'] as $grandchild)
                                            <a href="{{ route('category.show', $grandchild['slug']) }}">{{ $grandchild['name'] }}</a>
                                        @endforeach
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </template>

    {{-- Page Content --}}
    <main class="relative z-[1]">
        {{ $slot }}
    </main>

    {{-- Footer --}}
    <footer class="site-footer mt-16 pt-12 pb-6">
        <div class="container-store">
            <hr class="divider-glow mb-10">

            <div class="grid grid-cols-2 md:grid-cols-{{ $footerColumns->count() ?: 3 }} gap-10 mb-10">
                @foreach ($footerColumns as $column)
                    <div>
                        <h4>{{ $column->title }}</h4>

                        @if ($column->type === 'links')
                            <div class="footer-links">
                                @foreach ($column->config['links'] ?? [] as $link)
                                    <div>
                                        <a href="{{ $link['url'] ?? '#' }}">{{ $link['label'] ?? '' }}</a>
                                    </div>
                                @endforeach
                            </div>

                        @elseif ($column->type === 'social')
                            <div class="social-icons">
                                @foreach ($column->config['links'] ?? [] as $social)
                                    <a href="{{ $social['url'] ?? '#' }}" target="_blank" rel="noopener" title="{{ $social['platform'] ?? '' }}">
                                        @if (($social['platform'] ?? '') === 'facebook')
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg>
                                        @elseif (($social['platform'] ?? '') === 'instagram')
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                                        @elseif (($social['platform'] ?? '') === 'youtube')
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                                        @elseif (($social['platform'] ?? '') === 'tiktok')
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>
                                        @else
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                        @endif
                                    </a>
                                @endforeach
                            </div>

                        @elseif ($column->type === 'payment_icons')
                            <div class="payment-icons">
                                @foreach ($column->config['icons'] ?? [] as $icon)
                                    <span class="inline-block bg-[#1A1A1A] border border-[#2A2A2A] rounded px-3 py-1.5 text-xs text-[#777]">{{ $icon }}</span>
                                @endforeach
                            </div>

                        @elseif ($column->type === 'text')
                            <div class="text-sm text-[#666]">
                                {!! nl2br(e($column->config['content'] ?? '')) !!}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <hr class="divider-glow mb-6">
            <div class="text-center text-xs text-[#444] tracking-wider" class="font-body">
                &copy; {{ date('Y') }} {{ $siteName }}. All rights reserved.
            </div>
        </div>
    </footer>

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

    @stack('scripts')
</body>
</html>
