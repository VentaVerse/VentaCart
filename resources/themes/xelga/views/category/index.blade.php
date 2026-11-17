{{-- A category: its template's sections, with the products, filters and sort CategoryController chose. --}}
<x-store-layout :title="$category->meta_title ?: $category->name" :meta-description="$category->meta_description">
    @include('xelga::snippets.template', [
        'pageType' => 'category',
        'subject' => $category,
        'page' => compact('category', 'products', 'availableBrands', 'priceRange', 'availableTags', 'availableAttributes', 'subcategories', 'breadcrumbs', 'sort'),
    ])
</x-store-layout>
