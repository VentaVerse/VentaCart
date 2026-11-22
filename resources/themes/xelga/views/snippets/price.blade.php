{{-- A price as core's Pricing worked it out for this shopper: a price below the regular price shows the regular price struck through. --}}
<p class="price{{ $card['below_regular'] ? ' price--sale' : '' }}">
    @if ($card['below_regular'])
        <span class="visually-hidden">{{ $card['on_sale'] ? 'Sale price' : 'Your price' }}</span>
        <span class="price__now">{{ $card['price'] }}</span>
        <span class="visually-hidden">Regular price</span>
        <s class="price__was">{{ $card['regular_price'] }}</s>
    @else
        <span class="price__now">{{ $card['price'] }}</span>
    @endif
</p>
