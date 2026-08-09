@props(['label', 'value', 'tone' => 'default'])
@php
$tones = ['default' => 'text-v-text', 'accent' => 'text-v-accent', 'warning' => 'text-v-warning', 'danger' => 'text-v-danger'];
@endphp
<div {{ $attributes->merge(['class' => 'bg-v-surface border border-v-border rounded-v-lg px-4 py-3']) }}>
    <p class="v-label">{{ $label }}</p>
    <p class="v-num mt-1 text-xl font-medium {{ $tones[$tone] ?? $tones['default'] }}">{{ $value }}</p>
</div>
