{{-- Featured products: a list from core's product feed as a grid, a swipe row or a compact list. --}}
@php
    $cards = $data['cards'];
    $cardSizes = \Themes\Xelga\Rendering\Picture::cardSizes($settings['mobile_cols'] ?? 2, $data['cols'], $context->span);
@endphp
@if ($cards !== [] || $context->preview)
    <section class="s {{ $section['class'] }} {{ $settings['scheme'] }}" @include('xelga::snippets.section-attrs')
             data-cols="{{ $data['cols'] }}" data-rows="{{ $settings['rows'] }}" data-mobile-cols="{{ $settings['mobile_cols'] }}"
             data-show-rating="{{ $settings['show_rating'] ? 'true' : 'false' }}" data-show-quick-add="{{ $settings['show_quick_add'] ? 'true' : 'false' }}"
             data-show-brand="{{ $settings['show_brand'] ? 'true' : 'false' }}" data-head-align="{{ $settings['head_align'] }}"
             data-autoplay="{{ $settings['autoplay'] ? 'true' : 'false' }}" data-interval="{{ $settings['interval'] }}"
             aria-labelledby="{{ $section['id'] }}-title">
        @include('xelga::snippets.section-bg')
        <div class="s__inner">
            @include('xelga::snippets.section-head', ['link' => $data['link'], 'rail' => true, 'railPause' => (bool) $settings['autoplay']])
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
