{{-- Header layout: inline slim. One row: logo, nav inline, search
     behind an icon, compact cart. No separate nav band. --}}
@include('partials.header.parts.topbar')

<header class="site-header hdr-inline">
    <div class="container-store hdr-inline__row" x-data="{ searchOpen: false }">
        @include('partials.header.parts.hamburger')

        {{-- Logo --}}
        @include('partials.header.parts.logo')

        {{-- Inline navigation --}}
        <div class="nav-bar nav-bar--inline hidden lg:flex">
            @include('partials.header.parts.nav-items')
        </div>

        <div class="hdr-inline__actions">
            <button type="button" @click="searchOpen = !searchOpen"
                    class="hidden md:inline-flex p-2 text-[var(--color-text-muted)] hover:text-[var(--color-primary)] transition-colors"
                    aria-label="Search">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </button>
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
