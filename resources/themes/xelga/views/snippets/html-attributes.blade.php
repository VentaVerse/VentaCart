{{--
    Attributes on <html>: the theme settings that only change how things
    look (card style, quick add, cart icon), and the addresses of core's
    cart and wishlist routes the storefront script posts to.
--}}
@php
    $xelgaHtml = \Themes\Xelga\Sections\ThemeValues::htmlAttributes();
    $xelgaRoute = static fn (string $name, array $params = []) => \Illuminate\Support\Facades\Route::has($name) ? route($name, $params, false) : null;
    $xelgaHtml['data-cart-add'] = $xelgaRoute('cart.add');
    $xelgaHtml['data-cart-page'] = $xelgaRoute('cart.index');
    $xelgaHtml['data-wishlist-add'] = $xelgaRoute('wishlist.add');
    $xelgaHtml['data-wishlist-remove'] = $xelgaRoute('wishlist.remove', ['product' => 0]) ? str_replace('/0', '/{product}', $xelgaRoute('wishlist.remove', ['product' => 0])) : null;
    // Money in the shopper's currency, written the way core's money() helper writes it.
    $xelgaCurrency = app(\App\Services\ThemeService::class)->getActiveCurrency();
    $xelgaHtml['data-money-symbol'] = $xelgaCurrency ? (string) $xelgaCurrency->symbol : '';
    $xelgaHtml['data-money-rate'] = $xelgaCurrency ? (string) (float) $xelgaCurrency->rate : '1';
@endphp
@foreach ($xelgaHtml as $xelgaName => $xelgaValue)
    @if ($xelgaValue !== null) {{ $xelgaName }}="{{ $xelgaValue }}" @endif
@endforeach
