@php
    $config = $section->config ?? [];
    $autoplay = $config['autoplay'] ?? false;
    $speed = $config['speed'] ?? 3000;
@endphp

@if ($brands->isNotEmpty())
    <div x-data="brandCarousel({ autoplay: {{ $autoplay ? 'true' : 'false' }}, speed: {{ $speed }} })" class="relative">
        <button @click="scrollLeft()" class="absolute left-0 top-1/2 -translate-y-1/2 z-10 bg-[#2C3340] border border-[#3A4250] rounded-full w-9 h-9 flex items-center justify-center text-[#4F6070] hover:text-[var(--color-primary)] hover:border-[var(--color-primary)] transition-all duration-300">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>

        <div x-ref="scroll" class="brand-carousel mx-12">
            @foreach ($brands as $brand)
                <a href="{{ route('brand.show', $brand->slug) }}" class="brand-item" title="{{ $brand->name }}">
                    @if ($brand->image)
                        <img src="{{ storage_url($brand->image) }}" alt="{{ $brand->name }}">
                    @else
                        <span class="text-xs text-[#4F6070] text-center" class="font-heading-wide">{{ $brand->name }}</span>
                    @endif
                </a>
            @endforeach
        </div>

        <button @click="scrollRight()" class="absolute right-0 top-1/2 -translate-y-1/2 z-10 bg-[#2C3340] border border-[#3A4250] rounded-full w-9 h-9 flex items-center justify-center text-[#4F6070] hover:text-[var(--color-primary)] hover:border-[var(--color-primary)] transition-all duration-300">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </button>
    </div>
@else
    <p class="text-center text-[#444] py-4" class="font-body">No brands to display.</p>
@endif
