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
<div class="mb-5">
    <x-admin.segment label="Abandoned cart settings">
        @foreach ($acSettingsNavItems as $item)
            <x-admin.segment.item :href="route($item['route'])" :active="request()->routeIs($item['pattern'])">{{ $item['label'] }}</x-admin.segment.item>
        @endforeach
        <x-slot:aside>
            <a class="v-segbar__aside" href="{{ route('admin.abandoned-carts.index') }}">Carts workspace <x-admin.icon name="chevron-right" class="w-3 h-3" /></a>
        </x-slot:aside>
    </x-admin.segment>
</div>
