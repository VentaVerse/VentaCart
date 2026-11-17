{{-- Search results: the search template's sections, with what core's search found. --}}
<x-store-layout :title="$query !== '' ? 'Search: ' . $query : 'Search'">
    @include('xelga::snippets.template', ['pageType' => 'search', 'page' => compact('products', 'query', 'sort')])
</x-store-layout>
