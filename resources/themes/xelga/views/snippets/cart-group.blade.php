{{--
    One group of cart lines (App\Support\CartGroups): a header with the
    group's name, linked when it has an address, and its shipping note,
    then its lines. A group without a name (the lines no group took) has
    no header.
--}}
<section class="cart-group" data-cart-group="{{ $group['key'] }}" @if ($group['label'] !== '') aria-label="{{ $group['label'] }}" @endif>
    @if ($group['label'] !== '')
        <header class="cart-group__head">
            <h{{ $headingLevel }} class="cart-group__title">
                @if ($group['url'] !== null)<a href="{{ $group['url'] }}">{{ $group['label'] }}</a>@else{{ $group['label'] }}@endif
            </h{{ $headingLevel }}>
            @if ($group['shipping'] !== null)
                <p class="cart-group__ship">
                    @if ($group['shipping']['label'] !== '')<span>{{ $group['shipping']['label'] }}</span>@endif
                    @if ($group['shipping']['amount'] !== null) <strong>{{ $group['shipping']['amount'] }}</strong>@endif
                    @if ($group['shipping']['note'] !== null) <span class="muted">{{ $group['shipping']['note'] }}</span>@endif
                </p>
            @endif
        </header>
    @endif
    <ul class="{{ $listClass }}" aria-label="{{ $group['label'] !== '' ? 'Items from ' . $group['label'] : 'Other items in your cart' }}">
        @foreach ($group['lines'] as $line)
            @include('xelga::snippets.cart-line', ['line' => $line])
        @endforeach
    </ul>
</section>
