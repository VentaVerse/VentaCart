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
            // A pointer, not a page of this module: the courier directory is
            // core (Sales, Couriers) and the queue's manual shipment reads
            // it. Reachable from here because this is where an operator
            // looks for it.
            ['key' => 'couriers', 'label' => 'Couriers', 'icon' => 'package', 'url' => route('admin.shipping-couriers.index')],
        ]],
        // The integrations, named as such: "Couriers" is the pointer above,
        // the store's directory of who carries parcels, and two entries in
        // one nav wearing the same word would send an operator to the wrong
        // one every other time.
        ['label' => 'Integrations', 'items' => $courierItems],
    ];
@endphp
<x-admin.space-shell :title="$title" space="Fulfillment" :groups="$groups" :active="$active">
    {{ $slot }}
</x-admin.space-shell>
