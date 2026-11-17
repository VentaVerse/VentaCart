{{--
    Product page: the product template's sections, with the bundle core's
    ProductController worked out for this shopper (prices, variants, tiers,
    reviews). The product joins the shopper's recently viewed list.
--}}
@php
    \Themes\Xelga\Sections\RecentlyViewed::remember($product);
    $xelgaFirstImage = $product->image ?: $product->images->sortBy('sort_order')->first()?->path;
@endphp
<x-store-layout :title="$product->meta_title ?: $product->name" :meta-description="$product->meta_description ?: \Illuminate\Support\Str::limit(trim(strip_tags((string) $product->description)), 160)">
    @push('head')
        @if ($xelgaFirstImage)
            <meta property="og:image" content="{{ url(storage_url($xelgaFirstImage)) }}">
        @endif
        @php
            // Built as data and encoded, so a quote in a name cannot break the JSON.
            // SKU is internal and is not published.
            $xelgaLd = [
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => (string) $product->name,
                'description' => \Illuminate\Support\Str::limit(html_entity_decode(strip_tags((string) $product->description), ENT_QUOTES | ENT_HTML5), 200),
                'offers' => [
                    '@type' => 'Offer',
                    'price' => (string) $effectivePrice,
                    'priceCurrency' => (string) (($defaultCurrency ?? null)?->code ?? ''),
                    'availability' => $product->quantity > 0 || ! $product->track_stock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                    'url' => route('product.show', $product->slug),
                ],
            ];
            if ($product->brand) {
                $xelgaLd['brand'] = ['@type' => 'Brand', 'name' => (string) $product->brand->name];
            }
            if ($xelgaFirstImage) {
                $xelgaLd['image'] = url(storage_url($xelgaFirstImage));
            }
            if ($reviewCount > 0) {
                $xelgaLd['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => number_format($averageRating, 1), 'reviewCount' => (string) $reviewCount];
            }
        @endphp
        <script type="application/ld+json">{!! json_encode($xelgaLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endpush

    @include('xelga::snippets.template', ['pageType' => 'product', 'subject' => $product, 'page' => \App\Support\PageContext::all()])
</x-store-layout>
