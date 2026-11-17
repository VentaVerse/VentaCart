{{--
    Product information: the photos in one column, the product's other
    blocks in the other, in the order the merchant set. Everything shown
    comes from core's product page bundle through ProductMain; the page
    data in the script tag lets the browser switch variants and quantity
    tiers without working out any price itself.
--}}
@php
    $product = $data['product'];
    $gallery = collect($blocks)->firstWhere('type', 'gallery');
    $info = array_values(array_filter($blocks, fn ($b) => $b['type'] !== 'gallery'));
@endphp
@if ($product)
    <section class="s {{ $section['class'] }} {{ $settings['scheme'] }}" id="{{ $section['id'] }}" @include('xelga::snippets.section-attrs')
             data-gallery="{{ $settings['gallery'] }}" data-photo-size="{{ $settings['photo_size'] }}" data-photo-shape="{{ $settings['photo_shape'] }}" data-picker-color="{{ $settings['picker_color'] }}" data-picker-size="{{ $settings['picker_size'] }}"
             data-zoom="{{ $settings['zoom'] }}" data-sticky-buy="{{ $settings['sticky_buy'] }}" data-has-gallery="{{ $gallery && ! $gallery['disabled'] && $data['images'] !== [] ? 'true' : 'false' }}"
             aria-label="{{ $product->name }}">
        <div class="s__inner pmain">
            @if ($gallery)
                <div class="pmain__media">
                    @include('xelga::blocks.product-main.gallery', ['block' => $gallery])
                </div>
            @endif
            <div class="pmain__info" data-product-form>
                @foreach ($info as $block)
                    @if (isset($block['source']))
                        {{ $block['html'] }}
                    @else
                        @includeIf('xelga::blocks.product-main.' . $block['type'], ['block' => $block])
                    @endif
                @endforeach
            </div>
        </div>
        <script type="application/json" data-product-data>@json($data['json'])</script>

        @if ($settings['sticky_buy'] !== 'off' && ! $data['unavailable'])
            <div class="buybar {{ $settings['scheme'] }}" data-buybar inert>
                @if ($data['images'] !== [])
                    <img class="buybar__img" src="{{ $data['images'][0]['src'] }}" alt="" data-buybar-img>
                @endif
                <div class="buybar__text">
                    <p class="buybar__name">{{ $product->name }}</p>
                    <p class="buybar__meta"><span data-buybar-variant></span><span data-buybar-price>{{ $data['price']['now'] }}</span></p>
                </div>
                <button type="button" class="btn buybar__add" data-buybar-add @disabled($data['sold_out'])>{{ $data['sold_out'] ? 'Sold out' : 'Add to cart' }}</button>
            </div>
        @endif

        @if (in_array($settings['zoom'], ['lightbox', 'hover'], true) && count($data['images']) > 0)
            @php $photoCount = count($data['images']); @endphp
            {{-- Full screen photos: one photo per screen width, moved by swipe, scroll, drag, arrows or keys. --}}
            <dialog class="lightbox" data-lightbox aria-label="Photos of {{ $product->name }}">
                <div class="lightbox__track" data-lightbox-track>
                    @foreach ($data['images'] as $i => $image)
                        <figure class="lightbox__slide" data-lightbox-slide="{{ $i }}" @if ($photoCount > 1) role="group" aria-roledescription="slide" aria-label="{{ $i + 1 }} of {{ $photoCount }}" @endif>
                            <img class="lightbox__img" src="{{ $image['src'] }}" alt="{{ $image['alt'] }}" loading="lazy" decoding="async" draggable="false">
                        </figure>
                    @endforeach
                </div>
                <button type="button" class="icon-btn lightbox__close" data-lightbox-close aria-label="Close photos" autofocus><svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg></button>
                @if ($photoCount > 1)
                    <button type="button" class="icon-btn lightbox__nav lightbox__nav--prev" data-lightbox-prev aria-label="Previous photo"><svg class="icon" aria-hidden="true"><use href="#i-chevron-left"></use></svg></button>
                    <button type="button" class="icon-btn lightbox__nav lightbox__nav--next" data-lightbox-next aria-label="Next photo"><svg class="icon" aria-hidden="true"><use href="#i-chevron-right"></use></svg></button>
                    <p class="lightbox__count" aria-hidden="true"><span data-lightbox-now>1</span> / {{ $photoCount }}</p>
                    <p class="visually-hidden" aria-live="polite" data-lightbox-live></p>
                @endif
            </dialog>
        @endif
    </section>
@endif
