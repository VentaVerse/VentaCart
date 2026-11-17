{{-- A photo with a link in one menu item's wide panel. --}}
@php $block = $promo['block']; @endphp
@if ($promo['url'] || $context->preview)
    <a class="mega__promo" href="{{ $promo['url'] ?? '#' }}" data-block-id="{{ $block['id'] }}" data-block-type="mega-promo" data-setting-href="link"
       @if ($block['disabled']) hidden @endif>
        @if ($promo['picture'])
            <img data-setting="image" src="{{ $promo['picture']['src'] }}" alt=""
                 @if ($promo['picture']['width']) width="{{ $promo['picture']['width'] }}" height="{{ $promo['picture']['height'] }}" @endif loading="lazy">
        @else
            <img data-setting="image" alt="" hidden>
        @endif
        <span class="mega__promo-title" data-setting="heading">{{ $block['settings']['heading'] }}</span>
    </a>
@endif
