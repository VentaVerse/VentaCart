{{-- A square mark and a coloured word on an uncoloured background.

     The tinted capsule this replaces was the admin-template
     signature: forty rounded blobs of 12%-opacity colour down one
     table until the page read as confetti. Here the status is an
     ordinary word set in its own state colour, with a small square
     beside it, so a column of statuses reads as a column of words
     and the marks align.

     Colour never carries the meaning alone: the word is always
     printed, and `dot="false"` drops only the mark, never the word.

     Every existing caller keeps working: the sole prop is still
     `tone`, and the same five tone names resolve. --}}
@props(['tone' => 'neutral', 'dot' => true])
@php
$tones = ['neutral', 'success', 'warning', 'danger', 'info', 'accent'];
$toneClass = in_array($tone, $tones, true) ? $tone : 'neutral';
@endphp
<span {{ $attributes->merge(['class' => "v-badge v-badge--{$toneClass}"]) }}>
    @if ($dot)<span class="v-badge__dot" aria-hidden="true"></span>@endif
    <span class="v-badge__label">{{ $slot }}</span>
</span>
