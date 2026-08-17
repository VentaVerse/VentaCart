{{-- Header layout: search-under. Slim identity row, then a
     full-width search band, then the nav band. --}}
@include('partials.header.parts.topbar')

<header class="site-header hdr-under">
    <div class="container-store flex items-center justify-between">
        @include('partials.header.parts.hamburger')

        {{-- Logo --}}
        @include('partials.header.parts.logo')

        {{-- Cart --}}
        @include('partials.header.parts.cart')
    </div>
</header>

{{-- Desktop search band --}}
<div class="hdr-under__band hidden md:block">
    <div class="container-store">
        @include('partials.header.parts.search', ['searchClass' => 'search-bar search-bar--wide flex'])
    </div>
</div>

@include('partials.header.parts.mobile-search')

{{-- Navigation Bar (Desktop) --}}
<nav class="nav-bar hidden lg:block">
    <div class="container-store flex items-center justify-center">
        @include('partials.header.parts.nav-items')
    </div>
</nav>
