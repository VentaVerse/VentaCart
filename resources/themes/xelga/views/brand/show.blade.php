{{-- A brand: its template's sections, with the products, filters and sort BrandController chose. --}}
<x-store-layout :title="$brand->meta_title ?: $brand->name" :meta-description="$brand->meta_description">
    @include('xelga::snippets.template', [
        'pageType' => 'brand',
        'subject' => $brand,
        'page' => compact('brand', 'products', 'sort', 'priceRange', 'availableTags'),
    ])
</x-store-layout>
