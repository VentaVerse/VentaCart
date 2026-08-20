<x-store-layout :title="$brand->name">
    <x-storefront.columns>
    <x-storefront.region name="content_top" />

    {{-- Breadcrumbs --}}
    <div class="bg-[var(--color-card-bg)] border-b border-[var(--color-border)]">
        <div class="container-store py-3">
            <nav class="flex items-center gap-2 text-xs text-[var(--color-text-muted)]">
                <a href="{{ route('home') }}" class="hover:text-[var(--color-text)]">Home</a>
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-[var(--color-text-heading)] font-medium">{{ $brand->name }}</span>
            </nav>
        </div>
    </div>

    <div class="container-store py-8">
        {{-- Brand Header --}}
        <div class="flex items-center gap-6 mb-8">
            @if ($brand->image)
                <div class="w-24 h-24 bg-white rounded-lg border border-[var(--color-border)] p-3 flex items-center justify-center flex-shrink-0">
                    <img src="{{ storage_url($brand->image) }}" alt="{{ $brand->name }}" class="max-h-full max-w-full object-contain">
                </div>
            @endif
            <div>
                <h1 class="text-3xl text-[var(--color-text-heading)]" class="font-heading-wide">{{ strtoupper($brand->name) }}</h1>
                <p class="text-sm text-[var(--color-text-muted)] mt-1">{{ $products->total() }} product{{ $products->total() !== 1 ? 's' : '' }}</p>
            </div>
        </div>

        @php
            $vCatWrap = app(\App\Services\ThemeService::class)->getThemeSetting('category_layout', 'sidebar-left') === 'offcanvas'
                ? 'flex flex-col gap-4'
                : 'flex flex-col lg:flex-row gap-6';
        @endphp
        <div class="{{ $vCatWrap }}">
            {{-- Sidebar Filters --}}
            @include('partials.listing-sidebar')

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
                        <svg class="w-16 h-16 mx-auto mb-4 text-[var(--color-input-border)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                        <p class="text-lg text-[var(--color-text-muted)] mb-2">No products found for {{ $brand->name }}</p>
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
    <x-storefront.region name="content_bottom" />
    </x-storefront.columns>
</x-store-layout>
