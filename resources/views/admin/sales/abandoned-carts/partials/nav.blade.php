{{--
    Abandoned Carts workspace nav — Sales-context pages only (Carts,
    Notifications, Recovered). Same sticky sidebar + mobile <select> pattern
    as the SEO workspace (marketing/seo/partials/nav-v2.blade.php), scoped
    locally since this workspace has no dedicated Nav registry.

    Rules/Templates/Settings used to be full items here too, which was the
    "menus bring me to somewhere else" bug in reverse: a Sales-workspace page
    shouldn't hand out a whole foreign (Settings) submenu. The single
    "Settings →" link below is the one explicit, singular crossing into that
    context — mirroring the SEO workspace's single "Engine settings" item.
    Not included on rule-form/template-form/show pages (standalone
    forms/detail, matching the old view's lack of tab nav there too), nor on
    the settings/modules/abandoned-carts/* pages (those use their own local
    settings nav — settings/modules/abandoned-carts/partials/nav.blade.php).
--}}
@php
    $acNavItems = [
        ['label' => 'Carts', 'route' => 'admin.abandoned-carts.index', 'pattern' => 'admin.abandoned-carts.index'],
        ['label' => 'Notifications', 'route' => 'admin.abandoned-carts.notifications', 'pattern' => 'admin.abandoned-carts.notifications'],
        ['label' => 'Recovered', 'route' => 'admin.abandoned-carts.recovered', 'pattern' => 'admin.abandoned-carts.recovered'],
    ];
@endphp
<nav class="hidden lg:block w-[13.5rem] shrink-0" aria-label="Abandoned carts workspace">
    <div class="sticky top-11 space-y-4">
        <div class="space-y-0.5">
            @foreach ($acNavItems as $item)
                <a href="{{ route($item['route']) }}"
                   class="block rounded-v-md px-2 py-1 text-[13px] transition-colors
                       {{ request()->routeIs($item['pattern']) ? 'bg-v-accent-weak font-semibold text-v-accent' : 'text-v-secondary hover:bg-v-hover hover:text-v-text' }}">
                    {{ $item['label'] }}
                </a>
            @endforeach
        </div>
        <div class="border-t border-v-border pt-3">
            <a href="{{ route('admin.abandoned-carts.settings') }}"
               class="flex items-center justify-between gap-1.5 rounded-v-md px-2 py-1 text-[13px] text-v-secondary hover:bg-v-hover hover:text-v-text">
                Settings <x-admin.icon name="chevron-right" class="h-3 w-3" />
            </a>
        </div>
    </div>
</nav>
<select x-data @change="window.location.assign($event.target.value)"
        class="v-control lg:hidden mb-5 w-full h-[var(--control-h)] rounded-v-md border border-v-border bg-v-surface px-2.5 text-[13px] text-v-text" aria-label="Abandoned carts section">
    @foreach ($acNavItems as $item)
        <option value="{{ route($item['route']) }}" @selected(request()->routeIs($item['pattern']))>{{ $item['label'] }}</option>
    @endforeach
    <option value="{{ route('admin.abandoned-carts.settings') }}" @selected(request()->routeIs('admin.abandoned-carts.settings'))>Settings</option>
</select>
