{{-- Buttons back into the store: the home page, a page the merchant picks, and Contact us. --}}
@php
    $s = $block['settings'];
    $shopUrl = $data['shopLinks'][$block['id']] ?? null;
@endphp
<ul class="nf__links" data-block-id="{{ $block['id'] }}" data-block-type="links" @if ($block['disabled']) hidden @endif>
    <li><a class="btn" href="{{ $data['homeUrl'] }}">Back to the home page</a></li>
    @if ($shopUrl && $s['shop_label'] !== '')
        <li><a class="btn btn--secondary" href="{{ $shopUrl }}" data-setting-href="shop_link"><span data-setting="shop_label">{{ $s['shop_label'] }}</span></a></li>
    @endif
    @if ($s['show_contact'] && $data['contactUrl'])
        <li><a class="btn btn--secondary" href="{{ $data['contactUrl'] }}">Contact us</a></li>
    @endif
</ul>
