@props(['variant' => 'primary', 'size' => 'md', 'href' => null, 'type' => 'button'])
@php
$base = 'v-btn inline-flex items-center justify-center gap-1.5 font-medium rounded-v-md transition-colors select-none disabled:opacity-50 disabled:pointer-events-none';
$sizes = [
    'md' => 'h-[var(--control-h)] px-3.5 text-[13px]',
    'sm' => 'h-[var(--control-h-sm)] px-2.5 text-xs',
];
$variants = [
    'primary'   => 'bg-v-accent text-v-accent-text hover:bg-v-accent-hover',
    'secondary' => 'bg-v-surface text-v-text border border-v-border hover:bg-v-hover',
    'danger'    => 'bg-v-danger text-v-danger-text hover:opacity-90',
    'ghost'     => 'text-v-secondary hover:text-v-text hover:bg-v-hover',
];
$sizeClass = $sizes[$size] ?? $sizes['md'];
$variantClass = $variants[$variant] ?? $variants['primary'];
$classes = "$base $sizeClass $variantClass";
@endphp
@if ($href)
<a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
<button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
    <svg class="v-btn-spinner hidden w-3.5 h-3.5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"/><path fill="currentColor" class="opacity-75" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
    {{ $slot }}
</button>
@endif
