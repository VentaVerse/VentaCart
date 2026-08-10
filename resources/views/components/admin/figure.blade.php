{{-- A filled measure block: the handful of figures a page is opened
     to read from across the room.

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
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => "v-stat v-stat--{$tone}"]) }}>
    <span class="v-stat__top">
        <span class="v-stat__k">{{ $label }}</span>
        @if ($icon)<span class="v-stat__ic"><x-admin.icon :name="$icon" class="w-[18px] h-[18px]" /></span>@endif
    </span>
    <span class="v-stat__v v-num">@if ($mark)<span class="v-stat__mark">{{ $mark }}</span>@endif{{ $value }}</span>
    @if ($note)<span class="v-stat__d">{{ $note }}</span>@endif
</{{ $tag }}>
