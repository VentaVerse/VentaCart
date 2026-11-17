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
                    // The banners module hands over checked values
                    // (Extensions\Banners\BannerData); they are checked again
                    // here because they print into styles and expressions.
                    $layers = \Extensions\Banners\BannerData::layers($slide['layers'] ?? []);
                    $hasLayers = $layers !== [];
                    $bgImage = is_string($slide['image'] ?? null) && preg_match('#^/storage/[^\s"\'()<>\\\\]+$#', $slide['image']) ? $slide['image'] : null;
                    $bgColor = \App\Support\SafeInput::color($slide['bg_color'] ?? '') ?: '#000';
                    $bgSize = \Extensions\Banners\BannerData::bgSize($slide['bg_size'] ?? 'cover');
                    $slideHeight = (int) ($slide['height'] ?? 500);
                    $isFullWidth = $slide['full_width'] ?? true;
                    $slideUrl = \App\Support\SafeInput::link($slide['url'] ?? '');
                    $widthStyle = !$isFullWidth ? ' max-width: 1280px; margin-left: auto; margin-right: auto;' : '';
                @endphp

                @if ($hasLayers)
                    {{-- Advanced banner slide with layers --}}
                    <div class="slide relative overflow-hidden banner-advanced-slide" :class="{ 'active': current === {{ $i }} }"
                         style="--slide-height: {{ $slideHeight }}px; background-color: {{ $bgColor }}; background-size: {{ $bgSize }}; background-position: center; background-repeat: no-repeat;{{ $bgImage ? " background-image: url('$bgImage');" : '' }}{{ $widthStyle }}">

                        @if ($slideUrl !== '')
                            <a href="{{ $slideUrl }}" class="absolute inset-0 z-0" @if (!empty($slide['new_tab'])) target="_blank" rel="noopener" @endif></a>
                        @endif

                        {{-- Centered stage container for layers — scales proportionally on mobile --}}
                        <div class="banner-layers-stage">
                            @foreach ($layers as $li => $layer)
                                @php
                                    $type = $layer['type'];
                                    $x = $layer['x'];
                                    $y = $layer['y'];
                                    $opacity = $layer['opacity'] / 100;
                                    $anim = $layer['animation'];
                                    $animClass = $anim !== 'none' ? 'banner-anim banner-anim-' . $anim : '';
                                    $delay = $layer['delay'];
                                    $duration = $layer['duration'];
                                    $distance = $layer['distance'];
                                @endphp
                                <div class="absolute z-10"
                                     :class="{
                                         [@js($animClass)]: current === {{ (int) $i }},
                                         'banner-layer-hidden': current !== {{ (int) $i }}
                                     }"
                                     style="left: {{ $x }}; top: {{ $y }}; opacity: {{ $opacity }}; animation-duration: {{ $duration }}ms; --anim-dist: {{ $distance }}px;{{ $delay ? " animation-delay: {$delay}ms;" : '' }}">

                                    @if ($type === 'text')
                                        <span style="font-size: {{ $layer['fontSize'] }}px; font-weight: {{ $layer['fontWeight'] }}; color: {{ $layer['color'] }}; letter-spacing: {{ $layer['letterSpacing'] }}px; text-transform: {{ $layer['textTransform'] }}; white-space: pre-line;">{{ $layer['content'] }}</span>

                                    @elseif ($type === 'image' && $layer['src'] !== '')
                                        <img src="{{ storage_url($layer['src']) }}" alt=""
                                             style="width: {{ $layer['width'] }}px;{{ $layer['height'] ? ' height: ' . $layer['height'] . 'px;' : '' }} display: block; max-width: none;"
                                             loading="{{ $i === 0 ? 'eager' : 'lazy' }}">

                                    @elseif ($type === 'button')
                                        <a href="{{ $layer['url'] !== '' ? $layer['url'] : '#' }}" class="inline-block"
                                           style="background: {{ $layer['bgColor'] }}; color: {{ $layer['color'] }}; padding: {{ $layer['paddingY'] }}px {{ $layer['paddingX'] }}px; border-radius: {{ $layer['borderRadius'] }}px; font-size: {{ $layer['fontSize'] }}px; font-weight: {{ $layer['fontWeight'] }}; letter-spacing: {{ $layer['letterSpacing'] }}px; text-transform: {{ $layer['textTransform'] }}; text-decoration: none; position: relative; z-index: 20;">{{ $layer['content'] !== '' ? $layer['content'] : 'Button' }}</a>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    {{-- Simple banner slide (background image, no layers) --}}
                    <div class="slide banner-advanced-slide" :class="{ 'active': current === {{ $i }} }"
                         style="--slide-height: {{ $slideHeight }}px; background-color: {{ $bgColor }}; background-size: {{ $bgSize }}; background-position: center; background-repeat: no-repeat;{{ $bgImage ? " background-image: url('$bgImage');" : '' }}{{ $widthStyle }}">
                        @if ($slideUrl !== '')
                            <a href="{{ $slideUrl }}" class="absolute inset-0 z-0" @if (!empty($slide['new_tab'])) target="_blank" rel="noopener" @endif></a>
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
