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
             data-gallery="{{ $settings['gallery'] }}" data-picker-color="{{ $settings['picker_color'] }}" data-picker-size="{{ $settings['picker_size'] }}"
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
                    @includeIf('xelga::blocks.product-main.' . $block['type'], ['block' => $block])
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

        @if ($settings['zoom'] === 'lightbox' && count($data['images']) > 0)
            <dialog class="lightbox scheme-3" data-lightbox aria-label="Photos of {{ $product->name }}">
                <div class="lightbox__bar">
                    <p class="lightbox__count" aria-live="polite"><span data-lightbox-now>1</span> of {{ count($data['images']) }}</p>
                    <button type="button" class="icon-btn" data-lightbox-close aria-label="Close full screen photos"><svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg></button>
                </div>
                <div class="lightbox__stage">
                    @if (count($data['images']) > 1)
                        <button type="button" class="icon-btn icon-btn--outline lightbox__nav" data-lightbox-prev aria-label="Previous photo"><svg class="icon" aria-hidden="true"><use href="#i-chevron-left"></use></svg></button>
                    @endif
                    <img class="lightbox__img" data-lightbox-img src="{{ $data['images'][0]['src'] }}" alt="{{ $data['images'][0]['alt'] }}">
                    @if (count($data['images']) > 1)
                        <button type="button" class="icon-btn icon-btn--outline lightbox__nav" data-lightbox-next aria-label="Next photo"><svg class="icon" aria-hidden="true"><use href="#i-chevron-right"></use></svg></button>
                    @endif
                </div>
            </dialog>
        @endif
    </section>
@endif
