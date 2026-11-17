{{--
    Quantity and Add to cart. The button posts to core's cart.add with the
    chosen variant; core checks stock and the minimum. A sold-out product
    offers the stock alerts extension's back in stock email when it is on.
--}}
@php
    $s = $block['settings'];
    $qtyId = $section['id'] . '-qty';
    $notifyId = $section['id'] . '-notify';
@endphp
<div class="buy" data-block-id="{{ $block['id'] }}" data-block-type="quantity-and-buy" @if ($block['disabled']) hidden @endif>
    <div class="buy__row">
        <div class="qty">
            <button type="button" class="qty__btn" data-qty-step="-1" aria-label="Decrease quantity"><svg class="icon icon--sm" aria-hidden="true"><use href="#i-minus"></use></svg></button>
            <label class="visually-hidden" for="{{ $qtyId }}">Quantity</label>
            <input class="qty__input" id="{{ $qtyId }}" type="number" inputmode="numeric" min="{{ $data['min_qty'] }}" @if ($data['max_qty']) max="{{ $data['max_qty'] }}" @endif value="{{ $data['min_qty'] }}" data-product-qty>
            <button type="button" class="qty__btn" data-qty-step="1" aria-label="Increase quantity"><svg class="icon icon--sm" aria-hidden="true"><use href="#i-plus"></use></svg></button>
        </div>
        <button type="button" class="btn btn--lg buy__add" data-buy-main data-product-id="{{ $product->id }}" data-product-name="{{ $product->name }}" @disabled($data['sold_out'] || $data['unavailable'])>{{ $data['sold_out'] ? 'Sold out' : 'Add to cart' }}</button>
        @if ($data['can_wish'] && ($s['show_wishlist'] ?? true))
            <button type="button" class="icon-btn icon-btn--outline buy__wish" data-wishlist data-product-id="{{ $product->id }}" aria-pressed="{{ $data['wishlisted'] ? 'true' : 'false' }}" aria-label="Save {{ $product->name }} to your wishlist"><svg class="icon" aria-hidden="true"><use href="#i-heart"></use></svg></button>
        @endif
    </div>
    <p class="buy__msg" data-buy-msg role="status"></p>
    @if ($data['min_qty'] > 1)
        <p class="buy__min">Sold in quantities of {{ $data['min_qty'] }} or more.</p>
    @endif
    @if ($data['can_notify'] && ($s['show_notify'] ?? true))
        <div class="notify" data-notify @if (! $data['sold_out']) hidden @endif>
            <p class="notify__text" data-notify-text>This is sold out. Leave your name and email and the store will write when it is back.</p>
            <form class="notify__form" data-notify-form action="{{ route('notify-stock.store') }}" method="post" novalidate>
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <label class="visually-hidden" for="{{ $notifyId }}-name">Your name</label>
                <input class="input" id="{{ $notifyId }}-name" type="text" name="name" autocomplete="name" placeholder="Your name" value="{{ $data['customer']['name'] }}" maxlength="255" required>
                <label class="visually-hidden" for="{{ $notifyId }}-email">Email address</label>
                <input class="input" id="{{ $notifyId }}-email" type="email" name="email" autocomplete="email" placeholder="you@example.com" value="{{ $data['customer']['email'] }}" maxlength="255" required aria-describedby="{{ $notifyId }}-msg">
                <x-turnstile id="{{ $notifyId }}-check" />
                <button class="btn btn--secondary" type="submit">Email me</button>
                <p class="notify__msg" id="{{ $notifyId }}-msg" data-notify-msg role="status"></p>
            </form>
        </div>
    @endif
</div>
