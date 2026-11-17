{{--
    The product card core draws in its own pages (the wishlist): the theme's
    shared card, prepared from the one product. Prices come from core's
    Pricing, through ProductCards.
--}}
@php $xelgaCard = \Themes\Xelga\Sections\ProductCards::for([$product], \Themes\Xelga\Sections\ThemeValues::get('show_swatches') === true)[0] ?? null; @endphp
@if ($xelgaCard)
    @include('xelga::snippets.product-card', ['card' => $xelgaCard, 'idPrefix' => 'product', 'headingLevel' => 'h3'])
@endif
