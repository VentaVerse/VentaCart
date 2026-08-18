@props(['title' => 'Abandoned Carts', 'active' => null])
{{-- The module's own space (owner ruling 2026-08-19: extension menus are
     side pans, like Settings and SEO). --}}
@php
    $groups = [
        ['label' => 'Work', 'items' => [
            ['key' => 'carts', 'label' => 'Carts', 'icon' => 'cart', 'url' => route('admin.abandoned-carts.index')],
            ['key' => 'notifications', 'label' => 'Notifications', 'icon' => 'mail', 'url' => route('admin.abandoned-carts.notifications')],
            ['key' => 'recovered', 'label' => 'Recovered', 'icon' => 'check', 'url' => route('admin.abandoned-carts.recovered')],
        ]],
        ['label' => 'Configuration', 'items' => [
            ['key' => 'rules', 'label' => 'Rules', 'icon' => 'layers', 'url' => route('admin.abandoned-carts.rules')],
            ['key' => 'templates', 'label' => 'Templates', 'icon' => 'text', 'url' => route('admin.abandoned-carts.templates')],
            ['key' => 'settings', 'label' => 'Settings', 'icon' => 'settings', 'url' => route('admin.abandoned-carts.settings')],
        ]],
    ];
@endphp
<x-admin3.space-shell :title="$title" space="Abandoned Carts" :groups="$groups" :active="$active">
    {{ $slot }}
</x-admin3.space-shell>
