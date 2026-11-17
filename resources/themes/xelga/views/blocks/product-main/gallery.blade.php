{{-- Photos: a swipeable viewer with thumbnails, or stacked and grid layouts on large screens. --}}
@php $images = $data['images']; $count = count($images); @endphp
<div class="gallery" data-block-id="{{ $block['id'] }}" data-block-type="gallery" data-gallery @if ($block['disabled']) hidden @endif>
    @if ($count === 0)
        @php \Themes\Xelga\Rendering\Fold::close(); @endphp
        <div class="gallery__viewer gallery__viewer--empty"><span class="card__placeholder" aria-hidden="true"><svg class="icon" aria-hidden="true"><use href="#i-bag"></use></svg></span></div>
    @else
        <div class="gallery__viewer">
            <div class="gallery__track" data-gallery-track @if ($count > 1) role="region" aria-roledescription="carousel" aria-label="Photos of {{ $product->name }}. Use the arrow keys to move between them." tabindex="0" @endif>
                @foreach ($images as $i => $image)
                    <figure class="gallery__slide" @if ($count > 1) role="group" aria-roledescription="slide" aria-label="{{ $i + 1 }} of {{ $count }}" @endif data-index="{{ $i }}">
                        <img src="{{ $image['src'] }}" {{ \Themes\Xelga\Rendering\Picture::sources($image, '(min-width: 1320px) 720px, (min-width: 750px) 55vw, 100vw') }} alt="{{ $image['alt'] }}" @if ($settings['zoom'] === 'hover') data-zoom-src="{{ $image['src'] }}" @endif {{ \Themes\Xelga\Rendering\Fold::attrs($context, $i === 0) }}>
                        @if ($i === 0) @php \Themes\Xelga\Rendering\Fold::close(); @endphp @endif
                    </figure>
                @endforeach
            </div>
            @if ($count > 1)
                <button type="button" class="icon-btn gallery__nav gallery__nav--prev" data-gallery-prev aria-label="Previous photo"><svg class="icon" aria-hidden="true"><use href="#i-chevron-left"></use></svg></button>
                <button type="button" class="icon-btn gallery__nav gallery__nav--next" data-gallery-next aria-label="Next photo"><svg class="icon" aria-hidden="true"><use href="#i-chevron-right"></use></svg></button>
            @endif
            @if (in_array($settings['zoom'], ['lightbox', 'hover'], true))
                <button type="button" class="icon-btn gallery__zoom" data-gallery-zoom aria-haspopup="dialog" aria-label="View photo full screen"><svg class="icon" aria-hidden="true"><use href="#i-expand"></use></svg></button>
            @endif
            @if ($count > 1)
                <p class="gallery__count" aria-hidden="true"><span data-gallery-now>1</span> / {{ $count }}</p>
            @endif
        </div>
        @if ($count > 1)
            <ul class="gallery__thumbs" aria-label="Choose a photo">
                @foreach ($images as $i => $image)
                    <li><button type="button" class="gallery__thumb" data-gallery-go="{{ $i }}" aria-label="Show photo {{ $i + 1 }} of {{ $count }}" @if ($i === 0) aria-current="true" @endif><img src="{{ $image['src'] }}" {{ \Themes\Xelga\Rendering\Picture::sources($image, '96px') }} alt="" @if ($i >= 5) loading="lazy" @endif decoding="async"></button></li>
                @endforeach
            </ul>
            <p class="visually-hidden" aria-live="polite" data-gallery-live></p>
        @endif
    @endif
</div>
