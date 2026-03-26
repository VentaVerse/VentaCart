<x-store-layout :title="$brand->name">

    {{-- Breadcrumbs --}}
    <div class="bg-[#111] border-b border-[#222]">
        <div class="container-store py-3">
            <nav class="flex items-center gap-2 text-xs text-[#666]">
                <a href="{{ route('home') }}" class="hover:text-[var(--color-primary)]">Home</a>
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-[#B0B8C4] font-medium">{{ $brand->name }}</span>
            </nav>
        </div>
    </div>

    <div class="container-store py-8">
        {{-- Brand Header --}}
        <div class="flex items-center gap-6 mb-8">
            @if ($brand->image)
                <div class="w-24 h-24 bg-white rounded-lg border border-[#3A4250] p-3 flex items-center justify-center flex-shrink-0">
                    <img src="{{ storage_url($brand->image) }}" alt="{{ $brand->name }}" class="max-h-full max-w-full object-contain">
                </div>
            @endif
            <div>
                <h1 class="text-3xl text-[#DDDDDD]" class="font-heading-wide">{{ strtoupper($brand->name) }}</h1>
                <p class="text-sm text-[#666] mt-1">{{ $products->total() }} product{{ $products->total() !== 1 ? 's' : '' }}</p>
            </div>
        </div>

        <div class="flex flex-col lg:flex-row gap-6">
            {{-- Sidebar Filters --}}
            <aside class="lg:w-60 flex-shrink-0" x-data="{ filtersOpen: false }">
                <button @click="filtersOpen = !filtersOpen" class="lg:hidden w-full flex items-center justify-between p-3 bg-[#2C3340] border border-[#3A4250] rounded mb-4 text-sm font-medium text-[#B0B8C4]">
                    <span>Filters</span>
                    <svg class="w-4 h-4 transition-transform" :class="filtersOpen && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>

                <div :class="filtersOpen ? 'block' : 'hidden'" class="lg:block space-y-6">
                    @include('partials.sidebar-filters', compact('availableBrands', 'priceRange', 'availableTags'))
                </div>
            </aside>

            {{-- Product Grid --}}
            <div class="flex-1 min-w-0">
                <div class="flex items-center justify-end mb-4">
                    <select onchange="window.location.href = updateParam('sort', this.value)" class="search-sort-select">
                        <option value="date_added" {{ $sort === 'date_added' ? 'selected' : '' }}>Date Added</option>
                        <option value="newest" {{ $sort === 'newest' ? 'selected' : '' }}>Newest</option>
                        <option value="name_asc" {{ $sort === 'name_asc' ? 'selected' : '' }}>Name A-Z</option>
                        <option value="name_desc" {{ $sort === 'name_desc' ? 'selected' : '' }}>Name Z-A</option>
                        <option value="price_asc" {{ $sort === 'price_asc' ? 'selected' : '' }}>Price: Low to High</option>
                        <option value="price_desc" {{ $sort === 'price_desc' ? 'selected' : '' }}>Price: High to Low</option>
                    </select>
                </div>

                @if ($products->isNotEmpty())
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
                        <svg class="w-16 h-16 mx-auto mb-4 text-[#444]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                        <p class="text-lg text-[#666] mb-2">No products found for {{ $brand->name }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function updateParam(key, value) {
            const url = new URL(window.location.href);
            url.searchParams.set(key, value);
            return url.toString();
        }
    </script>
    @endpush
</x-store-layout>
