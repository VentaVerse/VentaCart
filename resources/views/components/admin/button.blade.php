{{-- The Blotter's control weight: a hairline edge on the sheet colour
     at the 11px control radius, 13px type. Elevation is spent on one
     thing only, the primary action, so a screen still has exactly one
     control that looks pressable from across the room.

     Danger is outlined here, not filled. Inline destructive controls
     sit among ordinary ones and must not outshout the page's primary
     action; the confirm dialog's own button is the destructive act and
     components/buttons.css fills it there. A page covered in solid red
     buttons teaches people to ignore red. --}}
@props(['variant' => 'primary', 'size' => 'md', 'href' => null, 'type' => 'button'])
@php
$base = 'v-btn inline-flex items-center justify-center gap-1.5 rounded-v-md border transition-colors select-none disabled:opacity-50 disabled:pointer-events-none';
$sizes = [
    'md' => 'h-[var(--control-h)] px-3.5 text-[13px]',
    'sm' => 'h-[var(--control-h-sm)] px-2.5 text-xs',
];
$variants = [
    'primary'   => 'v-btn--primary bg-v-accent border-v-accent text-v-accent-text shadow-v-lift hover:bg-v-accent-hover',
    'secondary' => 'v-btn--secondary bg-v-surface border-v-strong text-v-text hover:bg-v-hover',
    'danger'    => 'v-btn--danger bg-v-surface border-v-danger text-v-danger hover:bg-[var(--bl-bad-wash)]',
    'ghost'     => 'v-btn--ghost border-transparent bg-transparent text-v-secondary hover:border-v-strong hover:bg-v-surface hover:text-v-text',
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
