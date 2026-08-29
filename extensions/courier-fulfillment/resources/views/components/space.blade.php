@props(['title' => 'Fulfillment', 'active' => null])
@php
    $courierItems = [];
    foreach (\Extensions\CourierFulfillment\CourierManager::all() as $spaceCourier) {
        $courierItems[] = [
            'key' => $spaceCourier->getKey(),
            'label' => $spaceCourier->getName(),
            'icon' => 'fulfillment',
            'url' => route('admin.fulfillment.settings.courier', $spaceCourier->getKey()),
        ];
    }
    $groups = [
        ['label' => 'Configuration', 'items' => [
            ['key' => 'settings', 'label' => 'Settings', 'icon' => 'settings', 'url' => route('admin.fulfillment.settings')],
            ['key' => 'status-mapping', 'label' => 'Status Mapping', 'icon' => 'layers', 'url' => route('admin.fulfillment.settings.placement')],
            ['key' => 'pickup-addresses', 'label' => 'Pickup Addresses', 'icon' => 'store', 'url' => route('admin.fulfillment.pickup-addresses.index')],
        ]],
        // The integrations, named as such. The store's courier directory
        // (Sales, Couriers) is deliberately NOT linked from here (owner
        // ruling 2026-08-29): a context-nav entry that leaves the space with
        // no way back reads as teleportation, and the queue already points
        // at the directory in the one place it matters, the manual card.
        ['label' => 'Integrations', 'items' => $courierItems],
    ];
@endphp
<x-admin.space-shell :title="$title" space="Fulfillment" :groups="$groups" :active="$active">
    {{ $slot }}
</x-admin.space-shell>
