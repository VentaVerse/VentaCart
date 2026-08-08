@props(['title' => 'Sign in', 'subtitle' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}: {{ config('app.name', 'Venta') }} Admin</title>
    <x-admin.theme-boot />
    @vite(['resources/css/app.css', 'resources/css/admin/admin.css', 'resources/js/admin/admin.js', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="v-admin min-h-screen flex items-center justify-center px-6 py-12">
    <div class="w-full max-w-[380px]">
        <div class="flex items-center justify-between mb-10">
            <a href="{{ route('login') }}" class="text-[15px] font-semibold text-v-text">{{ config('app.name') }}</a>
            <x-admin.theme-toggle />
        </div>

        <h1 class="text-[26px] font-semibold text-v-text tracking-tight leading-tight">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1.5 text-[14px] text-v-muted leading-relaxed">{{ $subtitle }}</p>
        @endif

        <div class="mt-8">
            @if (session('status'))
                <div class="mb-6 rounded-v-md border border-v-border bg-v-accent-weak px-3.5 py-2.5 text-[13px] text-v-accent">
                    {{ session('status') }}
                </div>
            @endif

            {{ $slot }}
        </div>

        <p class="mt-10 text-center text-xs text-v-muted">
            <a href="/" class="hover:text-v-secondary">{{ config('app.name') }}</a> &middot; &copy; {{ date('Y') }}
        </p>
    </div>
    @stack('scripts')
</body>
</html>
