{{--
    Abandoned Carts workspace nav — Sales-context pages only (Carts,
    Notifications, Recovered).

    The trough, not a rail. Three destinations naming which view of one list
    you are looking at is exactly what the segment bar is, and the bar
    scrolls its own options at narrow widths, so the duplicate mobile
    <select> the sidebar needed is gone. The Marketing/SEO workspace still
    runs the sticky-sidebar version of this pattern; it converts with the
    marketing region.

    Rules/Templates/Settings used to be full items here too, which was the
    "menus bring me to somewhere else" bug in reverse: a Sales-workspace page
    shouldn't hand out a whole foreign (Settings) submenu. The single
    "Settings" link below is the one explicit, singular crossing into that
    context, and it rides the bar's aside, which is the slot for a sibling
    destination rather than one more option.

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
<x-admin.segment label="Abandoned carts workspace">
    @foreach ($acNavItems as $item)
        <x-admin.segment.item :href="route($item['route'])" :active="request()->routeIs($item['pattern'])">{{ $item['label'] }}</x-admin.segment.item>
    @endforeach
    <x-slot:aside>
        <a class="v-segbar__aside" href="{{ route('admin.abandoned-carts.settings') }}">Settings <x-admin.icon name="chevron-right" class="w-3 h-3" /></a>
    </x-slot:aside>
</x-admin.segment>
