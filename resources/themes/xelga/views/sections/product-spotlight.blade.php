{{--
    Product spotlight: one product with its price, card details and a
    countdown to the end of its special price, as core set it. A product the
    shopper cannot see leaves the section out.
--}}
@php
    $card = $data['card'];
    $picture = $data['picture'];
@endphp
@if ($card || $context->preview)
    <section class="s {{ $section['class'] }} {{ $settings['scheme'] }}" @include('xelga::snippets.section-attrs')
             data-image-pos="{{ $settings['image_pos'] }}" data-show-countdown="{{ $settings['show_countdown'] ? 'true' : 'false' }}"
             data-show-specs="{{ $settings['show_specs'] ? 'true' : 'false' }}"
             data-focal="{{ $settings['focal'] }}" data-focal-y="{{ $settings['focal_y'] }}"
             aria-labelledby="{{ $section['id'] }}-title">
        @include('xelga::snippets.section-bg')
        @if (! $card)
            <div class="s__inner">
                <h2 class="s-head__title" id="{{ $section['id'] }}-title" data-setting="heading">{{ $settings['heading'] }}</h2>
                @include('xelga::snippets.editor-note', ['note' => $data['empty']])
            </div>
        @else
            <div class="s__inner spotlight">
                <div class="spotlight__media">
                    @if ($picture)
                        <img src="{{ $picture['src'] }}" alt="" {{ \Themes\Xelga\Rendering\Fold::attrs($context) }} {{ \Themes\Xelga\Rendering\Picture::sources($picture, \Themes\Xelga\Rendering\Picture::columnSizes($context->span, '(min-width: 750px) 50vw, 100vw', $context->inSide)) }}
                             @if ($picture['width']) width="{{ $picture['width'] }}" height="{{ $picture['height'] }}" @endif>
                    @endif
                    @if ($card['on_sale'] && $card['saving'] > 0)
                        <span class="badge badge--sale">Save {{ $card['saving'] }}%</span>
                    @endif
                </div>
                <div class="spotlight__body">
                    <h2 class="spotlight__heading" id="{{ $section['id'] }}-title" data-setting="heading" data-optional @if ($settings['heading'] === '') hidden @endif>{{ $settings['heading'] }}</h2>
                    <p class="spotlight__text" data-setting="text" data-optional @if ($settings['text'] === '') hidden @endif>{{ $settings['text'] }}</p>
                    <h3 class="spotlight__product"><a href="{{ $card['url'] }}">{{ $card['name'] }}</a></h3>
                    @if ($card['rating'] !== null)
                        <p class="spotlight__rating">@include('xelga::snippets.stars', ['rating' => $card['rating'], 'count' => $card['reviews']])</p>
                    @endif
                    @include('xelga::snippets.price', ['card' => $card])
                    @if ($data['specs'] !== [])
                        <dl class="spec-list">
                            @foreach ($data['specs'] as $spec)
                                <dt>{{ $spec['name'] }}</dt><dd>{{ $spec['value'] }}</dd>
                            @endforeach
                        </dl>
                    @endif
                    @if ($data['ends_at'])
                        <div class="countdown" data-countdown-end="{{ $data['ends_at'] }}">
                            <p class="countdown__label">Sale price ends in</p>
                            <ol class="countdown__units" aria-hidden="true">
                                <li class="countdown__unit"><span class="countdown__num" data-unit="d">00</span><span class="countdown__unit-label">days</span></li>
                                <li class="countdown__unit"><span class="countdown__num" data-unit="h">00</span><span class="countdown__unit-label">hours</span></li>
                                <li class="countdown__unit"><span class="countdown__num" data-unit="m">00</span><span class="countdown__unit-label">min</span></li>
                                <li class="countdown__unit"><span class="countdown__num" data-unit="s">00</span><span class="countdown__unit-label">sec</span></li>
                            </ol>
                            <p class="visually-hidden countdown__summary"></p>
                            <p class="countdown__ended">The sale price has ended. Reload the page to see the current price.</p>
                        </div>
                    @endif
                    <div class="spotlight__buy">
                        @if ($card['sold_out'])
                            <button type="button" class="btn" disabled>Sold out</button>
                        @elseif ($card['has_variants'])
                            <a class="btn btn--accent" href="{{ $card['url'] }}">Choose a variant</a>
                        @else
                            <div class="qty">
                                <button type="button" class="qty__btn" data-qty-step="-1" aria-label="One fewer"><svg class="icon icon--sm" aria-hidden="true"><use href="#i-minus"></use></svg></button>
                                <label class="visually-hidden" for="{{ $section['id'] }}-qty">Quantity</label>
                                <input class="qty__input" id="{{ $section['id'] }}-qty" type="number" inputmode="numeric" min="{{ $card['min_qty'] }}" max="{{ $card['max_qty'] }}" value="{{ $card['min_qty'] }}">
                                <button type="button" class="qty__btn" data-qty-step="1" aria-label="One more"><svg class="icon icon--sm" aria-hidden="true"><use href="#i-plus"></use></svg></button>
                            </div>
                            <button type="button" class="btn btn--accent" data-add-to-cart data-product-id="{{ $card['id'] }}" data-product-name="{{ $card['name'] }}" data-quantity-from="#{{ $section['id'] }}-qty"><span data-setting="button_label">{{ $settings['button_label'] !== '' ? $settings['button_label'] : 'Add to cart' }}</span></button>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </section>
@endif
