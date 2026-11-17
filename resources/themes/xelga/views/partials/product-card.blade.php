{{--
    The product card core draws in its own pages (the wishlist): the theme's
    shared card, prepared from the one product. Prices come from core's
    Pricing, through ProductCards. Lines extensions add are never asked for
    one card at a time: a page that has its list passes them as $cardLines,
    from one ProductCardLines::for() call.
--}}
@php
    $xelgaCard = \Themes\Xelga\Sections\ProductCards::for([$product], \Themes\Xelga\Sections\ThemeValues::get('show_swatches') === true, lines: false)[0] ?? null;
    if ($xelgaCard && ! empty($cardLines) && \Themes\Xelga\Sections\ThemeValues::get(\Themes\Xelga\Sections\CardLines::SETTING) === true) {
        $xelgaCard['ext'] = ($cardLines ?? [])[$xelgaCard['id']] ?? null;
    }
@endphp
@if ($xelgaCard)
    @include('xelga::snippets.product-card', ['card' => $xelgaCard, 'idPrefix' => 'product', 'headingLevel' => 'h3'])
@endif
