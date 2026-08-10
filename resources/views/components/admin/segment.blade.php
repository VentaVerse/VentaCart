{{-- The trough control: a row of pills stating which view of a list
     you are looking at.

     `inline` is the bar that stands alone and takes only the width
     its options need. Without it the bar spans its row and can carry
     an `aside`, which is a sibling DESTINATION on the far side, never
     one more option.

     `sub` is the second tier: same anatomy, no trough, so a split
     inside an option reads as nested under it rather than as a second
     bar of equal weight. --}}
@props(['label' => null, 'sub' => false, 'inline' => false])
@php
$nav = 'v-segment' . ($sub ? ' v-segment--sub' : '') . ($inline ? ' v-segment--inline' : '');
@endphp
@if ($inline)
    <nav {{ $attributes->merge(['class' => $nav]) }} @if ($label) aria-label="{{ $label }}" @endif>{{ $slot }}</nav>
@else
    <div class="v-segbar {{ $sub ? 'v-segbar--sub' : '' }}">
        <nav {{ $attributes->merge(['class' => $nav]) }} @if ($label) aria-label="{{ $label }}" @endif>{{ $slot }}</nav>
        @isset($aside){{ $aside }}@endisset
    </div>
@endif
