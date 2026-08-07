@props(['title' => 'Admin'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — {{ config('app.name', 'Venta') }}</title>
    <x-admin.theme-boot />
    @vite(['resources/css/app.css', 'resources/css/admin/admin.css', 'resources/js/admin/admin.js', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="v-admin min-h-screen" x-data="{ mobileNav: false }" x-on:keydown.escape.window="mobileNav = false">
    <div class="flex min-h-screen">
        {{-- Mobile overlay --}}
        <div x-show="mobileNav" x-transition.opacity x-cloak class="fixed inset-0 z-40 bg-black/50 lg:hidden" @click="mobileNav = false"></div>

        {{-- Sidebar --}}
        <aside :class="mobileNav ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
               role="dialog" aria-modal="true" aria-label="Navigation"
               class="v-sidebar fixed inset-y-0 z-50 flex w-[var(--sidebar-w)] flex-col bg-v-sunken border-r border-v-border transition-transform duration-200">
            <div class="flex h-11 items-center px-4 border-b border-v-border">
                <a href="{{ route('admin.dashboard') }}" class="text-[15px] font-semibold text-v-text">{{ config('app.name') }}</a>
                <button class="ml-auto lg:hidden text-v-muted hover:text-v-text" @click="mobileNav = false" aria-label="Close navigation">
                    <x-admin.icon name="x" class="w-4 h-4" />
                </button>
            </div>
            <nav class="v-nav flex-1 overflow-y-auto px-2 py-2"
                 x-data="{ open: JSON.parse(localStorage.getItem('vNavOpen') || '{}'),
                           toggle(g) { this.open[g] = !this.isOpen(g); localStorage.setItem('vNavOpen', JSON.stringify(this.open)); },
                           isOpen(g) { return this.open[g] !== false; } }">
                @php $activeNav = \App\Support\AdminNav::activeItemKey(request()->path()); @endphp
                @foreach (\App\Support\AdminNav::groups(Auth::user()) as $group)
                    @php $active = $activeNav && $activeNav['group'] === $group['label']; @endphp
                    @if ($group['label'])
                        <button type="button" @click="toggle('{{ $group['label'] }}')"
                                class="mt-3 flex w-full items-center justify-between px-2 py-1 text-[10px] font-semibold uppercase tracking-widest text-v-muted hover:text-v-secondary">
                            {{ $group['label'] }}
                            <x-admin.icon name="chevron-down" class="w-3 h-3 transition-transform" x-bind:class="isOpen('{{ $group['label'] }}') || {{ $active ? 'true' : 'false' }} ? '' : '-rotate-90'" />
                        </button>
                    @endif
                    <div @if ($group['label']) x-show="isOpen('{{ $group['label'] }}') || {{ $active ? 'true' : 'false' }}" @endif class="space-y-0.5">
                        @foreach ($group['items'] as $item)
                            @php $on = $active && $activeNav['item'] === $item['key']; @endphp
                            <a href="{{ route($item['route']) }}"
                               class="flex items-center gap-2.5 rounded-v-md px-2 py-1.5 text-[13px] transition-colors
                                   {{ $on ? 'bg-v-accent-weak text-v-accent font-semibold' : 'text-v-secondary hover:bg-v-hover hover:text-v-text' }}">
                                <x-admin.icon :name="$item['icon']" class="w-[18px] h-[18px] shrink-0 {{ $on ? '' : 'opacity-60' }}" />
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </nav>
            <div class="border-t border-v-border p-3">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-v-accent-weak text-xs font-bold text-v-accent">
                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[13px] font-medium text-v-text">{{ Auth::user()->name }}</p>
                        <p class="truncate text-[11px] text-v-muted">{{ Auth::user()->role?->name ?? 'Admin' }}</p>
                    </div>
                    <a href="{{ route('admin.profile.edit') }}" class="text-v-muted hover:text-v-text" title="My profile">
                        <x-admin.icon name="customers" class="w-4 h-4" />
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-v-muted hover:text-v-text" title="Sign out">
                            <x-admin.icon name="logout" class="w-4 h-4" />
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        {{-- Main --}}
        <div class="min-w-0 flex-1 lg:ml-[var(--sidebar-w)]">
            <header class="sticky top-0 z-20 flex h-11 items-center gap-3 border-b border-v-border bg-v-canvas/80 px-4 backdrop-blur sm:px-6">
                <button class="lg:hidden text-v-secondary hover:text-v-text" @click="mobileNav = true" aria-label="Open navigation">
                    <x-admin.icon name="menu" class="w-5 h-5" />
                </button>
                <div class="min-w-0 flex-1" id="v-topbar-slot"></div>
                <button type="button" x-data @click="$dispatch('open-palette')"
                        class="hidden sm:inline-flex items-center gap-2 rounded-v-md border border-v-border bg-v-sunken px-2.5 py-1 text-xs text-v-muted hover:text-v-secondary">
                    <x-admin.icon name="search" class="w-3.5 h-3.5" /> Search <kbd class="font-vmono text-[10px]">⌘K</kbd>
                </button>
                <a href="{{ route('home') }}" target="_blank" class="hidden sm:inline-flex items-center gap-1.5 text-xs text-v-secondary hover:text-v-text">
                    <x-admin.icon name="external" class="w-3.5 h-3.5" /> View store
                </a>
                <x-admin.theme-toggle />
            </header>
            <main class="mx-auto max-w-[1400px] px-4 py-6 sm:px-6">
                {{ $slot }}
            </main>
        </div>
    </div>
    <x-admin.toasts />
    <x-admin.command-palette />
    @stack('scripts')
</body>
</html>
