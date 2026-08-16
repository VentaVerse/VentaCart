@props(['text'])
{{-- The question mark that holds a field's explanation. Hover or
     keyboard focus reveals it; the page itself stays quiet. --}}
{{-- onclick guard: inside a <label> a click would otherwise
     activate the control (toggling toggles). Reading is not acting. --}}
<span class="v3-help" tabindex="0" aria-label="{{ $text }}" onclick="event.preventDefault(); event.stopPropagation()">
    <span aria-hidden="true">?</span>
    <span class="v3-help__tip" role="tooltip">{{ $text }}</span>
</span>
