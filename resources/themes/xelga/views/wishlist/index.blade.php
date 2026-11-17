{{--
    The wishlist: the products the shopper saved, from core's wishlist
    service, drawn with the store's product card (prices from core's
    Pricing for this shopper). Signed-in customers see it inside their
    account; a guest's list lives in their browser session.
--}}
@php
    $signedIn = auth('customer')->check();
    app(\Themes\Xelga\Rendering\Storefront::class)->page($signedIn ? 'account' : 'page');
    $cards = \Themes\Xelga\Sections\ProductCards::for($products, \Themes\Xelga\Sections\ThemeValues::get('show_swatches') === true);
@endphp
<x-store-layout title="Wishlist">
    @include('xelga::partials.fixed.page-assets')
    <div class="s fixed-page scheme-1" data-fixed-page="wishlist">
        <div class="s__inner">
            @include('xelga::partials.fixed.crumbs', ['trail' => $signedIn ? [['label' => 'Your account', 'url' => route('account.dashboard')], ['label' => 'Wishlist']] : [['label' => 'Wishlist']]])
            <div class="{{ $signedIn ? 'acct' : 'wish' }}">
                @if ($signedIn)
                    @include('xelga::partials.fixed.account-nav')
                @endif
                <div class="acct__main">
                    <header class="acct__head">
                        <h1 class="acct__title">Wishlist</h1>
                        @if ($cards !== [])
                            <p class="muted">{{ count($cards) === 1 ? '1 product saved' : count($cards) . ' products saved' }}</p>
                        @endif
                    </header>
                    @if ($cards === [])
                        <div class="empty-note">
                            <p class="empty-note__title">Nothing saved yet</p>
                            <p class="muted">Tap the heart on a product to keep it here.</p>
                            <p><a class="btn" href="{{ route('home') }}">Continue shopping</a></p>
                        </div>
                    @else
                        <ul class="product-grid wish__grid" aria-label="Saved products">
                            @foreach ($cards as $card)
                                <li>@include('xelga::snippets.product-card', ['card' => $card, 'idPrefix' => 'wishlist', 'headingLevel' => 'h2'])</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-store-layout>
