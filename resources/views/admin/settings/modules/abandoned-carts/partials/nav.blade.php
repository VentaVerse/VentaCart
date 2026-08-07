{{--
    Local settings nav for the Abandoned Carts settings group — the 3 pages
    mounted under settings/modules/abandoned-carts (Settings, Rules,
    Templates). These are cross-ROUTE tabs (three separate controller
    actions/views), not same-page panels, so x-admin.tabs (Alpine
    query-param panels within one route — see payments' Configuration |
    Availability tabs) doesn't fit. This is plain <a> links styled like that
    same tab strip.

    Deliberately does NOT pull in the Sales-workspace abandoned-carts nav
    (Carts/Notifications/Recovered) — that was the "menus bring me to the
    Main Nav" bug: a Settings-context page showing a whole foreign workspace
    menu. The one explicit, singular crossing back into that workspace is
    the "Carts workspace →" link on the right.
--}}
@php
    $acSettingsNavItems = [
        ['label' => 'Settings', 'route' => 'admin.abandoned-carts.settings', 'pattern' => 'admin.abandoned-carts.settings'],
        ['label' => 'Rules', 'route' => 'admin.abandoned-carts.rules', 'pattern' => 'admin.abandoned-carts.rules*'],
        ['label' => 'Templates', 'route' => 'admin.abandoned-carts.templates', 'pattern' => 'admin.abandoned-carts.templates*'],
    ];
@endphp
<div class="mb-5 flex items-center gap-1 overflow-x-auto border-b border-v-border" role="tablist" aria-label="Abandoned cart settings">
    @foreach ($acSettingsNavItems as $item)
        <a href="{{ route($item['route']) }}" role="tab" aria-selected="{{ request()->routeIs($item['pattern']) ? 'true' : 'false' }}"
           class="-mb-px shrink-0 whitespace-nowrap border-b-2 px-3 py-2 text-[13px] font-medium transition-colors
               {{ request()->routeIs($item['pattern']) ? 'border-v-accent text-v-accent' : 'border-transparent text-v-secondary hover:text-v-text' }}">
            {{ $item['label'] }}
        </a>
    @endforeach
    <a href="{{ route('admin.abandoned-carts.index') }}"
       class="ml-auto shrink-0 whitespace-nowrap px-3 py-2 text-[13px] text-v-secondary hover:text-v-text hover:underline">
        Carts workspace &rarr;
    </a>
</div>
