@props(['title' => 'Admin', 'active' => null])
@php
    $v3Store = \App\Support\StoreIdentity::name();
    $v3User = Auth::user();
    $v3UserName = $v3User->name ?: $v3User->username;
    $v3Initials = strtoupper(mb_substr($v3UserName, 0, 2));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}: {{ $v3Store }}</title>
    <x-admin.theme-boot />
    <link rel="preload" href="{{ \Illuminate\Support\Facades\Vite::asset('resources/fonts/Archivo-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ \Illuminate\Support\Facades\Vite::asset('resources/fonts/JetBrainsMono.woff2') }}" as="font" type="font/woff2" crossorigin>
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

        <x-admin3.rail :active="$active" />

        <div>
            <header class="v3-top">
                <button type="button" class="v3-top__burger" @click="nav = true"
                        aria-label="Open navigation" :aria-expanded="nav ? 'true' : 'false'">
                    <x-admin3.icon name="menu" class="v3-ico" />
                </button>
                <span class="v3-top__who">
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
