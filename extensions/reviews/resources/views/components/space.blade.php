@props(['title' => 'Reviews', 'active' => null, 'counts' => null])
@php
    $groups = [
        ['label' => 'Queue', 'items' => [
            ['key' => 'pending', 'label' => 'Pending', 'icon' => 'clock', 'url' => route('admin.reviews.index', ['tab' => 'pending']), 'count' => $counts['pending'] ?? null],
            ['key' => 'approved', 'label' => 'Approved', 'icon' => 'check', 'url' => route('admin.reviews.index', ['tab' => 'approved']), 'count' => $counts['approved'] ?? null],
            ['key' => 'rejected', 'label' => 'Rejected', 'icon' => 'x', 'url' => route('admin.reviews.index', ['tab' => 'rejected']), 'count' => $counts['rejected'] ?? null],
        ]],
        ['label' => 'Configuration', 'items' => [
            ['key' => 'settings', 'label' => 'Settings', 'icon' => 'settings', 'url' => route('admin.reviews.settings')],
        ]],
    ];
@endphp
<x-admin.space-shell :title="$title" space="Reviews" :groups="$groups" :active="$active">
    {{ $slot }}
</x-admin.space-shell>
