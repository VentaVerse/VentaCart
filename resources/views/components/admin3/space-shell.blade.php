@props([
    'title',
    'space',          // the space's name in the top band and the title tail
    'groups',         // resolved nav groups: [{label, items: [{key, label, icon, line, url}]}]
    'active' => null, // context-nav active item key
    'railActive',     // which main-menu parent the icon rail highlights
    'roots',          // the space's URL roots (array); crossing INTO the space plays the seat entrance
])
{{-- The dual-menu shell for any SPACE: the condensed main-nav rail on
     the far left, the space's own context nav beside it, the measure
     centered in what remains. Settings wears it through its thin
     wrapper; module spaces (SEO first) pass their own registry. One
     component, so the spaces can never drift. --}}
@php
    $v3Store = \App\Support\StoreIdentity::name();
    $v3Favicon = \App\Support\StoreIdentity::faviconUrl();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title === $space ? $space : $title . ': ' . $space }}: {{ $v3Store }}</title>
    @if ($v3Favicon)
        <link rel="icon" href="{{ $v3Favicon }}">
    @endif
    <x-admin.theme-boot />
    <style> html { --boot-band: 56px; } </style>
    <link rel="preload" href="{{ \Illuminate\Support\Facades\Vite::asset('resources/fonts/Archivo-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ \Illuminate\Support\Facades\Vite::asset('resources/fonts/JetBrainsMono.woff2') }}" as="font" type="font/woff2" crossorigin>
    @vite(['resources/css/admin3/admin3.css', 'resources/js/admin3/admin3.js'])
    {{-- The crossing decision, pre-paint: when the previous page was
         outside this space (or this is a hard entry), the space's nav
         plays its seat entrance (.v3-cross in shell css).
         navigation.activation names the real previous entry (correct
         on back/forward); referrer is the fallback. --}}
    <script>
        (() => {
            const roots = window.__vSpaceRoots = @js($roots);
            let fromSpace = false;
            try {
                const prev = window.navigation?.activation?.from?.url ?? document.referrer ?? '';
                if (prev) {
                    const u = new URL(prev, location.origin);
                    fromSpace = u.origin === location.origin
                        && roots.some(r => u.pathname === r || u.pathname.startsWith(r + '/'));
                }
            } catch (e) { /* unparsable: treat as external */ }
            if (!fromSpace) document.documentElement.classList.add('v3-cross');
        })();
    </script>
    @stack('head')
</head>
{{-- x-data on body: Alpine only binds directives inside an x-data tree,
     and confirm triggers sit outside any component scope. The full-rail
     shell already does this; without it those buttons are dead. --}}
<body class="v3" x-data>
    <div class="v3-settings-root">
        <x-admin3.rail condensed :active="$railActive" />

        <div class="v3-settings v3-settings__frame">
            <header class="v3-settings__top">
                <a href="{{ route('admin.dashboard') }}" class="v3-settings__back v3-settings__back--mobile">
                    <x-admin3.icon name="back" class="v3-ico-sm" />
                    <span class="v3-sr">Back to admin</span>
                </a>
                <span class="v3-settings__name">{{ $space }}</span>
            </header>

            <div class="v3-settings__body">
                <x-admin3.context-nav :groups="$groups" :active="$active" :label="$space" />

                <main class="v3-settings__main">
                    <label class="v3-snav__picker">
                        <span class="v3-sr">{{ $space }} section</span>
                        <select class="v3-control" onchange="if (this.value) window.location.href = this.value">
                            <option value="">Go to…</option>
                            @foreach ($groups as $group)
                                <optgroup label="{{ $group['label'] }}">
                                    @foreach ($group['items'] as $item)
                                        <option value="{{ $item['url'] }}" @selected($active === $item['key'])>{{ $item['label'] }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </label>

                    <div class="v3-settings__measure">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>
    </div>

    <x-admin3.toasts />
    @stack('scripts')
</body>
</html>
