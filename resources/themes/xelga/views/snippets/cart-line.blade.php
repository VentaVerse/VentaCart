{{-- One cart line on the cart page, with the price core stored on it. --}}
@php $label = $line['variant'] !== '' ? $line['name'] . ', ' . $line['variant'] : $line['name']; @endphp
<li class="line{{ $line['unavailable'] || $line['out_of_stock'] ? ' is-flagged' : '' }}" data-line="{{ $line['id'] }}">
    @if ($line['url'])
        <a class="line__media" href="{{ $line['url'] }}" tabindex="-1" aria-hidden="true">
    @else
        <span class="line__media" aria-hidden="true">
    @endif
        @if ($line['image'])
            <img src="{{ $line['image'] }}" alt="" loading="lazy" decoding="async">
        @endif
    {!! $line['url'] ? '</a>' : '</span>' !!}
    <div class="line__body">
        @if ($line['url'])
            <a class="line__title" href="{{ $line['url'] }}">{{ $line['name'] }}</a>
        @else
            <p class="line__title">{{ $line['name'] }}</p>
        @endif
        @if ($line['variant'] !== '')
            <p class="line__variant">{{ $line['variant'] }}</p>
        @endif
        <p class="price"><span class="visually-hidden">Price each </span><span class="price__now">{{ $line['price'] }}</span></p>
        @if ($line['unavailable'])
            <p class="line__flag">No longer available. Remove it to check out.</p>
        @elseif ($line['out_of_stock'])
            <p class="line__flag">Not enough in stock{{ $line['max'] ? ': only ' . $line['max'] . ' left' : '' }}. Lower the quantity or remove it to check out.</p>
        @endif
    </div>
    <p class="line__total"><span class="visually-hidden">Line total </span><span data-line-total>{{ $line['total'] }}</span></p>
    <div class="line__actions">
        @if (! $line['unavailable'])
            <div class="qty qty--sm">
                <button type="button" class="qty__btn" data-line-step="-1" aria-label="One fewer {{ $label }}"><svg class="icon icon--sm" aria-hidden="true"><use href="#i-minus"></use></svg></button>
                <label class="visually-hidden" for="line-{{ $line['id'] }}-qty">Quantity of {{ $label }}</label>
                <input class="qty__input" id="line-{{ $line['id'] }}-qty" type="number" inputmode="numeric" min="1" @if ($line['max']) max="{{ $line['max'] }}" @endif value="{{ $line['quantity'] }}" data-line-qty>
                <button type="button" class="qty__btn" data-line-step="1" aria-label="One more {{ $label }}"><svg class="icon icon--sm" aria-hidden="true"><use href="#i-plus"></use></svg></button>
            </div>
        @endif
        <button type="button" class="line__remove" data-line-remove aria-label="Remove {{ $label }} from your cart">Remove</button>
    </div>
</li>
