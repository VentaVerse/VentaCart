@php
    $config = $section->config ?? [];
    $width = $config['width'] ?? 'full';
    $autoplay = $config['autoplay'] ?? false;
    $speed = $config['speed'] ?? 5000;
    $slides = [];
@endphp

<div class="{{ $width === 'contained' ? 'container-store' : '' }}">
    @if (count($slides) > 0)
        <div class="hero-slider" x-data="imageSlider({ slides: {{ json_encode($slides) }}, autoplay: {{ $autoplay ? 'true' : 'false' }}, speed: {{ $speed }} })">
            @foreach ($slides as $i => $slide)
                <div class="slide" :class="{ 'active': current === {{ $i }} }">
                    @if (!empty($slide['url']))
                        <a href="{{ $slide['url'] }}" @if (!empty($slide['new_tab'])) target="_blank" rel="noopener" @endif>
                            <img src="{{ $slide['image'] }}" alt="{{ $slide['alt'] ?? '' }}" loading="{{ $i === 0 ? 'eager' : 'lazy' }}">
                        </a>
                    @else
                        <img src="{{ $slide['image'] }}" alt="{{ $slide['alt'] ?? '' }}" loading="{{ $i === 0 ? 'eager' : 'lazy' }}">
                    @endif
                </div>
            @endforeach

            @if (count($slides) > 1)
                <button @click="prev()" class="slider-arrow prev">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <button @click="next()" class="slider-arrow next">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
                <div class="slider-nav">
                    @foreach ($slides as $i => $slide)
                        <button @click="goTo({{ $i }})" :class="{ 'active': current === {{ $i }} }"></button>
                    @endforeach
                </div>
            @endif
        </div>
    @else
        {{-- Placeholder Hero --}}
        <div class="hero-placeholder flex items-center justify-center" style="min-height: 400px;">
            <div class="text-center relative z-10 px-6">
                <div class="flex items-center justify-center gap-3 mb-4">
                    <span class="led led-amber"></span>
                    <span class="led led-red"></span>
                    <span class="led led-green"></span>
                </div>
                <h1 class="text-5xl md:text-7xl tracking-[0.15em] mb-3" style="font-family: var(--font-heading); color: var(--color-primary);">
                    NEW GEAR DAY
                </h1>
                <p class="text-sm md:text-base text-[#555] tracking-[0.3em] uppercase mb-8" class="font-body">
                    Guitar Effects &bull; Cables &bull; Parts
                </p>
                <a href="#" class="inline-block px-8 py-3 text-sm font-bold tracking-[0.2em] uppercase transition-all duration-300" style="font-family: var(--font-heading); background: var(--color-primary); color: #0D0D0D; box-shadow: 0 0 20px var(--glow-primary);">
                    SHOP NOW
                </a>
            </div>
        </div>
    @endif
</div>
