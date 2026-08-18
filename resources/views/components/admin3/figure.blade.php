{{-- A filled measure block: the handful of figures a page is opened
     to read from across the room. Ported whole from the v2 layer when
     it retired; the anatomy and the accent rule are unchanged.

     `accent` is IDENTITY, never state. It says which measure you are
     reading, so a figure keeps its hue whether the news is good or
     bad, and the written label is beside it in every case. Nothing
     here goes red because it is low. The six are cyan, violet, amber,
     green, rose and indigo.

     `mark` is the currency symbol. It travels with its digits and is
     set smaller, so a seven figure total still reads as a number
     rather than as a symbol with a number after it. --}}
@props([
    'label',
    'value',
    'note' => null,
    'accent' => 'cyan',
    'icon' => null,
    'href' => null,
    'mark' => null,
])
@php
$accents = ['cyan', 'violet', 'amber', 'green', 'rose', 'indigo'];
$tone = in_array($accent, $accents, true) ? $accent : 'cyan';
$tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => "v3-figure v3-figure--{$tone}"]) }}>
    <span class="v3-figure__top">
        <span class="v3-figure__k">{{ $label }}</span>
        @if ($icon)<span class="v3-figure__ic"><x-admin3.icon :name="$icon" /></span>@endif
    </span>
    <span class="v3-figure__v v3-num">@if ($mark)<span class="v3-figure__mark">{{ $mark }}</span>@endif{{ $value }}</span>
    @if ($note)<span class="v3-figure__d">{{ $note }}</span>@endif
</{{ $tag }}>
