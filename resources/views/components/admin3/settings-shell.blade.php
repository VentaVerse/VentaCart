@props(['title' => 'Settings', 'active' => null])
@php
    $v3Store = \App\Support\StoreIdentity::name();
    $v3Groups = collect(config('admin3-settings-nav'))
        ->map(function ($group) {
            $group['items'] = array_values(array_filter(
                $group['items'],
                fn ($i) => \Illuminate\Support\Facades\Route::has($i['route'])
            ));
            return $group;
        })
        ->filter(fn ($group) => $group['items'] !== [])
        ->values();
    $v3Url = fn ($i) => $i['section'] !== null ? route($i['route'], $i['section']) : route($i['route']);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}: Settings: {{ $v3Store }}</title>
    <x-admin.theme-boot />
    @vite(['resources/css/admin3/admin3.css', 'resources/js/admin3/admin3.js'])
    @stack('head')
</head>
<body class="v3">
    <div class="v3-settings">
        <header class="v3-settings__top">
            <a href="{{ route('admin.dashboard') }}" class="v3-settings__back">
                <x-admin3.icon name="back" style="width:16px;height:16px" />
                Back to admin
            </a>
            <span class="v3-settings__name">Settings</span>
        </header>

        <div class="v3-settings__body">
            <nav class="v3-snav" aria-label="Settings">
                @foreach ($v3Groups as $group)
                    <div class="v3-snav__group">{{ $group['label'] }}</div>
                    @foreach ($group['items'] as $item)
                        <a href="{{ $v3Url($item) }}" class="v3-snav__item"
                           @if ($active === $item['key']) aria-current="page" @endif>
                            <x-admin3.icon :name="$item['icon']" />
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                @endforeach
            </nav>

            <main class="v3-settings__main">
                <label class="v3-snav__picker">
                    <span class="v3-sr">Settings section</span>
                    <select class="v3-control" onchange="if (this.value) window.location.href = this.value">
                        <option value="">Go to…</option>
                        @foreach ($v3Groups as $group)
                            <optgroup label="{{ $group['label'] }}">
                                @foreach ($group['items'] as $item)
                                    <option value="{{ $v3Url($item) }}" @selected($active === $item['key'])>{{ $item['label'] }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </label>

                {{ $slot }}
            </main>
        </div>
    </div>

    <x-admin3.toasts />
    @stack('scripts')
</body>
</html>
