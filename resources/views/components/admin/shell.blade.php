@props(['title' => 'Admin'])
@php
    // The store's own identity, resolved exactly as the storefront resolves
    // it: settings group 'identity', paths on the public disk. The slab shows
    // the merchant, never the platform, because the person logged in runs a
    // store and Venta is only the application it runs on.
    $vStoreName = \App\Support\StoreIdentity::name();
    $vLogo = \App\Support\StoreIdentity::logoUrl();
    $vFavicon = \App\Support\StoreIdentity::faviconUrl();

    $vNavGroups = \App\Support\AdminNav::groups(Auth::user());
    $vActive = \App\Support\AdminNav::activeItemKey(request()->path());

    // One group is open at a time, and the value lives on the <nav> rather
    // than on each group: a single value cannot name two groups, which is
    // what makes opening one close the others. The group holding the current
    // route starts open, so the slab shows where you are before Alpine runs.
    $vOpenGroup = null;
    foreach ($vNavGroups as $i => $group) {
        if ($group['label'] !== null && $vActive && $vActive['group'] === $group['label']) {
            $vOpenGroup = 'g' . $i;
        }
    }

    $vUser = Auth::user();
    $vUserName = $vUser->name ?: $vUser->username;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}: {{ $vStoreName }}</title>
    @if ($vFavicon)
        <link rel="icon" href="{{ $vFavicon }}">
    @endif
    <x-admin.theme-boot />
    <link rel="preload" href="{{ \Illuminate\Support\Facades\Vite::asset('resources/fonts/Archivo-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ \Illuminate\Support\Facades\Vite::asset('resources/fonts/JetBrainsMono.woff2') }}" as="font" type="font/woff2" crossorigin>
    @vite(['resources/css/app.css', 'resources/css/admin/admin.css', 'resources/js/admin/admin.js', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="v-admin min-h-screen" x-data="vShell()" x-on:keydown.escape.window="closeNav()">
<!--
IMPECCABLE DIRECTION CONTRACT
THESIS: Venta's admin is the ERP's sibling, not its twin. It refuses the neutral grey admin chrome it inherited and the temptation to look like a different product family.
OWN-WORLD: The Blotter's material (Archivo, tinted paper under raised white sheets, two elevations, 16px panels and 11px controls, sentence-case labels, square dots, rationed mono) wearing Venta's identity: an azure-navy slab where the ERP is near-black, warm brass where the ERP is cold cyan, and the store's own name and logo where the ERP shows the company.
STORY: The operator glances at the sidebar and knows, without reading, that they are in their store's admin and not the ERP.
FIRST VIEWPORT: A 216px azure slab carrying the store's logo, name and grouped navigation; a hairline topbar with the search trigger, theme switch and account; the work surface a full-bleed tinted ground holding raised white sheets.
FORM: Brief-pinned. The owner named the ERP's Blotter as the world to mirror and OpenCart's blue as the identity anchor, so no direction roll applies.
FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, and DESIGN.md
-->
    {{-- First stop in the tab order, so a keyboard user is never made to
         walk the slab's controls to reach the page. Visible only while
         focused; the recipe lives in shell.css. --}}
    <a href="#v-main" class="v-skip">Skip to content</a>
    <div class="lg:grid lg:grid-cols-[var(--sidebar-w)_minmax(0,1fr)] min-h-screen">
        {{-- Scrim, mobile only. --}}
        <div x-show="mobileNav" x-transition.opacity x-cloak @click="closeNav()"
             class="fixed inset-0 z-40 bg-black/45 lg:hidden" aria-hidden="true"></div>

        {{-- The slab. Off-canvas below lg, permanent above it.

             Below lg it is a drawer, and a closed drawer is not a place
             you can be: inert takes its 27 controls out of the tab order
             rather than leaving them focusable 216px off-screen, and
             x-dialog holds focus inside it while it is open and hands it
             back to the hamburger when it closes. Both are gated on
             lgUp, because above lg this is simply the page's navigation
             and every one of those controls should be reachable.
             inert does not touch the transform, so the slide is
             unchanged. --}}
        <aside :class="mobileNav ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
               :inert="navClosed"
               x-dialog="mobileNav && !lgUp"
               id="v-slab" aria-label="Navigation"
               class="v-slab fixed inset-y-0 left-0 z-50 flex w-[var(--sidebar-w)] flex-col transition-transform duration-200 lg:static">
            <div class="v-slab__brand">
                <a href="{{ route('admin.dashboard') }}" class="v-slab__home" aria-label="{{ $vStoreName }} dashboard">
                    @if ($vLogo)
                        <span class="v-logo-plate">
                            <img src="{{ $vLogo }}" alt="{{ $vStoreName }}" class="v-slab__logo">
                        </span>
                    @else
                        <span class="v-slab__mark" aria-hidden="true">{{ \App\Support\StoreIdentity::initial() }}</span>
                        <span class="v-slab__name" title="{{ $vStoreName }}">{{ $vStoreName }}</span>
                    @endif
                </a>
                {{-- Drawer-only: shell.css removes it above lg, where the
                     slab is permanent and has nothing to close. A Tailwind
                     lg:hidden here loses to .v-tap's own display by
                     specificity, which is the regression that put an X on
                     a permanent sidebar. --}}
                <button type="button" @click="closeNav()" class="v-slab__close v-tap" aria-label="Close navigation"
                        data-v-dialog-initial>
                    <x-admin.icon name="x" class="w-4 h-4" />
                </button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto">
                <nav class="v-nav" aria-label="Main" x-data="{ group: @js($vOpenGroup) }">
                    @foreach ($vNavGroups as $i => $group)
                        @php $vKey = 'g' . $i; @endphp
                        @if ($group['label'] === null)
                            @foreach ($group['items'] as $item)
                                @php $vOn = $vActive && $vActive['group'] === null && $vActive['item'] === $item['key']; @endphp
                                <a href="{{ route($item['route']) }}" class="v-nav__item {{ $vOn ? 'is-on' : '' }}"
                                   @if ($vOn) aria-current="page" @endif>
                                    <x-admin.icon :name="$item['icon']" class="w-[17px] h-[17px] shrink-0" />
                                    <span class="v-nav__label">{{ $item['label'] }}</span>
                                    {{-- Work waiting on this entry, when a composer has bound a
                                         count for it. A lookup, so the shell never learns which
                                         entry is special, and zero prints nothing because an
                                         empty badge is a thing to check. --}}
                                    @if (($vBadge = ($navBadges ?? [])[$item['route']] ?? 0) > 0)
                                        <span class="v-nav__badge">{{ number_format($vBadge) }}</span>
                                    @endif
                                </a>
                            @endforeach
                        @else
                            <div class="v-nav__group">
                                <button type="button"
                                        @click="group = (group === '{{ $vKey }}' ? null : '{{ $vKey }}')"
                                        aria-expanded="{{ $vOpenGroup === $vKey ? 'true' : 'false' }}"
                                        :aria-expanded="group === '{{ $vKey }}' ? 'true' : 'false'"
                                        aria-controls="v-nav-{{ $vKey }}"
                                        class="v-nav__toggle">
                                    <x-admin.icon :name="$group['icon'] ?? $group['items'][0]['icon']" class="w-[17px] h-[17px] shrink-0" />
                                    <span class="v-nav__label">{{ $group['label'] }}</span>
                                    <span class="v-nav__chev">
                                        <x-admin.icon name="chevron-right" class="w-3 h-3" />
                                    </span>
                                </button>
                                {{-- The collapse animates both ways: the 0fr/1fr grid row is the
                                     CSS-only way to transition to a height nobody measured, where
                                     x-show would snap. inert keeps a closed group's links out of
                                     the tab order rather than merely out of sight. --}}
                                {{-- The open group is rendered open. Alpine then agrees rather
                                     than announces, so its boot mutates no class and the collapse
                                     transition has nothing to replay on arrival. --}}
                                {{-- Object syntax, not `expr && 'is-open'`: Alpine's string
                                     syntax never removes a class the server printed, so the
                                     boot-open group stayed visually open (with inert links)
                                     after another group was opened. --}}
                                <div class="v-collapse {{ $vOpenGroup === $vKey ? 'is-open' : '' }}" id="v-nav-{{ $vKey }}"
                                     :class="{ 'is-open': group === '{{ $vKey }}' }"
                                     @if ($vOpenGroup !== $vKey) inert @endif
                                     :inert="group !== '{{ $vKey }}'">
                                    <div class="v-collapse__inner">
                                        @foreach ($group['items'] as $item)
                                            @php $vOn = $vActive && $vActive['group'] === $group['label'] && $vActive['item'] === $item['key']; @endphp
                                            <a href="{{ $item['url'] ?? route($item['route']) }}" class="v-nav__child {{ $vOn ? 'is-on' : '' }}"
                                               @if ($vOn) aria-current="page" @endif>
                                                <span class="v-nav__label">{{ $item['label'] }}</span>
                                                @if (($vBadge = ($navBadges ?? [])[$item['route']] ?? 0) > 0)
                                                    <span class="v-nav__badge">{{ number_format($vBadge) }}</span>
                                                @endif
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </nav>
            </div>

            <a href="{{ route('home') }}" target="_blank" rel="noopener" class="v-slab__foot">
                <span class="v-slab__who">
                    <b>{{ $vStoreName }}</b>
                    <span>View store</span>
                </span>
                <x-admin.icon name="external" class="v-slab__act w-3.5 h-3.5" />
            </a>
        </aside>

        <div class="flex min-w-0 flex-col">
            <header class="v-topbar sticky top-0 z-30">
                {{-- Drawer-only, like the close above: hidden above lg by
                     shell.css rather than by a utility .v-tap outranks. --}}
                <button type="button" @click="mobileNav = true" class="v-topbar__menu v-tap text-v-secondary hover:text-v-text"
                        aria-label="Open navigation" aria-controls="v-slab" :aria-expanded="mobileNav ? 'true' : 'false'">
                    <x-admin.icon name="menu" class="w-5 h-5" />
                </button>

                {{-- Opens the command palette, which owns search for the whole
                     admin. Cmd+K and Ctrl+K reach the same component; a
                     shortcut nobody can see is not discoverable, so the
                     control states it. --}}
                <button type="button" class="v-search-trigger"
                        x-data="{ mac: /Mac|iPod|iPhone|iPad/.test(navigator.platform || navigator.userAgent || '') }"
                        @click="$dispatch('open-palette')">
                    <x-admin.icon name="search" class="w-3.5 h-3.5 shrink-0" />
                    <span class="v-search-trigger__label">Search products, orders, customers</span>
                    <kbd class="v-search-trigger__hint" x-text="mac ? '&#8984;K' : 'Ctrl K'"></kbd>
                </button>

                <div class="v-topbar__right">
                    <x-admin.theme-toggle />

                    <div class="v-account" x-data="{ open: false }" @keydown.escape="open = false">
                        <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
                                aria-haspopup="true" aria-controls="v-account-menu"
                                class="flex items-center gap-2 text-[13px] font-medium text-v-text">
                            <span class="v-account__avatar">{{ mb_strtoupper(mb_substr($vUserName, 0, 2)) }}</span>
                            <span class="hidden sm:inline">{{ $vUserName }}</span>
                        </button>
                        <div x-show="open" x-cloak @click.outside="open = false" x-transition.duration.120ms
                             id="v-account-menu"
                             class="v-menu absolute right-0 z-50 mt-1.5 w-56 overflow-hidden rounded-v-lg border border-v-strong bg-v-surface py-1">
                            <div class="border-b border-v-border px-3.5 pb-2 pt-1">
                                <div class="text-[13px] font-semibold text-v-text">{{ $vUserName }}</div>
                                <div class="text-[12px] text-v-muted">{{ $vUser->role?->name ?? 'Administrator' }}</div>
                            </div>
                            <a href="{{ route('admin.profile.edit') }}" class="v-menu__item flex items-center gap-2">
                                <x-admin.icon name="customers" class="w-3.5 h-3.5" /> My profile
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="v-menu__item flex items-center gap-2">
                                    <x-admin.icon name="logout" class="w-3.5 h-3.5" /> Sign out
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <main id="v-main" class="v-content">
                {{ $slot }}
            </main>
        </div>
    </div>
    <x-admin.toasts />
    <x-admin.command-palette />
    @stack('scripts')
</body>
</html>
