{{--
    A heading and a row of shared product cards, as a grid or a swipe row:
    the related products and recently viewed sections draw the same way as
    the home page's product rows.
--}}
@php
    $cards = $data['cards'];
    $cardSizes = \Themes\Xelga\Rendering\Picture::cardSizes($settings['mobile_cols'] ?? 2, $settings['cols'] ?? 4);
@endphp
@if ($cards !== [] || $context->preview)
    <section class="s {{ $section['class'] }} {{ $settings['scheme'] }}" @include('xelga::snippets.section-attrs')
             data-cols="{{ $settings['cols'] }}" data-mobile-cols="{{ $settings['mobile_cols'] }}"
             data-show-rating="{{ $settings['show_rating'] ? 'true' : 'false' }}" data-show-quick-add="{{ $settings['show_quick_add'] ? 'true' : 'false' }}"
             data-show-brand="{{ $settings['show_brand'] ? 'true' : 'false' }}" data-head-align="{{ $settings['head_align'] }}"
             aria-labelledby="{{ $section['id'] }}-title">
        <div class="s__inner">
            @include('xelga::snippets.section-head', ['link' => null, 'rail' => $settings['variant'] === 'carousel'])
            @if ($cards === [])
                @include('xelga::snippets.editor-note', ['note' => $data['empty']])
            @else
                <ul class="product-grid rail-track" aria-label="{{ $settings['heading'] !== '' ? $settings['heading'] : $section['label'] }}">
                    @foreach ($cards as $card)
                        <li>@include('xelga::snippets.product-card', ['card' => $card, 'idPrefix' => $section['id'], 'sizes' => $cardSizes])</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
@endif
