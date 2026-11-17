{{--
    The account menu beside every account page: who is signed in, the
    account pages core offers, the wishlist, and signing out (a POST with
    the CSRF token, as core requires).
--}}
@php
    $who = auth('customer')->user();
    $initials = mb_strtoupper(mb_substr((string) $who?->first_name, 0, 1) . mb_substr((string) $who?->last_name, 0, 1));
    $links = [
        ['route' => 'account.dashboard', 'match' => 'account.dashboard', 'icon' => 'home', 'label' => 'Dashboard'],
        ['route' => 'account.orders', 'match' => 'account.orders*', 'icon' => 'package', 'label' => 'Orders'],
        ['route' => 'account.addresses', 'match' => 'account.addresses*', 'icon' => 'map-pin', 'label' => 'Addresses'],
        ['route' => 'wishlist.index', 'match' => 'wishlist.index', 'icon' => 'heart', 'label' => 'Wishlist'],
        ['route' => 'account.profile', 'match' => 'account.profile', 'icon' => 'user', 'label' => 'Profile and password'],
    ];
    // Then the links extensions added (App\Support\AccountMenu), with the theme's icon for each of the contract's names.
    $xelgaIcons = ['file' => 'list', 'wallet' => 'wallet', 'return' => 'rotate-ccw', 'store' => 'bag', 'star' => 'star', 'gift' => 'tag'];
    $extensionLinks = class_exists(\App\Support\AccountMenu::class) ? \App\Support\AccountMenu::links() : [];
@endphp
<nav class="acct__nav" aria-label="Your account">
    @if ($who)
        <p class="acct__who">
            <span class="acct__avatar" aria-hidden="true">{{ $initials }}</span>
            <span><strong>{{ $who->full_name }}</strong><span class="muted">{{ $who->email }}</span></span>
        </p>
    @endif
    <ul>
        @foreach ($links as $link)
            @if (\Illuminate\Support\Facades\Route::has($link['route']))
                <li><a href="{{ route($link['route']) }}" @if (request()->routeIs($link['match'])) aria-current="page" @endif><svg class="icon icon--sm" aria-hidden="true"><use href="#i-{{ $link['icon'] }}"></use></svg><span>{{ $link['label'] }}</span></a></li>
            @endif
        @endforeach
        @foreach ($extensionLinks as $link)
            <li><a href="{{ $link['url'] }}" @if ($link['current']) aria-current="page" @endif><svg class="icon icon--sm" aria-hidden="true"><use href="#i-{{ $xelgaIcons[$link['icon']] ?? 'list' }}"></use></svg><span>{{ $link['label'] }}</span></a></li>
        @endforeach
    </ul>
    <form method="POST" action="{{ route('customer.logout') }}">
        @csrf
        <button type="submit" class="text-btn acct__out"><svg class="icon icon--sm" aria-hidden="true"><use href="#i-logout"></use></svg>Sign out</button>
    </form>
</nav>
