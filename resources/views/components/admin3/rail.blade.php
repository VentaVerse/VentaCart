@props(['condensed' => true, 'active' => null])
{{-- The 56px icon strip for the Settings and module SPACES only. The
     admin's one nav is the classic slab (owner ruling 2026-08-17); this
     strip is not a second menu but that same menu condensed: it renders
     the SAME parents, in the same order, from the same AdminNav
     registry, so the two can never drift. A parent group has no page of
     its own; its icon jumps to the group's first destination and the
     tooltip names the parent. --}}
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

    <nav class="v3-irail" aria-label="Main">
        {{-- Labels ride each icon as an instant styled pill (no native
             title attributes: the browser's tooltip is slow and plain). --}}
        <a href="{{ route('admin.dashboard') }}" class="v3-irail__mark"
           aria-label="{{ $railStore }} dashboard">{{ mb_substr($railStore, 0, 1) }}<span class="v3-irail__tip">{{ $railStore }}</span></a>
        {{-- The word rides under the icon (owner ruling 2026-08-17: an
             icon alone is a memory quiz; "If I want catalog, I want to
             see the word Catalog"). The strip stays the compact
             compromise the settings column forces, but it speaks. --}}
        @foreach ($railParents as $item)
            <a href="{{ route($item['route']) }}" class="v3-irail__item"
               @if ($active === $item['key']) aria-current="page" @endif>
                <x-admin3.icon :name="$item['icon']" />
                <span class="v3-irail__word">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>
