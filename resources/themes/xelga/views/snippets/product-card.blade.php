{{--
    The shared product card, from a card that ProductCards prepared: photo
    (and a second one on hover), badges, wishlist heart, brand, name, card
    details, color dots, rating, price and quick add. Theme settings decide
    which of these show, through attributes on <html>, so every card in the
    store follows them. A product with variants opens its page instead of
    adding to the cart, so the shopper chooses one.
--}}
@php
    $cardId = ($idPrefix ?? 'card') . '-' . $card['id'];
    $level = $headingLevel ?? 'h3';
    $canWish = \Illuminate\Support\Facades\Route::has('wishlist.add');
@endphp
<article class="card{{ $card['sold_out'] ? ' is-soldout' : '' }}" aria-labelledby="{{ $cardId }}">
    <div class="card__media">
        @if ($card['image'])
            <img class="card__img" src="{{ $card['image']['src'] }}" alt="" loading="lazy" decoding="async">
            @if ($card['alt_image'])
                <img class="card__img card__img--alt" src="{{ $card['alt_image']['src'] }}" alt="" loading="lazy" decoding="async">
            @endif
        @else
            <span class="card__placeholder" aria-hidden="true"><svg class="icon" aria-hidden="true"><use href="#i-bag"></use></svg></span>
        @endif
        @if ($card['on_sale'] || $card['is_new'] || $card['sold_out'])
            <ul class="card__badges">
                @if ($card['sold_out'])
                    <li class="badge badge--soldout">Sold out</li>
                @elseif ($card['on_sale'])
                    <li class="badge badge--sale"><span class="badge__word">Sale</span><span class="badge__pct">{{ $card['saving'] > 0 ? '-' . $card['saving'] . '%' : 'Sale' }}</span></li>
                @endif
                @if ($card['is_new'] && ! $card['sold_out'])
                    <li class="badge badge--new">New</li>
                @endif
            </ul>
        @endif
        @if ($canWish)
            <button type="button" class="icon-btn card__wish" data-wishlist data-product-id="{{ $card['id'] }}"
                    aria-pressed="{{ $card['wishlisted'] ? 'true' : 'false' }}" aria-label="Save {{ $card['name'] }} to your wishlist">
                <svg class="icon" aria-hidden="true"><use href="#i-heart"></use></svg>
            </button>
        @endif
    </div>
    <div class="card__body">
        @if ($card['brand'])
            <p class="card__brand">{{ $card['brand'] }}</p>
        @endif
        <{{ $level }} class="card__title" id="{{ $cardId }}"><a href="{{ $card['url'] }}">{{ $card['name'] }}</a></{{ $level }}>
        @if ($card['spec'] !== '')
            <p class="card__spec">{{ $card['spec'] }}</p>
        @endif
        @if (! empty($card['swatches']['items']))
            <ul class="card__swatches" aria-label="{{ $card['swatches']['group'] }}: {{ implode(', ', array_column($card['swatches']['items'], 'label')) }}">
                @foreach ($card['swatches']['items'] as $swatch)
                    <li class="swatch" data-swatch="{{ $swatch['key'] }}" title="{{ $swatch['label'] }}"></li>
                @endforeach
                @if ($card['swatches']['more'] > 0)
                    <li class="card__swatches-more" aria-hidden="true">+{{ $card['swatches']['more'] }}</li>
                @endif
            </ul>
        @endif
        @if ($card['rating'] !== null)
            <p class="card__rating">@include('xelga::snippets.stars', ['rating' => $card['rating'], 'count' => $card['reviews']])</p>
        @endif
        @include('xelga::snippets.price', ['card' => $card])
    </div>
    @if ($card['sold_out'])
        <button type="button" class="btn btn--secondary btn--sm card__add" disabled><span class="card__add-label">Sold out</span></button>
    @elseif ($card['has_variants'])
        <a class="btn btn--secondary btn--sm card__add" href="{{ $card['url'] }}" aria-label="Choose a variant of {{ $card['name'] }}"><svg class="icon card__add-icon" aria-hidden="true"><use href="#i-arrow-right"></use></svg><span class="card__add-label">Choose a variant</span></a>
    @else
        <button type="button" class="btn btn--secondary btn--sm card__add" data-add-to-cart data-product-id="{{ $card['id'] }}" data-quantity="{{ $card['min_qty'] }}" data-product-name="{{ $card['name'] }}" aria-label="Add {{ $card['name'] }} to cart"><svg class="icon card__add-icon" aria-hidden="true"><use href="#i-plus"></use></svg><span class="card__add-label">Add to cart</span><span class="card__add-label--done"><svg class="icon icon--sm" aria-hidden="true"><use href="#i-check"></use></svg><span>Added</span></span></button>
    @endif
</article>
