{{-- A question mark that explains the control beside it.

     Venta's rule is that the interface carries no instructional prose: a
     control names its own action and a screen of controls stays a screen of
     controls. This is the one exception, for a consequence that is genuinely
     not obvious from the control's name, and even then the sentence is kept
     off the page until it is asked for.

     Shows on hover, on keyboard focus, and on tap: tapping the button
     focuses it, which is the same state hover produces, so one CSS rule
     covers all three and no JavaScript is involved.

     The bubble stays in the accessibility tree at all times through
     aria-describedby rather than being hidden, so a screen reader announces
     the explanation when it reaches the button. It is only visually
     withheld.

     Above 900px the bubble opens down and to the right. For a hint near the
     right edge of a wide screen add class="v-hint--end" to open it the other
     way. Below 900px it is a sheet across the bottom and the class does
     nothing.

     Usage:
         <x-admin.hint>Cancelling here also voids the waybill.</x-admin.hint>
         <x-admin.hint label="About reserved stock">...</x-admin.hint>
--}}
@props(['label' => 'More information'])

@php
    // Unique per render: two hints on a page must not share a description
    // id, or aria-describedby resolves both buttons to the first bubble.
    $hintId = 'hint-' . \Illuminate\Support\Str::random(10);
@endphp

<span {{ $attributes->merge(['class' => 'v-hint']) }}>
    <button type="button" class="v-hint__btn" aria-label="{{ $label }}" aria-describedby="{{ $hintId }}">
        <span aria-hidden="true">?</span>
    </button>
    <span class="v-hint__bubble" role="tooltip" id="{{ $hintId }}">{{ $slot }}</span>
</span>
