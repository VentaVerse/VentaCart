{{-- The row of controls above a list. Bottom-aligned, so every child
     ending in a --control-h box shares a baseline whether or not it
     carries a label above it.

     A bare row rather than a sheet: a full-width raised panel holding
     two controls reads as a section whose contents failed to load. --}}
@props([])
<div {{ $attributes->merge(['class' => 'v-toolbar']) }}>
    <div class="v-toolbar__row">{{ $slot }}</div>
</div>
