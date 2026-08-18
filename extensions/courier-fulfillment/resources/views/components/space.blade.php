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
        ['label' => 'Couriers', 'items' => $courierItems],
    ];
@endphp
<x-admin3.space-shell :title="$title" space="Fulfillment" :groups="$groups" :active="$active">
    {{ $slot }}
</x-admin3.space-shell>
