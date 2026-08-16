@props(['title' => 'Settings', 'active' => null])
@php
    $v3Store = \App\Support\StoreIdentity::name();
    $routeOk = fn ($item) => \Illuminate\Support\Facades\Route::has($item['route']);
    // Resolve each destination's URL once; the context nav and the mobile
    // picker both consume the same resolved groups.
    $v3Groups = collect(config('admin3-settings-nav'))
        ->map(function ($group) use ($routeOk) {
            $group['items'] = collect($group['items'])
                ->filter($routeOk)
                ->map(fn ($i) => $i + ['url' => $i['section'] !== null ? route($i['route'], $i['section']) : route($i['route'])])
                ->values()
                ->all();
            return $group;
        })
        ->filter(fn ($group) => $group['items'] !== [])
        ->values()
        ->all();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title === 'Settings' ? 'Settings' : $title . ': Settings' }}: {{ $v3Store }}</title>
    <x-admin.theme-boot />
    <style> html { --boot-band: 56px; } </style>
    <link rel="preload" href="{{ \Illuminate\Support\Facades\Vite::asset('resources/fonts/Archivo-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ \Illuminate\Support\Facades\Vite::asset('resources/fonts/JetBrainsMono.woff2') }}" as="font" type="font/woff2" crossorigin>
    @vite(['resources/css/admin3/admin3.css', 'resources/js/admin3/admin3.js'])
    {{-- The crossing decision, pre-paint: when the previous page was on
         the other side of the Settings boundary (or this is a hard
         entry), the nav plays its live-DOM Easypanel move (shell css,
         .v3-cross). navigation.activation names the real previous entry
         (correct on back/forward); referrer is the fallback. --}}
    <script>
        (() => {
            const root = window.__vSettingsRoot = @js(parse_url(route('admin.settings.index'), PHP_URL_PATH));
            let fromSettings = false;
            try {
                const prev = window.navigation?.activation?.from?.url ?? document.referrer ?? '';
                if (prev) {
                    const u = new URL(prev, location.origin);
                    fromSettings = u.origin === location.origin
                        && (u.pathname === root || u.pathname.startsWith(root + '/'));
                }
            } catch (e) { /* unparsable: treat as external */ }
            if (!fromSettings) document.documentElement.classList.add('v3-cross');
        })();
    </script>
    @stack('head')
</head>
<body class="v3">
    <div class="v3-settings-root">
        <x-admin3.rail condensed active="settings" />

        <div class="v3-settings v3-settings__frame">
            <header class="v3-settings__top">
                <a href="{{ route('admin.dashboard') }}" class="v3-settings__back v3-settings__back--mobile">
                    <x-admin3.icon name="back" class="v3-ico-sm" />
                    <span class="v3-sr">Back to admin</span>
                </a>
                <span class="v3-settings__name">Settings</span>
            </header>

            <div class="v3-settings__body">
                <x-admin3.context-nav :groups="$v3Groups" :active="$active" label="Settings" />

                <main class="v3-settings__main">
                    <label class="v3-snav__picker">
                        <span class="v3-sr">Settings section</span>
                        <select class="v3-control" onchange="if (this.value) window.location.href = this.value">
                            <option value="">Go to…</option>
                            @foreach ($v3Groups as $group)
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
