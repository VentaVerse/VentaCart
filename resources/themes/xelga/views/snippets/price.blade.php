{{-- A price as core's Pricing worked it out for this shopper: a sale shows the regular price struck through. --}}
<p class="price{{ $card['on_sale'] ? ' price--sale' : '' }}">
    @if ($card['on_sale'])
        <span class="visually-hidden">Sale price</span>
        <span class="price__now">{{ $card['price'] }}</span>
        <span class="visually-hidden">Regular price</span>
        <s class="price__was">{{ $card['regular_price'] }}</s>
    @else
        <span class="price__now">{{ $card['price'] }}</span>
    @endif
</p>
