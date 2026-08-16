@props(['title' => 'Admin', 'active' => null])
@php
    $v3Store = \App\Support\StoreIdentity::name();
    $v3Logo = \App\Support\StoreIdentity::logoUrl();
    $v3Nav = config('admin3-nav');
    $v3User = Auth::user();
    $v3UserName = $v3User->name ?: $v3User->username;
    $v3Initials = strtoupper(mb_substr($v3UserName, 0, 2));
    $routeOk = fn ($item) => \Illuminate\Support\Facades\Route::has($item['route']);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}: {{ $v3Store }}</title>
    <x-admin.theme-boot />
    @vite(['resources/css/admin3/admin3.css', 'resources/js/admin3/admin3.js'])
    @stack('head')
</head>
<body class="v3" x-data="{ nav: false }" x-on:keydown.escape.window="nav = false">
<!--
VENTA ADMIN V3 DIRECTION CONTRACT
THESIS: The merchant's working surface where committing work is the designed moment; it refuses the multi-save page and the grouped mega-sidebar.
OWN-WORLD: Azure slab of seven work objects, brass as the only action color, tinted paper under white sheets, Archivo with rationed JetBrains Mono; Settings is its own full screen; a slab-colored save bar descends the instant a page goes dirty.
STORY: The operator always knows where they are, what is unsaved, and that a toast never lies.
FIRST VIEWPORT: Slab left with store name and lean nav, hairline topbar, one page title, sheets on tinted ground; the save bar hidden until work exists.
FORM: ERP-cousin material, brief-pinned; Shopify topology locked by the owner.
FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, and the v3 design record.
-->
    <div class="v3-shell">
        <div class="v3-scrim" x-show="nav" x-transition.opacity @click="nav = false" x-cloak aria-hidden="true"></div>

        <aside class="v3-rail" :class="nav && 'is-open'" aria-label="Navigation">
            <div class="v3-rail__brand">
                @if ($v3Logo)
                    <img src="{{ $v3Logo }}" alt="{{ $v3Store }}" class="v3-rail__logo">
                @else
                    <span class="v3-rail__mark" aria-hidden="true">{{ mb_substr($v3Store, 0, 1) }}</span>
                    <span class="v3-rail__store">{{ $v3Store }}</span>
                @endif
            </div>

            <nav class="v3-nav" aria-label="Main">
                @foreach (array_filter($v3Nav['work'], $routeOk) as $item)
                    <a href="{{ route($item['route']) }}" class="v3-nav__item"
                       @if ($active === $item['key']) aria-current="page" @endif>
                        <x-admin3.icon :name="$item['icon']" />
                        {{ $item['label'] }}
                    </a>
                @endforeach

                @php $v3Modules = array_filter($v3Nav['modules'], $routeOk); @endphp
                @if ($v3Modules !== [])
                    <div class="v3-nav__group">Modules</div>
                    @foreach ($v3Modules as $item)
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

        <div>
            <header class="v3-top">
                <button type="button" class="v3-top__burger" @click="nav = true"
                        aria-label="Open navigation" :aria-expanded="nav ? 'true' : 'false'">
                    <x-admin3.icon name="menu" style="width:19px;height:19px" />
                </button>
                <span class="v3-top__who" style="margin-left:auto">
                    <span class="v3-top__ava" aria-hidden="true">{{ $v3Initials }}</span>
                    {{ $v3UserName }}
                </span>
            </header>

            <main class="v3-main" id="v3-main">
                {{ $slot }}
            </main>
        </div>
    </div>

    <x-admin3.toasts />
    @stack('scripts')
</body>
</html>
