@props(['title' => 'Sign in', 'subtitle' => null])
@php
    // Same identity the slab wears, resolved the same way: whoever reaches
    // this page is signing in to a store, not to the platform.
    $vStoreName = \App\Support\StoreIdentity::name();
    $vLogo = \App\Support\StoreIdentity::logoUrl();
    $vFavicon = \App\Support\StoreIdentity::faviconUrl();
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
    @vite(['resources/css/app.css', 'resources/css/admin/admin.css', 'resources/js/admin/admin.js', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="v-admin min-h-screen flex items-center justify-center px-6 py-12">
    <div class="w-full max-w-[400px]">
        <div class="mb-6 flex items-center justify-between gap-4">
            <a href="{{ route('login') }}" class="flex min-w-0 items-center gap-2.5 no-underline">
                @if ($vLogo)
                    {{-- Same plate as the slab: the sign-in page follows the
                         theme, so a dark-ink logo needs its own ground here too. --}}
                    <span class="v-logo-plate">
                        <img src="{{ $vLogo }}" alt="{{ $vStoreName }}" class="h-7 w-auto max-w-[150px] object-contain">
                    </span>
                @else
                    <span class="grid h-7 w-7 place-items-center rounded-v-sm bg-v-mark text-[13px] font-bold text-v-mark-ink">{{ \App\Support\StoreIdentity::initial() }}</span>
                    <span class="truncate text-[15px] font-semibold tracking-tight text-v-text">{{ $vStoreName }}</span>
                @endif
            </a>
            <x-admin.theme-toggle />
        </div>

        <div class="bg-v-surface border border-v-border rounded-v-lg px-7 py-7">
            <h1 class="text-[22px] font-semibold text-v-text tracking-tight leading-tight">{{ $title }}</h1>
            @if ($subtitle)
                <p class="mt-1.5 text-[13.5px] text-v-secondary leading-relaxed">{{ $subtitle }}</p>
            @endif

            <div class="mt-6">
                @if (session('status'))
                    <div class="mb-5 rounded-v-md border border-v-border bg-v-accent-weak px-3.5 py-2.5 text-[13px] text-v-accent">
                        {{ session('status') }}
                    </div>
                @endif

                {{ $slot }}
            </div>
        </div>

        <p class="mt-6 text-center text-xs text-v-muted">
            <a href="/" class="hover:text-v-secondary">{{ $vStoreName }}</a> &middot; &copy; {{ date('Y') }}
        </p>
    </div>
    @stack('scripts')
</body>
</html>
