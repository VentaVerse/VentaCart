{{-- Header layout: centered boutique. Logo centered, search folds
     behind an icon on the left, cart right, nav centered below. --}}
@include('partials.header.parts.topbar')

<header class="site-header hdr-centered">
    <div class="container-store hdr-centered__row" x-data="{ searchOpen: false }">
        <div class="hdr-centered__side">
            @include('partials.header.parts.hamburger')
            <button type="button" @click="searchOpen = !searchOpen"
                    class="hidden md:inline-flex p-2 text-[var(--color-text-muted)] hover:text-[var(--color-primary)] transition-colors"
                    aria-label="Search">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </button>
        </div>

        {{-- Logo --}}
        @include('partials.header.parts.logo')

        <div class="hdr-centered__side hdr-centered__side--end">
            {{-- Cart --}}
            @include('partials.header.parts.cart')
        </div>

        {{-- Expanding search panel --}}
        <div class="hdr-searchpanel" x-show="searchOpen" x-cloak x-transition.opacity
             @click.away="searchOpen = false" @keydown.escape.window="searchOpen = false">
            @include('partials.header.parts.search', ['searchClass' => 'search-bar search-bar--panel'])
        </div>
    </div>
</header>

@include('partials.header.parts.mobile-search')

{{-- Navigation Bar (Desktop) --}}
<nav class="nav-bar hidden lg:block">
    <div class="container-store flex items-center justify-center">
        @include('partials.header.parts.nav-items')
    </div>
</nav>
