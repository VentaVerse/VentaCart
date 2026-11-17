{{--
    The page frame: header group, the page's content, footer group and the
    four regions every theme draws. Rendered through core's x-store-layout,
    which hands it the page title, the store's name and the cart count.
    The page's sections are drawn before this frame, so main can name the
    width of the content area (data-content, in twelfths) when sides
    narrow it; a page the whole page wide carries none. Sides stand beside
    main in a page wrapper, left before it and right after it, so the
    reading order, the phone's stacking order and the skip link's target
    all agree. Header and footer stay outside, the whole page wide.
--}}
@php
    $xelga = app(\Themes\Xelga\Rendering\Storefront::class);
    $xelgaPageType = \App\Support\StorefrontPageType::current();
    $xelgaPreload = $xelga->fontPreload();
    $xelgaCanonical = $canonical ?? (function () {
        $allowed = collect(request()->query())->only(['page'])->filter();

        return $allowed->isEmpty() ? url()->current() : url()->current() . '?' . http_build_query($allowed->all());
    })();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @if ($xelgaPageType) data-page-type="{{ $xelgaPageType }}" @endif @include('xelga::snippets.html-attributes')>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }}</title>
    @if (! empty($metaDescription))
        <meta name="description" content="{{ $metaDescription }}">
    @elseif (! empty($defaultMetaDescription))
        <meta name="description" content="{{ $defaultMetaDescription }}">
    @endif
    @if (! empty($googleSiteVerification))
        <meta name="google-site-verification" content="{{ $googleSiteVerification }}">
    @endif
    <link rel="canonical" href="{{ $xelgaCanonical }}">
    @isset($robotsMeta)
        <meta name="robots" content="{{ $robotsMeta }}">
    @endisset
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:type" content="website">
    @if ($xelgaFavicon = \App\Support\StoreIdentity::faviconUrl())
        <link rel="icon" href="{{ $xelgaFavicon }}">
    @endif
    @if ($xelgaPreload)
        <link rel="preload" href="{{ $xelgaPreload }}" as="font" type="font/woff2" crossorigin>
    @endif
    @vite(['resources/themes/xelga/css/theme.css', 'resources/themes/xelga/js/theme.js'])
    {{ \Themes\Xelga\Extensions\Extensions::current()->stylesheetTags() }}
    <link rel="stylesheet" href="{{ $xelga->tokensUrl() }}">
    @if ($xelga->preview())
        @vite('resources/themes/xelga/editor/js/frame.js')
    @endif
    @stack('head')
</head>
<body class="scheme-1">
    <a class="skip-link" href="#main-content">Skip to content</a>
    @include('xelga::snippets.icons')

    {{ $xelga->group('header') }}

    @if ($xelgaSides = $xelga->sides())
    <div class="xp" data-sides="{{ $xelgaSides }}">
        @if ($xelgaSides !== 'right')
        <aside class="xp__side xp__side--left" aria-label="{{ __('Left side') }}">{{ $xelga->side('left') }}</aside>
        @endif
        <main id="main-content" class="xp__content" data-content="{{ $xelga->contentSpan() }}">
            {{ $slot }}
        </main>
        @if ($xelgaSides !== 'left')
        <aside class="xp__side xp__side--right" aria-label="{{ __('Right side') }}">{{ $xelga->side('right') }}</aside>
        @endif
    </div>
    @else
    <main id="main-content"@if ($xelga->contentSpan() < 12) data-content="{{ $xelga->contentSpan() }}"@endif>
        {{ $slot }}
    </main>
    @endif

    {{ $xelga->group('footer') }}

    @include('xelga::snippets.cart-drawer')

    @if ($xelga->preview())
        <script type="application/json" id="xelga-page-data">@json($xelga->pageData())</script>
    @endif
    @stack('scripts')
</body>
</html>
