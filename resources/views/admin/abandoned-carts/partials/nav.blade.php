{{--
    Abandoned Carts workspace nav — same sticky sidebar + mobile <select>
    pattern as the SEO workspace (resources/views/admin/extensions/seo/partials/nav-v2.blade.php),
    scoped locally since this workspace has no dedicated Nav registry. Not
    included on abandoned-carts/settings.blade.php (that page is the
    Settings-hub entry point, kept as a standalone destination) or on the
    rule-form/template-form/show pages (standalone forms/detail, matching
    the old view's lack of tab nav there too).
--}}
@php
    $acNavItems = [
        ['label' => 'General', 'route' => 'admin.abandoned-carts.settings', 'pattern' => 'admin.abandoned-carts.settings'],
        ['label' => 'Carts', 'route' => 'admin.abandoned-carts.index', 'pattern' => 'admin.abandoned-carts.index'],
        ['label' => 'Rules', 'route' => 'admin.abandoned-carts.rules', 'pattern' => 'admin.abandoned-carts.rules*'],
        ['label' => 'Templates', 'route' => 'admin.abandoned-carts.templates', 'pattern' => 'admin.abandoned-carts.templates*'],
        ['label' => 'Notifications', 'route' => 'admin.abandoned-carts.notifications', 'pattern' => 'admin.abandoned-carts.notifications'],
        ['label' => 'Recovered', 'route' => 'admin.abandoned-carts.recovered', 'pattern' => 'admin.abandoned-carts.recovered'],
    ];
@endphp
<nav class="hidden lg:block w-[13.5rem] shrink-0" aria-label="Abandoned carts workspace">
    <div class="sticky top-11 space-y-0.5">
        @foreach ($acNavItems as $item)
            <a href="{{ route($item['route']) }}"
               class="block rounded-v-md px-2 py-1 text-[13px] transition-colors
                   {{ request()->routeIs($item['pattern']) ? 'bg-v-accent-weak font-semibold text-v-accent' : 'text-v-secondary hover:bg-v-hover hover:text-v-text' }}">
                {{ $item['label'] }}
            </a>
        @endforeach
    </div>
</nav>
<select x-data @change="window.location.assign($event.target.value)"
        class="v-control lg:hidden mb-5 w-full h-[var(--control-h)] rounded-v-md border border-v-border bg-v-surface px-2.5 text-[13px] text-v-text" aria-label="Abandoned carts section">
    @foreach ($acNavItems as $item)
        <option value="{{ route($item['route']) }}" @selected(request()->routeIs($item['pattern']))>{{ $item['label'] }}</option>
    @endforeach
</select>
