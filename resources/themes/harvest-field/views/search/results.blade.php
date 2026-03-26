<x-store-layout title="Search: {{ $query }}">
    <div class="bg-[#111] border-b border-[#222]">
        <div class="container-store py-3">
            <nav class="flex items-center gap-2 text-xs text-[#666]">
                <a href="{{ route('home') }}" class="hover:text-[var(--color-primary)]">Home</a>
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-[#D4D0C8] font-medium">Search Results</span>
            </nav>
        </div>
    </div>

    <div class="container-store py-8">
        <h1 class="section-title reveal">
            @if ($query)
                SEARCH RESULTS FOR "{{ strtoupper($query) }}"
            @else
                ALL PRODUCTS
            @endif
        </h1>

        @if ($products->total() > 0)
            <div class="flex items-center justify-between mb-6">
                <p class="text-sm text-[#888]">{{ $products->total() }} product{{ $products->total() !== 1 ? 's' : '' }} found</p>
                <form method="GET" action="{{ route('search.results') }}" class="flex items-center gap-2">
                    <input type="hidden" name="q" value="{{ $query }}">
                    <select name="sort" onchange="this.form.submit()" class="search-sort-select">
                        <option value="relevance" {{ $sort === 'relevance' ? 'selected' : '' }}>Relevance</option>
                        <option value="date_added" {{ $sort === 'date_added' ? 'selected' : '' }}>Date Added</option>
                        <option value="newest" {{ $sort === 'newest' ? 'selected' : '' }}>Newest</option>
                        <option value="price_asc" {{ $sort === 'price_asc' ? 'selected' : '' }}>Price: Low to High</option>
                        <option value="price_desc" {{ $sort === 'price_desc' ? 'selected' : '' }}>Price: High to Low</option>
                        <option value="name_asc" {{ $sort === 'name_asc' ? 'selected' : '' }}>Name: A-Z</option>
                        <option value="name_desc" {{ $sort === 'name_desc' ? 'selected' : '' }}>Name: Z-A</option>
                    </select>
                </form>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                @foreach ($products as $product)
                    @include('partials.product-card', ['product' => $product])
                @endforeach
            </div>

            @if ($products->hasPages())
                <div class="mt-8">
                    {{ $products->links() }}
                </div>
            @endif
        @else
            <div class="text-center py-16">
                <svg class="w-16 h-16 mx-auto mb-4 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <p class="text-lg mb-2">No products found for "{{ $query }}"</p>
                <p class="text-sm opacity-60">Try a different search term or browse our categories.</p>
            </div>
        @endif
    </div>
</x-store-layout>
