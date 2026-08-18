{{-- One option in a segment bar. A real link carrying its own query,
     so the control works with JavaScript off; a picker that swaps the
     view client-side intercepts it and toggles `is-active`. --}}
@props(['href' => null, 'active' => false, 'count' => null, 'countKey' => null])
<a @if ($href) href="{{ $href }}" @endif
   {{ $attributes->merge(['class' => 'v3-segment__item' . ($active ? ' is-active' : '')]) }}
   @if ($active) aria-current="page" @endif>
    <span>{{ $slot }}</span>
    @if ($count !== null)<span class="v3-segment__count"@if ($countKey) data-count-for="{{ $countKey }}"@endif>{{ $count }}</span>@endif
</a>
