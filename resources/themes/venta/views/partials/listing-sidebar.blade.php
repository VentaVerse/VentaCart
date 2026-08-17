{{-- The filter sidebar for listing pages (category, brand), arranged by
     the category_layout option: beside the grid on either side, or as an
     off-canvas panel behind a Filters button at every width. --}}
@php
    $vCatLayout = (string) app(\App\Services\ThemeService::class)->getThemeSetting('category_layout', 'sidebar-left');
    if (! in_array($vCatLayout, ['sidebar-left', 'sidebar-right', 'offcanvas'], true)) {
        $vCatLayout = 'sidebar-left';
    }
@endphp

@if ($vCatLayout === 'offcanvas')
    <aside class="w-full" x-data="{ filtersOpen: false }" x-on:keydown.escape.window="filtersOpen = false">
        <button @click="filtersOpen = true" class="inline-flex items-center gap-2 px-4 py-2 rounded border border-[var(--color-border)] bg-[var(--color-card-bg)] text-sm font-medium hover:border-[var(--color-primary)] transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M6 9h12M9 14h6M11 19h2"/></svg>
            <span>Filters</span>
        </button>

        <template x-if="filtersOpen">
            <div>
                <div class="fixed inset-0 bg-black/30 backdrop-blur-sm z-40" @click="filtersOpen = false"></div>
                <div class="fixed inset-y-0 left-0 w-80 max-w-[85vw] z-50 bg-[var(--color-dropdown-bg)] shadow-2xl overflow-y-auto p-5"
                     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
                     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full">
                    <div class="flex items-center justify-between mb-5">
                        <span class="text-sm font-semibold tracking-wide">Filters</span>
                        <button @click="filtersOpen = false" class="p-1 text-[var(--color-text-muted)] hover:text-[var(--color-primary)] transition-colors" aria-label="Close filters">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="space-y-6">
                        @include('partials.sidebar-filters', compact('availableBrands', 'priceRange', 'availableTags'))
                    </div>
                </div>
            </div>
        </template>
    </aside>
@else
    <aside class="lg:w-60 flex-shrink-0 {{ $vCatLayout === 'sidebar-right' ? 'lg:order-last' : '' }}" x-data="{ filtersOpen: false }">
        <button @click="filtersOpen = !filtersOpen" class="lg:hidden w-full flex items-center justify-between p-3 bg-[var(--color-search-bg)] rounded mb-4 text-sm font-medium">
            <span>Filters</span>
            <svg class="w-4 h-4 transition-transform" :class="filtersOpen && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>

        <div :class="filtersOpen ? 'block' : 'hidden'" class="lg:block space-y-6">
            @include('partials.sidebar-filters', compact('availableBrands', 'priceRange', 'availableTags'))
        </div>
    </aside>
@endif
