{{--
    The account dashboard: core's recent orders and default address, plus
    the first products on the shopper's wishlist (core's wishlist service,
    priced by core's Pricing like every card in the store).
--}}
@php
    app(\Themes\Xelga\Rendering\Storefront::class)->page('account');
    $wishlistCards = \Illuminate\Support\Facades\Route::has('wishlist.index')
        ? \Themes\Xelga\Sections\ProductCards::for(app(\App\Services\WishlistService::class)->products()->take(3), lines: false)
        : [];
    $wishlistCount = \Illuminate\Support\Facades\Route::has('wishlist.index') ? app(\App\Services\WishlistService::class)->count() : 0;
@endphp
<x-store-layout title="Your account">
    @include('xelga::partials.fixed.page-assets')
    <div class="s fixed-page scheme-1" data-fixed-page="account">
        <div class="s__inner">
            @include('xelga::partials.fixed.crumbs', ['trail' => [['label' => 'Your account']]])
            <div class="acct">
                @include('xelga::partials.fixed.account-nav')
                <div class="acct__main">
                    <header class="acct__head">
                        <h1 class="acct__title">Hello, {{ $customer->first_name }}</h1>
                        <p class="muted">Your orders, addresses and saved products, all in one place.</p>
                    </header>
                    @include('xelga::partials.fixed.flash')
                    <section class="acct__block" aria-labelledby="orders-title">
                        <div class="acct__block-head"><h2 class="acct__subtitle" id="orders-title">Recent orders</h2><a class="link-arrow" href="{{ route('account.orders') }}">All orders<svg class="icon icon--sm" aria-hidden="true"><use href="#i-arrow-right"></use></svg></a></div>
                        @if ($recentOrders->isEmpty())
                            <div class="empty-note"><p class="empty-note__title">No orders yet</p><p class="muted">When you place an order, it shows here with its status and tracking.</p></div>
                        @else
                            @include('xelga::partials.fixed.orders-table', ['orders' => $recentOrders, 'cancellable' => []])
                        @endif
                    </section>
                    <div class="acct__pair">
                        <section class="acct__block" aria-labelledby="addr-title">
                            <div class="acct__block-head"><h2 class="acct__subtitle" id="addr-title">Default address</h2><a class="link-arrow" href="{{ route('account.addresses') }}">Manage<svg class="icon icon--sm" aria-hidden="true"><use href="#i-arrow-right"></use></svg></a></div>
                            @if ($defaultAddress)
                                @include('xelga::partials.fixed.address', ['a' => [
                                    'first_name' => $defaultAddress->first_name, 'last_name' => $defaultAddress->last_name,
                                    'address_1' => $defaultAddress->address_1, 'barangay' => $defaultAddress->barangay,
                                    'district' => $defaultAddress->district, 'city' => $defaultAddress->city,
                                    'state' => $defaultAddress->state, 'postcode' => $defaultAddress->postcode,
                                    'country' => $defaultAddress->country?->name, 'phone' => $defaultAddress->phone_display,
                                ]])
                            @else
                                <p class="muted">No address saved yet. <a href="{{ route('account.addresses.create') }}">Add one</a></p>
                            @endif
                        </section>
                        @if (\Illuminate\Support\Facades\Route::has('wishlist.index'))
                            <section class="acct__block" aria-labelledby="wish-title">
                                <div class="acct__block-head"><h2 class="acct__subtitle" id="wish-title">Wishlist</h2><a class="link-arrow" href="{{ route('wishlist.index') }}">{{ $wishlistCount === 1 ? '1 product' : $wishlistCount . ' products' }}<svg class="icon icon--sm" aria-hidden="true"><use href="#i-arrow-right"></use></svg></a></div>
                                @if ($wishlistCards === [])
                                    <p class="muted">Tap the heart on a product to keep it here.</p>
                                @else
                                    <ul class="mini-list">
                                        @foreach ($wishlistCards as $card)
                                            <li class="mini-item">
                                                @if ($card['image'])
                                                    <img src="{{ $card['image']['src'] }}" alt="" loading="lazy" width="48" height="60">
                                                @else
                                                    <span class="mini-item__ph" aria-hidden="true"></span>
                                                @endif
                                                <span><a href="{{ $card['url'] }}">{{ $card['name'] }}</a><span class="muted tabular">{{ $card['price'] }}</span></span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </section>
                        @endif
                    </div>
                    <section class="acct__block" aria-labelledby="profile-title">
                        <div class="acct__block-head"><h2 class="acct__subtitle" id="profile-title">Profile and password</h2></div>
                        <p class="muted">Change your name, email preferences or password. <a href="{{ route('account.profile') }}">Edit profile</a></p>
                    </section>
                </div>
            </div>
        </div>
    </div>
</x-store-layout>
