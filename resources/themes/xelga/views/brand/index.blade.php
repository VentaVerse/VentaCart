{{-- Every brand with products in the store, as BrandController lists them. --}}
<x-store-layout title="Brands">
    @include('xelga::snippets.template', ['pageType' => 'brand-list', 'page' => compact('brands')])
</x-store-layout>
