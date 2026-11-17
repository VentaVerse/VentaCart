{{-- Header: logo, the store's menu, search, account, wishlist and cart. --}}
@php
    $id = $section['id'];
    $flag = static fn ($v) => $v ? 'true' : 'false';
    $accountUrl = auth('customer')->check() ? route('account.dashboard') : route('customer.login');
    $searchUrl = route('search.results');
    $wishlistUrl = \Illuminate\Support\Facades\Route::has('wishlist.index') ? route('wishlist.index') : null;
    $count = (int) ($cartCount ?? 0);
@endphp
<header class="s {{ $section['class'] }} {{ $settings['scheme'] }}"
        data-section-id="{{ $id }}" data-section-type="header"
        data-variant="{{ $settings['variant'] }}"
        data-menu-style="{{ $settings['menu_style'] }}"
        data-sticky="{{ $flag($settings['sticky']) }}"
        data-show-search="{{ $flag($settings['show_search']) }}"
        data-show-account="{{ $flag($settings['show_account']) }}"
        data-show-wishlist="{{ $flag($settings['show_wishlist']) }}"
        data-logo-width="{{ $settings['logo_width'] }}"
        @if ($section['disabled']) hidden @endif>
    <div class="s__inner header__bar">
        <button type="button" class="icon-btn header__burger" data-drawer-open aria-controls="{{ $id }}-drawer" aria-expanded="false" aria-label="Open menu"><svg class="icon" aria-hidden="true"><use href="#i-menu"></use></svg></button>
        <a class="header__logo" href="{{ route('home') }}" aria-label="{{ $data['storeName'] }}, home">
            <img class="header__logo-img" data-setting="logo_image"
                 @if ($data['logo']) src="{{ $data['logo']['src'] }}" @if ($data['logo']['width']) width="{{ $data['logo']['width'] }}" height="{{ $data['logo']['height'] }}" @endif @else hidden @endif
                 alt="">
            <span class="wordmark" data-setting="logo_text">{{ $data['storeName'] }}</span>
        </a>
        <search class="header__search" id="{{ $id }}-search">
            <form class="search-field" action="{{ $searchUrl }}" method="get" role="search">
                <label class="visually-hidden" for="{{ $id }}-q">Search products</label>
                <input class="search-field__input" id="{{ $id }}-q" type="search" name="q" autocomplete="off" maxlength="120"
                       placeholder="{{ $settings['search_placeholder'] }}" data-setting-attr="search_placeholder:placeholder">
                <button class="search-field__submit" type="submit" aria-label="Search"><svg class="icon" aria-hidden="true"><use href="#i-search"></use></svg></button>
            </form>
        </search>
        <nav class="header__nav" aria-label="Main menu">
            <ul class="menu">
                @foreach ($data['items'] as $i => $item)
                    @if ($item['columns'] !== [])
                        @php $panelId = $id . '-panel-' . $i; @endphp
                        <li class="menu__item menu__item--mega">
                            <button type="button" class="menu__link" aria-expanded="false" aria-controls="{{ $panelId }}">{{ $item['label'] }} <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg></button>
                            <div class="mega" id="{{ $panelId }}" hidden>
                                <div class="mega__inner">
                                    @foreach ($item['columns'] as $column)
                                        <div class="mega__col">
                                            @if ($column['title'] !== null)
                                                <h3>@if ($column['url'])<a href="{{ $column['url'] }}">{{ $column['title'] }}</a>@else{{ $column['title'] }}@endif</h3>
                                            @elseif ($item['all'] && $item['url'])
                                                <h3><a href="{{ $item['url'] }}">{{ $item['all'] }}</a></h3>
                                            @endif
                                            <ul>
                                                @foreach ($column['links'] as $link)
                                                    <li>@if ($link['url'])<a href="{{ $link['url'] }}">{{ $link['label'] }}</a>@else<span>{{ $link['label'] }}</span>@endif</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endforeach
                                    @if ($item['promo'])
                                        @include('xelga::blocks.header.mega-promo', ['promo' => $item['promo']])
                                    @endif
                                </div>
                            </div>
                        </li>
                    @else
                        <li class="menu__item"><a class="menu__link" href="{{ $item['url'] ?? '#' }}" @if ($item['new_tab']) target="_blank" rel="noopener" @endif>{{ $item['label'] }}</a></li>
                    @endif
                @endforeach
            </ul>
        </nav>
        <div class="header__icons">
            <button type="button" class="icon-btn header__search-toggle" aria-expanded="false" aria-controls="{{ $id }}-search" aria-label="Search"><svg class="icon" aria-hidden="true"><use href="#i-search"></use></svg></button>
            <a class="icon-btn header__account" href="{{ $accountUrl }}" aria-label="Account"><svg class="icon" aria-hidden="true"><use href="#i-user"></use></svg></a>
            @if ($wishlistUrl)
                <a class="icon-btn header__wishlist" href="{{ $wishlistUrl }}" aria-label="Wishlist"><svg class="icon" aria-hidden="true"><use href="#i-heart"></use></svg></a>
            @endif
            <a class="icon-btn header__cart" href="{{ route('cart.index') }}" data-cart-open aria-haspopup="dialog" aria-controls="cart-drawer">
                <svg class="icon header__cart-icon header__cart-icon--bag" aria-hidden="true"><use href="#i-bag"></use></svg>
                <svg class="icon header__cart-icon header__cart-icon--cart" aria-hidden="true"><use href="#i-cart"></use></svg>
                <span class="visually-hidden">Cart,</span>
                <span class="cart-count" data-cart-count>{{ $count }}</span>
                <span class="visually-hidden">{{ $count === 1 ? 'item' : 'items' }}</span>
            </a>
        </div>
    </div>

    <dialog class="drawer {{ $settings['scheme'] }}" id="{{ $id }}-drawer" aria-label="Menu">
        <div class="drawer__head">
            <span class="wordmark">{{ $data['storeName'] }}</span>
            <button type="button" class="icon-btn" data-drawer-close aria-label="Close menu"><svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg></button>
        </div>
        <div class="drawer__body">
            <ul class="drawer__menu">
                @foreach ($data['items'] as $item)
                    @if ($item['columns'] !== [])
                        <li>
                            <details>
                                <summary>{{ $item['label'] }} <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg></summary>
                                <ul>
                                    @if ($item['all'] && $item['url'])
                                        <li><a href="{{ $item['url'] }}">{{ $item['all'] }}</a></li>
                                    @endif
                                    @foreach ($item['columns'] as $column)
                                        @if ($column['title'] !== null && $column['url'])
                                            <li><a href="{{ $column['url'] }}">{{ $column['title'] }}</a></li>
                                        @endif
                                        @foreach ($column['links'] as $link)
                                            @if ($link['url'])
                                                <li><a href="{{ $link['url'] }}">{{ $link['label'] }}</a></li>
                                            @endif
                                        @endforeach
                                    @endforeach
                                </ul>
                            </details>
                        </li>
                    @else
                        <li><a href="{{ $item['url'] ?? '#' }}" @if ($item['new_tab']) target="_blank" rel="noopener" @endif>{{ $item['label'] }}</a></li>
                    @endif
                @endforeach
            </ul>
            <ul class="drawer__links">
                <li><a href="{{ $accountUrl }}"><svg class="icon" aria-hidden="true"><use href="#i-user"></use></svg> {{ auth('customer')->check() ? 'Your account' : 'Sign in or create an account' }}</a></li>
                @if ($wishlistUrl)
                    <li><a href="{{ $wishlistUrl }}"><svg class="icon" aria-hidden="true"><use href="#i-heart"></use></svg> Wishlist</a></li>
                @endif
            </ul>
        </div>
    </dialog>
</header>
