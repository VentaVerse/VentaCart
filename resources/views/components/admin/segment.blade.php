{{-- The trough control: a row of pills stating which view of a list
     you are looking at. Ported from the v2 layer with its anatomy
     intact (inline / sub / aside). --}}
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
