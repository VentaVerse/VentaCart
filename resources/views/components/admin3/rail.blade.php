@props(['condensed' => false, 'active' => null])
{{-- The main nav, one component for both of its states: the full azure
     sidebar on work pages, the 56px icon strip when condensed (Settings).
     Condensed renders the SAME parents as the main menu, in the same
     order, straight from config/admin-nav.php — so the two can never
     drift. Settings rides the list as the seventh icon like any other
     parent (owner ruling: nothing is pinned apart or hidden; the rail
     is simply the main menu, condensed). A parent group has no page of
     its own; its icon jumps to the group's first destination and the
     tooltip names the parent.
     Full mode expects an Alpine `nav` boolean in scope for the mobile
     drawer (the work shell provides it); condensed mode has no drawer. --}}
@php
    $railStore = \App\Support\StoreIdentity::name();
    $railLogo = \App\Support\StoreIdentity::logoUrl();
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
        {{-- Labels ride each icon as an instant styled pill (no native
             title attributes: the browser's tooltip is slow and plain). --}}
        <a href="{{ route('admin.dashboard') }}" class="v3-irail__mark"
           aria-label="{{ $railStore }} dashboard">{{ mb_substr($railStore, 0, 1) }}<span class="v3-irail__tip">{{ $railStore }}</span></a>
        @foreach ($railParents as $item)
            <a href="{{ route($item['route']) }}" class="v3-irail__item"
               aria-label="{{ $item['label'] }}"
               @if ($active === $item['key']) aria-current="page" @endif>
                <x-admin3.icon :name="$item['icon']" />
                <span class="v3-irail__tip">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>
@else
    @php
        // The full rail IS the admin's one nav (owner ruling, 2026-08-17):
        // it renders the same AdminNav registry, with the same grouping and
        // the same collapse behavior, as the classic sidebar on the not-yet-
        // migrated pages. One registry, two skins, zero drift. The `active`
        // prop is not consulted here; the current item is derived from the
        // request path exactly as the classic sidebar derives it.
        $railGroups = \App\Support\AdminNav::groups(auth()->user());
        $railActive = \App\Support\AdminNav::activeItemKey(request()->path());

        $railOpen = null;
        foreach ($railGroups as $i => $railGroup) {
            if ($railGroup['label'] !== null && $railActive && $railActive['group'] === $railGroup['label']) {
                $railOpen = 'g' . $i;
            }
        }
    @endphp
    <aside class="v3-rail" :class="nav && 'is-open'" aria-label="Navigation" x-data="{ group: @js($railOpen) }">
        <div class="v3-rail__brand">
            @if ($railLogo)
                <img src="{{ $railLogo }}" alt="{{ $railStore }}" class="v3-rail__logo">
            @else
                <span class="v3-rail__mark" aria-hidden="true">{{ mb_substr($railStore, 0, 1) }}</span>
                <span class="v3-rail__store">{{ $railStore }}</span>
            @endif
        </div>

        <nav class="v3-nav" aria-label="Main">
            @foreach ($railGroups as $i => $railGroup)
                @php $railKey = 'g' . $i; @endphp
                @if ($railGroup['label'] === null)
                    @foreach ($railGroup['items'] as $item)
                        @php $railOn = $railActive && $railActive['group'] === null && $railActive['item'] === $item['key']; @endphp
                        <a href="{{ route($item['route']) }}" class="v3-nav__item"
                           @if ($railOn) aria-current="page" @endif>
                            <x-admin3.icon :name="$railIcon($item['icon'])" />
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                @else
                    <div class="v3-nav__set">
                        <button type="button" class="v3-nav__item v3-nav__toggle"
                                @click="group = (group === '{{ $railKey }}' ? null : '{{ $railKey }}')"
                                aria-expanded="{{ $railOpen === $railKey ? 'true' : 'false' }}"
                                :aria-expanded="group === '{{ $railKey }}' ? 'true' : 'false'"
                                aria-controls="v3-nav-{{ $railKey }}">
                            <x-admin3.icon :name="$railIcon($railGroup['icon'] ?? $railGroup['items'][0]['icon'])" />
                            {{ $railGroup['label'] }}
                            <span class="v3-nav__chev" aria-hidden="true"><x-admin3.icon name="chevron" /></span>
                        </button>
                        {{-- Same collapse mechanics as the classic sidebar: the open
                             group is RENDERED open so Alpine's boot agrees rather than
                             announces; the 0fr/1fr grid row animates to an unmeasured
                             height; object :class syntax so the server-printed is-open
                             is actually removed when another group opens; inert keeps
                             a closed group's links out of the tab order. --}}
                        <div class="v3-collapse {{ $railOpen === $railKey ? 'is-open' : '' }}" id="v3-nav-{{ $railKey }}"
                             :class="{ 'is-open': group === '{{ $railKey }}' }"
                             @if ($railOpen !== $railKey) inert @endif
                             :inert="group !== '{{ $railKey }}'">
                            <div class="v3-collapse__inner">
                                @foreach ($railGroup['items'] as $item)
                                    @php $railOn = $railActive && $railActive['group'] === $railGroup['label'] && $railActive['item'] === $item['key']; @endphp
                                    <a href="{{ $item['url'] ?? route($item['route']) }}" class="v3-nav__child"
                                       @if ($railOn) aria-current="page" @endif>
                                        {{ $item['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
        </nav>

        <a href="{{ route('home') }}" target="_blank" rel="noopener" class="v3-rail__foot v3-nav__item">
            <x-admin3.icon name="store" />
            View store
        </a>
    </aside>
@endif
