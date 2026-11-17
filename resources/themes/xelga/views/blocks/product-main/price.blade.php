{{-- The price core's Pricing gives this shopper for one unit; a sale shows the regular price struck through. --}}
@php $price = $data['price']; $s = $block['settings']; @endphp
<div class="pinfo__price" data-block-id="{{ $block['id'] }}" data-block-type="price" @if ($block['disabled']) hidden @endif>
    <p class="price price--lg{{ $price['sale'] ? ' price--sale' : '' }}" data-price>
        <span class="visually-hidden" data-price-label-now>{{ $price['sale'] ? 'Sale price' : 'Price' }}</span>
        <span class="price__now" data-price-now>{{ $price['now'] }}</span>
        <span class="visually-hidden" data-price-label-was @if (! $price['sale']) hidden @endif>Regular price</span>
        <s class="price__was" data-price-was @if (! $price['sale']) hidden @endif>{{ $price['was'] }}</s>
        @if ($s['show_badge'] ?? true)
            <span class="badge badge--sale" data-price-badge @if (! $price['sale'] || $price['saving'] <= 0) hidden @endif>-{{ $price['saving'] }}%</span>
        @endif
    </p>
    <p class="pinfo__each" data-price-each hidden></p>
    @if (($s['show_countdown'] ?? true) && $price['ends_at'])
        <p class="pinfo__countdown" data-sale-end="{{ $price['ends_at'] }}">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#i-clock"></use></svg>
            <span data-sale-running>Sale price ends in <span data-sale-left>{{ \Carbon\Carbon::parse($price['ends_at'])->diffForHumans(null, \Carbon\CarbonInterface::DIFF_ABSOLUTE, false, 2) }}</span></span>
            <span data-sale-ended hidden>The sale price has ended. Reload the page to see the current price.</span>
        </p>
    @endif
</div>
