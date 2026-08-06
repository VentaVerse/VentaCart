@props(['tone' => 'neutral'])
@php
$tones = [
    'neutral' => 'bg-v-sunken text-v-secondary',
    'success' => 'bg-v-accent-weak text-v-accent',
    'warning' => 'bg-[color-mix(in_srgb,var(--warning)_12%,transparent)] text-v-warning',
    'danger'  => 'bg-[color-mix(in_srgb,var(--danger)_12%,transparent)] text-v-danger',
    'info'    => 'bg-[color-mix(in_srgb,var(--info)_12%,transparent)] text-v-info',
];
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold {$tones[$tone]}"]) }}>{{ $slot }}</span>
