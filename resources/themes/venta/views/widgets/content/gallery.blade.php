@php
    $config = $placement->config ?? [];
    $columns = max(2, min(6, (int) ($config['columns'] ?? 4)));
    $lightbox = ($config['lightbox'] ?? 'lightbox') !== 'none';
    $images = collect(is_array($config['images'] ?? null) ? $config['images'] : [])
        ->filter(fn ($p) => is_string($p) && $p !== '' && ! str_contains($p, '..'))->values();
@endphp
@if ($images->isNotEmpty())
<section class="py-10 md:py-14">
    <div class="container-store" x-data="vLightbox({{ $images->map(fn ($p) => storage_url($p))->toJson() }})">
        @if ($placement->title)
            <h2 class="section-title reveal">{{ $placement->title }}</h2>
        @endif
        <div class="wgallery wgallery--{{ $columns }} reveal-stagger">
            @foreach ($images as $i => $path)
                @if ($lightbox)
                    <button type="button" class="wgallery__item" @click="open({{ $i }})" aria-label="Open image {{ $i + 1 }}">
                        <img src="{{ thumb_url($path) }}" alt="" loading="lazy" decoding="async">
                    </button>
                @else
                    <span class="wgallery__item"><img src="{{ thumb_url($path) }}" alt="" loading="lazy" decoding="async"></span>
                @endif
            @endforeach
        </div>
        @if ($lightbox)
            <div class="wlightbox" x-show="at !== null" x-cloak x-transition.opacity role="dialog" aria-modal="true" aria-label="Image"
                 @keydown.escape.window="close()" @keydown.arrow-right.window="next()" @keydown.arrow-left.window="prev()" @click.self="close()">
                <button type="button" class="wlightbox__btn wlightbox__btn--prev" @click="prev()" aria-label="Previous">&lsaquo;</button>
                <img class="wlightbox__img" :src="current()" alt="">
                <button type="button" class="wlightbox__btn wlightbox__btn--next" @click="next()" aria-label="Next">&rsaquo;</button>
                <button type="button" class="wlightbox__close" @click="close()" aria-label="Close">&times;</button>
            </div>
        @endif
    </div>
</section>
@endif
