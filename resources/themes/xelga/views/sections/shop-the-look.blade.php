{{--
    Shop the look: a photo with a numbered dot on each product in it, and
    the products listed beside or below. A dot whose product the shopper
    cannot see is left out.
--}}
@php
    $picture = $data['picture'];
    $spots = [];
    foreach ($blocks as $block) {
        $card = $data['spots'][$block['id']] ?? null;
        if ($card || $context->preview) {
            $spots[] = ['block' => $block, 'card' => $card, 'n' => count($spots) + 1];
        }
    }
    $shown = array_values(array_filter($spots, fn ($s) => $s['card'] !== null));
@endphp
@if ($shown !== [] || $context->preview)
    <section class="s {{ $section['class'] }} {{ $settings['scheme'] }}" @include('xelga::snippets.section-attrs')
             data-image-pos="{{ $settings['image_pos'] }}" data-ratio="{{ $settings['ratio'] }}"
             data-show-numbers="{{ $settings['show_numbers'] ? 'true' : 'false' }}" aria-labelledby="{{ $section['id'] }}-title">
        @include('xelga::snippets.section-bg')
        <div class="s__inner look">
            <div class="look__media">
                <img src="{{ $picture['src'] ?? '' }}" alt="{{ $settings['image_alt'] }}" {{ \Themes\Xelga\Rendering\Fold::attrs($context, (bool) $picture) }} {{ \Themes\Xelga\Rendering\Picture::sources($picture, '(min-width: 750px) 50vw, 100vw') }}
                     data-setting="image" data-setting-attr="image_alt:alt"
                     @if ($picture && $picture['width']) width="{{ $picture['width'] }}" height="{{ $picture['height'] }}" @endif
                     @if (! $picture) hidden @endif>
                @foreach ($spots as $spot)
                    @php
                        $b = $spot['block'];
                        $card = $spot['card'];
                        $popId = $section['id'] . '-pop-' . $spot['n'];
                    @endphp
                    <div class="hotspot" data-block-id="{{ $b['id'] }}" data-block-type="hotspot" data-x="{{ $b['settings']['x'] }}" data-y="{{ $b['settings']['y'] }}"
                         @if ($b['disabled']) hidden @endif>
                        <button type="button" class="hotspot__btn" aria-expanded="false" aria-controls="{{ $popId }}"
                                aria-label="Piece {{ $spot['n'] }}: {{ $card['name'] ?? 'no product chosen' }}">
                            <span class="hotspot__dot"><span class="hotspot__num">{{ $spot['n'] }}</span><svg class="icon" aria-hidden="true"><use href="#i-plus"></use></svg></span>
                        </button>
                        <div class="hotspot__pop" id="{{ $popId }}" hidden>
                            @if ($card)
                                @if ($card['image'])
                                    <img src="{{ $card['image']['src'] }}" alt="" width="64" height="64" loading="lazy" decoding="async" {{ \Themes\Xelga\Rendering\Picture::sources($card['image'], '64px') }}>
                                @endif
                                <div>
                                    <p class="hotspot__pop-title"><a href="{{ $card['url'] }}">{{ $card['name'] }}</a></p>
                                    @include('xelga::snippets.price', ['card' => $card])
                                </div>
                            @else
                                <p>Pick the product for this dot.</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="look__body">
                <div class="stack">
                    <h2 class="look__title" id="{{ $section['id'] }}-title" data-setting="heading" data-optional @if ($settings['heading'] === '') hidden @endif>{{ $settings['heading'] }}</h2>
                    <p class="look__text" data-setting="text" data-optional @if ($settings['text'] === '') hidden @endif>{{ $settings['text'] }}</p>
                </div>
                @if ($shown === [])
                    @include('xelga::snippets.editor-note', ['note' => 'Pick a product for each dot. Shoppers see this section once a dot has a product they can buy.'])
                @endif
                <ol class="look__list">
                    @foreach ($shown as $spot)
                        @php $card = $spot['card']; @endphp
                        <li class="mini">
                            <span class="mini__num" aria-hidden="true">{{ $spot['n'] }}</span>
                            @if ($card['image'])
                                <img src="{{ $card['image']['src'] }}" alt="" width="64" height="64" loading="lazy" decoding="async" {{ \Themes\Xelga\Rendering\Picture::sources($card['image'], '64px') }}>
                            @else
                                <span class="mini__ph" aria-hidden="true"></span>
                            @endif
                            <div>
                                <p class="mini__title"><a href="{{ $card['url'] }}">{{ $card['name'] }}</a></p>
                                @include('xelga::snippets.price', ['card' => $card])
                            </div>
                            @if ($card['sold_out'])
                                <span class="mini__note">Sold out</span>
                            @elseif ($card['has_variants'])
                                <a class="icon-btn icon-btn--outline" href="{{ $card['url'] }}" aria-label="Choose a variant of {{ $card['name'] }}"><svg class="icon" aria-hidden="true"><use href="#i-arrow-right"></use></svg></a>
                            @else
                                <button type="button" class="icon-btn icon-btn--outline" data-add-to-cart data-product-id="{{ $card['id'] }}" data-quantity="{{ $card['min_qty'] }}" data-product-name="{{ $card['name'] }}" aria-label="Add {{ $card['name'] }} to cart"><svg class="icon" aria-hidden="true"><use href="#i-plus"></use></svg></button>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
    </section>
@endif
