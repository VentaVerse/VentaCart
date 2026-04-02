<x-store-layout title="Brands">

    {{-- Breadcrumbs --}}
    <div class="bg-[#070E22] border-b border-[#1A2548]">
        <div class="container-store py-3">
            <nav class="flex items-center gap-2 text-xs text-[#7A8299]">
                <a href="{{ route('home') }}" class="hover:text-[var(--color-primary)]">Home</a>
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-[#C4C9D4] font-medium">Brands</span>
            </nav>
        </div>
    </div>

    <div class="container-store py-8">
        <h1 class="text-3xl text-[#E8ECF2] mb-8" style="font-family: var(--font-heading-wide)">BRANDS</h1>

        @if ($brands->isEmpty())
            <p class="text-[#7A8299] text-center py-12">No brands available.</p>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
                @foreach ($brands as $brand)
                    <a href="{{ route('brand.show', $brand) }}"
                       class="group bg-[#0F1A38] border border-[#1A2548] rounded-lg p-4 flex flex-col items-center gap-3 hover:border-[var(--color-primary)] transition-colors">
                        @if ($brand->image)
                            <div class="w-20 h-20 flex items-center justify-center">
                                <img src="{{ storage_url($brand->image) }}" alt="{{ $brand->name }}" class="max-h-full max-w-full object-contain" loading="lazy">
                            </div>
                        @else
                            <div class="w-20 h-20 flex items-center justify-center text-[#3A4566]">
                                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                        @endif
                        <div class="text-center">
                            <p class="text-sm text-[#E8ECF2] font-medium group-hover:text-[var(--color-primary)] transition-colors">{{ $brand->name }}</p>
                            <p class="text-xs text-[#7A8299] mt-0.5">{{ $brand->products_count }} product{{ $brand->products_count !== 1 ? 's' : '' }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

</x-store-layout>
