@php
    $vIcons = [
        'truck' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 7h11v8H3zM14 10h4l3 3v2h-7zM7 18a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM17 18a1.5 1.5 0 100-3 1.5 1.5 0 000 3z"/>',
        'shield' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3zM9 12l2 2 4-4"/>',
        'refresh' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M5 15a7 7 0 0012 2M19 9A7 7 0 007 7"/>',
        'chat' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v10H8l-4 4V5z"/>',
        'star' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3l2.7 5.6 6.2.9-4.5 4.3 1.1 6.2L12 17l-5.5 3 1.1-6.2L3.1 9.5l6.2-.9L12 3z"/>',
        'box' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 7l9-4 9 4v10l-9 4-9-4V7zM3 7l9 4 9-4M12 11v10"/>',
        'card' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18v12H3zM3 10h18M7 15h4"/>',
        'clock' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 100-18 9 9 0 000 18zM12 7v5l3 2"/>',
        'pin' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 21s-6-5.5-6-11a6 6 0 0112 0c0 5.5-6 11-6 11zM12 12a2 2 0 100-4 2 2 0 000 4z"/>',
        'gift' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 9h18v4H3zM5 13h14v8H5zM12 9v12M12 9c-2-4-6-4-6-1s4 1 6 1zM12 9c2-4 6-4 6-1s-4 1-6 1z"/>',
    ];
@endphp
@if (isset($vIcons[$name ?? '']))
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">{!! $vIcons[$name] !!}</svg>
@endif
