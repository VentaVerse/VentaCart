<section>
@php
    // $group is resolved by the banners module for THIS placement. It used to
    // be looked up in a $bannerGroups array shared with the home view only,
    // which is why a banner could not be placed anywhere else.
    $slides = $group['slides'] ?? [];
    $autoplay = $group['autoplay'] ?? false;
    $speed = $group['speed'] ?? 5000;
@endphp

<div>
    @if (count($slides) > 0)
        <div class="hero-slider" x-data="imageSlider({ slides: {{ json_encode(array_map(function($s) { $c = $s; unset($c['layers']); return $c; }, $slides)) }}, autoplay: {{ $autoplay ? 'true' : 'false' }}, speed: {{ $speed }} })">
            @foreach ($slides as $i => $slide)
                @php
                    $hasLayers = !empty($slide['layers']);
                    $bgImage = $slide['image'] ?? null;
                    $bgColor = $slide['bg_color'] ?? '#000';
                    $bgSize = $slide['bg_size'] ?? 'cover';
                    $slideHeight = $slide['height'] ?? 500;
                    $isFullWidth = $slide['full_width'] ?? true;
                    $layers = $slide['layers'] ?? [];
                    $widthStyle = !$isFullWidth ? ' max-width: 1280px; margin-left: auto; margin-right: auto;' : '';
                @endphp

                @if ($hasLayers)
                    {{-- Advanced banner slide with layers --}}
                    <div class="slide relative overflow-hidden banner-advanced-slide" :class="{ 'active': current === {{ $i }} }"
                         style="--slide-height: {{ $slideHeight }}px; background-color: {{ $bgColor }}; background-size: {{ $bgSize }}; background-position: center; background-repeat: no-repeat;{{ $bgImage ? " background-image: url($bgImage);" : '' }}{{ $widthStyle }}">

                        @if (!empty($slide['url']))
                            <a href="{{ $slide['url'] }}" class="absolute inset-0 z-0" @if (!empty($slide['new_tab'])) target="_blank" rel="noopener" @endif></a>
                        @endif

                        {{-- Centered stage container for layers — scales proportionally on mobile --}}
                        <div class="banner-layers-stage">
                            @foreach ($layers as $li => $layer)
                                @php
                                    $type = $layer['type'] ?? 'text';
                                    $x = $layer['x'] ?? '0';
                                    $y = $layer['y'] ?? '0';
                                    $x = preg_match('/[%px]/', $x) ? $x : $x . 'px';
                                    $y = preg_match('/[%px]/', $y) ? $y : $y . 'px';
                                    $opacity = ($layer['opacity'] ?? 100) / 100;
                                    $anim = $layer['animation'] ?? 'none';
                                    $delay = $layer['delay'] ?? 0;
                                @endphp

                                @php
                                    $duration = $layer['duration'] ?? 800;
                                    $distance = $layer['distance'] ?? 30;
                                @endphp
                                <div class="absolute z-10"
                                     :class="{
                                         '{{ $anim !== 'none' ? 'banner-anim banner-anim-' . $anim : '' }}': current === {{ $i }},
                                         'banner-layer-hidden': current !== {{ $i }}
                                     }"
                                     style="left: {{ $x }}; top: {{ $y }}; opacity: {{ $opacity }}; animation-duration: {{ $duration }}ms; --anim-dist: {{ $distance }}px;{{ $delay ? " animation-delay: {$delay}ms;" : '' }}">

                                    @if ($type === 'text')
                                        <span style="font-size: {{ $layer['fontSize'] ?? 24 }}px; font-weight: {{ $layer['fontWeight'] ?? 400 }}; color: {{ $layer['color'] ?? '#fff' }}; letter-spacing: {{ $layer['letterSpacing'] ?? 0 }}px; text-transform: {{ $layer['textTransform'] ?? 'none' }}; white-space: pre-line;">{{ $layer['content'] ?? '' }}</span>

                                    @elseif ($type === 'image')
                                        <img src="{{ storage_url($layer['src'] ?? '') }}" alt=""
                                             style="width: {{ $layer['width'] ?? 200 }}px;{{ !empty($layer['height']) ? " height: {$layer['height']}px;" : '' }} display: block; max-width: none;"
                                             loading="{{ $i === 0 ? 'eager' : 'lazy' }}">

                                    @elseif ($type === 'button')
                                        <a href="{{ $layer['url'] ?? '#' }}" class="inline-block"
                                           style="background: {{ $layer['bgColor'] ?? '#fff' }}; color: {{ $layer['color'] ?? '#000' }}; padding: {{ $layer['paddingY'] ?? 12 }}px {{ $layer['paddingX'] ?? 32 }}px; border-radius: {{ $layer['borderRadius'] ?? 0 }}px; font-size: {{ $layer['fontSize'] ?? 14 }}px; font-weight: {{ $layer['fontWeight'] ?? 600 }}; letter-spacing: {{ $layer['letterSpacing'] ?? 1 }}px; text-transform: {{ $layer['textTransform'] ?? 'none' }}; text-decoration: none; position: relative; z-index: 20;">{{ $layer['content'] ?? 'Button' }}</a>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    {{-- Simple banner slide (background image, no layers) --}}
                    <div class="slide banner-advanced-slide" :class="{ 'active': current === {{ $i }} }"
                         style="--slide-height: {{ $slideHeight }}px; background-color: {{ $bgColor }}; background-size: {{ $bgSize }}; background-position: center; background-repeat: no-repeat;{{ $bgImage ? " background-image: url($bgImage);" : '' }}{{ $widthStyle }}">
                        @if (!empty($slide['url']))
                            <a href="{{ $slide['url'] }}" class="absolute inset-0 z-0" @if (!empty($slide['new_tab'])) target="_blank" rel="noopener" @endif></a>
                        @endif
                    </div>
                @endif
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
        <div class="hero-placeholder flex items-center justify-center">
            <div class="text-center relative z-10 px-6">
                <div class="flex items-center justify-center gap-3 mb-4">
                    <span class="led led-amber"></span>
                    <span class="led led-red"></span>
                    <span class="led led-green"></span>
                </div>
                <h1 class="text-5xl md:text-7xl tracking-[0.15em] mb-3" style="font-family: var(--font-heading); color: var(--color-primary);">
                    Welcome
                </h1>
                <p class="text-sm md:text-base text-[var(--color-text)] tracking-[0.3em] uppercase mb-8" class="font-body">
                    Set up your banner in the admin panel
                </p>
                <a href="/{{ config('app.admin_prefix', 'admin') }}" class="inline-block px-8 py-3 text-sm font-bold tracking-[0.2em] uppercase transition-all duration-300" style="font-family: var(--font-heading); background: var(--color-primary); color: var(--color-btn-text); box-shadow: 0 0 20px var(--glow-primary);">
                    Go to Admin
                </a>
            </div>
        </div>
    @endif
</div>
</section>
