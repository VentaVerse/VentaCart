@props(['condensed' => false, 'active' => null])
{{-- The main nav, one component for both of its states: the full azure
     sidebar on work pages, the 56px icon strip when condensed (Settings).
     Condensed renders the SAME parents as the main menu, in the same
     order, straight from config/admin-nav.php — so the two can never
     drift. A parent group has no page of its own; its icon jumps to the
     group's first destination and the tooltip names the parent.
     Full mode expects an Alpine `nav` boolean in scope for the mobile
     drawer (the work shell provides it); condensed mode has no drawer. --}}
@php
    $railStore = \App\Support\StoreIdentity::name();
    $railLogo = \App\Support\StoreIdentity::logoUrl();
    $railOk = fn ($item) => \Illuminate\Support\Facades\Route::has($item['route']);
    $railWork = array_values(array_filter(config('admin3-nav')['work'], $railOk));
    $railMods = array_values(array_filter(config('admin3-nav')['modules'], $railOk));

    // The v2 registry names icons the v3 set does not carry.
    $railIcon = fn ($name) => [
        'dashboard' => 'home',
        'pages' => 'content',
        'coupons' => 'marketing',
    ][$name] ?? $name;

    $railParents = [];
    foreach (\App\Support\AdminNav::groups(auth()->user()) as $railGroup) {
        if ($railGroup['label'] === null) {
            foreach ($railGroup['items'] as $railItem) {
                if ($railItem['key'] === 'settings') {
                    continue; // pinned at the foot below
                }
                $railParents[] = ['label' => $railItem['label'], 'icon' => $railIcon($railItem['icon']), 'route' => $railItem['route'], 'key' => $railItem['key']];
            }
        } else {
            $railFirst = $railGroup['items'][0];
            $railParents[] = ['label' => $railGroup['label'], 'icon' => $railIcon($railGroup['icon'] ?? $railFirst['icon']), 'route' => $railFirst['route'], 'key' => $railGroup['segment'] ?? $railFirst['key']];
        }
    }
@endphp
@if ($condensed)
    <nav class="v3-irail" aria-label="Main">
        <a href="{{ route('admin.dashboard') }}" class="v3-irail__mark"
           title="{{ $railStore }} dashboard" aria-label="{{ $railStore }} dashboard">{{ mb_substr($railStore, 0, 1) }}</a>
        @foreach ($railParents as $item)
            <a href="{{ route($item['route']) }}" class="v3-irail__item"
               title="{{ $item['label'] }}" aria-label="{{ $item['label'] }}"
               @if ($active === $item['key']) aria-current="page" @endif>
                <x-admin3.icon :name="$item['icon']" />
            </a>
        @endforeach
        <span class="v3-irail__foot">
            <a href="{{ route('admin.settings.index') }}" class="v3-irail__item"
               title="Settings" aria-label="Settings"
               @if ($active === 'settings') aria-current="page" @endif>
                <x-admin3.icon name="settings" />
            </a>
        </span>
    </nav>
@else
    <aside class="v3-rail" :class="nav && 'is-open'" aria-label="Navigation">
        <div class="v3-rail__brand">
            @if ($railLogo)
                <img src="{{ $railLogo }}" alt="{{ $railStore }}" class="v3-rail__logo">
            @else
                <span class="v3-rail__mark" aria-hidden="true">{{ mb_substr($railStore, 0, 1) }}</span>
                <span class="v3-rail__store">{{ $railStore }}</span>
            @endif
        </div>

        <nav class="v3-nav" aria-label="Main">
            @foreach ($railWork as $item)
                <a href="{{ route($item['route']) }}" class="v3-nav__item"
                   @if ($active === $item['key']) aria-current="page" @endif>
                    <x-admin3.icon :name="$item['icon']" />
                    {{ $item['label'] }}
                </a>
            @endforeach

            @if ($railMods !== [])
                <div class="v3-nav__group">Modules</div>
                @foreach ($railMods as $item)
                    <a href="{{ route($item['route']) }}" class="v3-nav__item"
                       @if ($active === $item['key']) aria-current="page" @endif>
                        <x-admin3.icon :name="$item['icon']" />
                        {{ $item['label'] }}
                    </a>
                @endforeach
            @endif
        </nav>

        <div class="v3-rail__foot">
            <a href="{{ route('admin.settings.index') }}" class="v3-nav__item"
               @if ($active === 'settings') aria-current="page" @endif>
                <x-admin3.icon name="settings" />
                Settings
            </a>
        </div>
    </aside>
@endif
