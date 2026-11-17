{{--
    The page of products core chose, drawn with the shared product card.
    Beside sides (from 990px) the content area is narrower, so the photos
    are sized for the $cols cards a line holds there; below 990px the
    sides stack and the section's own setting applies.
--}}
@php
    $span = $context->span;
    $sizes = $span < 12 ? \Themes\Xelga\Rendering\Picture::contentSizes($span, \Themes\Xelga\Rendering\Picture::cardSizes($settings['mobile_cols'] ?? 2, $settings['cols'] ?? 4), 1 / max(1, (int) ($cols ?? 4))) : null;
@endphp
<ul class="product-grid" aria-label="{{ $label }}" data-grid>
    @foreach ($data['list']['cards'] as $card)
        <li>@include('xelga::snippets.product-card', ['card' => $card, 'idPrefix' => $section['id'], 'sizes' => $sizes])</li>
    @endforeach
</ul>
