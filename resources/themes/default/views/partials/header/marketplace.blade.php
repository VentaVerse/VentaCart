{{-- Header layout: marketplace. Topbar, logo left with dominant
     search and cart right, nav band below. The original arrangement. --}}
@include('partials.header.parts.topbar')

{{-- Header --}}
<header class="site-header">
    <div class="container-store flex items-center justify-between">
        @include('partials.header.parts.hamburger')

        {{-- Logo --}}
        @include('partials.header.parts.logo')

        {{-- Search Bar with Autocomplete --}}
        @include('partials.header.parts.search')

        {{-- Cart --}}
        @include('partials.header.parts.cart')
    </div>
</header>

@include('partials.header.parts.mobile-search')

{{-- Navigation Bar (Desktop) --}}
<nav class="nav-bar hidden lg:block">
    <div class="container-store flex items-center justify-center">
        @include('partials.header.parts.nav-items')
    </div>
</nav>
