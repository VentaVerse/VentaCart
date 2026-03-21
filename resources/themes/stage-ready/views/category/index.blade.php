<x-store-layout :title="$category->meta_title ?: $category->name" :meta-description="$category->meta_description">

    {{-- Breadcrumbs --}}
    <div class="bg-[#111] border-b border-[#222]">
        <div class="container-store py-3">
            <nav class="flex items-center gap-2 text-xs text-[#666]">
                <a href="{{ route('home') }}" class="hover:text-[var(--color-primary)]">Home</a>
                @foreach ($breadcrumbs as $crumb)
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    @if ($loop->last)
                        <span class="text-[#C8C8C8] font-medium">{{ $crumb->name }}</span>
                    @else
                        <a href="{{ route('category.show', $crumb->slug) }}" class="hover:text-[var(--color-primary)]">{{ $crumb->name }}</a>
                    @endif
                @endforeach
            </nav>
        </div>
    </div>

    <div class="container-store py-6">
        <div class="flex flex-col lg:flex-row gap-6">

            {{-- Sidebar Filters --}}
            <aside class="lg:w-60 flex-shrink-0" x-data="{ filtersOpen: false }">
                <button @click="filtersOpen = !filtersOpen" class="lg:hidden w-full flex items-center justify-between p-3 bg-[#1A1A1A] border border-[#2A2A2A] rounded mb-4 text-sm font-medium text-[#C8C8C8]">
                    <span>Filters</span>
                    <svg class="w-4 h-4 transition-transform" :class="filtersOpen && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>

                <div :class="filtersOpen ? 'block' : 'hidden'" class="lg:block space-y-6">
                    @include('partials.sidebar-filters', compact('availableBrands', 'priceRange', 'availableTags'))
                </div>
            </aside>

            {{-- Product Grid --}}
            <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between mb-4">
                    <h1 class="text-xl font-semibold text-[#E8E8E8]" class="font-heading">
                        {{ $category->name }}
                        <span class="text-sm font-normal text-[#666]">({{ $products->total() }})</span>
                    </h1>

                    <div class="flex items-center gap-3">
                        <select onchange="window.location.href = updateParam('sort', this.value)" class="search-sort-select">
                            <option value="date_added" {{ $sort === 'date_added' ? 'selected' : '' }}>Date Added</option>
                            <option value="newest" {{ $sort === 'newest' ? 'selected' : '' }}>Newest</option>
                            <option value="name_asc" {{ $sort === 'name_asc' ? 'selected' : '' }}>Name A-Z</option>
                            <option value="name_desc" {{ $sort === 'name_desc' ? 'selected' : '' }}>Name Z-A</option>
                            <option value="price_asc" {{ $sort === 'price_asc' ? 'selected' : '' }}>Price: Low to High</option>
                            <option value="price_desc" {{ $sort === 'price_desc' ? 'selected' : '' }}>Price: High to Low</option>
                        </select>
                    </div>
                </div>

                {{-- Subcategories --}}
                @if ($subcategories->isNotEmpty())
                    <div class="flex flex-wrap gap-2 mb-4">
                        @foreach ($subcategories as $sub)
                            <a href="{{ route('category.show', $sub->slug) }}" class="px-3 py-1.5 text-xs text-[#888] border border-[#333] rounded-full hover:border-[var(--color-primary)] hover:text-[var(--color-primary)] transition-colors">
                                {{ $sub->name }}
                            </a>
                        @endforeach
                    </div>
                @endif

                @php
                    $colsMobile = $settings['columns_mobile'] ?? 2;
                    $colsTablet = $settings['columns_tablet'] ?? 3;
                    $colsDesktop = $settings['columns_desktop'] ?? 4;
                @endphp

                @if ($products->isNotEmpty())
                    <div id="category-grid" class="grid gap-4">
                        @foreach ($products as $product)
                            @include('partials.product-card', ['product' => $product])
                        @endforeach
                    </div>

                    <div class="mt-6">
                        {{ $products->links() }}
                    </div>
                @else
                    <div class="text-center py-12 text-[#666]">
                        <svg class="w-16 h-16 mx-auto mb-4 text-[#444]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        <p>No products found matching your criteria.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @push('head')
    <style>
        #category-grid { grid-template-columns: repeat({{ $colsMobile }}, minmax(0, 1fr)); }
        @media (min-width: 768px) { #category-grid { grid-template-columns: repeat({{ $colsTablet }}, minmax(0, 1fr)); } }
        @media (min-width: 1024px) { #category-grid { grid-template-columns: repeat({{ $colsDesktop }}, minmax(0, 1fr)); } }
    </style>
    @endpush

    @push('scripts')
    <script>
        function updateParam(key, value) {
            const url = new URL(window.location);
            url.searchParams.set(key, value);
            return url.toString();
        }
    </script>
    @endpush
</x-store-layout>
