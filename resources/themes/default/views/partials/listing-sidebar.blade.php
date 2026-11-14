{{-- The filter sidebar for listing pages (category, brand), beside the
     grid; behind a Filters button on small screens. --}}
<aside class="lg:w-60 flex-shrink-0" x-data="{ filtersOpen: false }">
    <button @click="filtersOpen = !filtersOpen" class="lg:hidden w-full flex items-center justify-between p-3 bg-[var(--color-search-bg)] rounded mb-4 text-sm font-medium">
        <span>Filters</span>
        <svg class="w-4 h-4 transition-transform" :class="filtersOpen && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
    </button>

    <div :class="filtersOpen ? 'block' : 'hidden'" class="lg:block space-y-6">
        @include('partials.sidebar-filters', compact('availableBrands', 'priceRange', 'availableTags'))
    </div>
</aside>
