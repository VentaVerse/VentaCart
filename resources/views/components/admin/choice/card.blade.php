{{-- One option.

     Four states, and the state is always a WORD (`state`), never grey
     alone:

       available        picked by clicking it
       not configured   `:available="false"` + the reason in the slot
       not serviceable  the same, with what the other side said
       could not check  `state` on an AVAILABLE card, which greys it
                        while leaving it clickable

     An option that cannot be picked renders as a <div>, never as a
     disabled <button>: it structurally cannot advance, rather than
     merely being told not to. It stays on the list instead of
     vanishing, because an option somebody switched on and then cannot
     find reads as a bug.

     The fourth state is the deliberate opposite: something is wrong,
     but nothing has refused, so the card is greyed AND still a real
     button.

     `mode` is a qualifier that changes what picking this means: a
     sandbox, a staging key, a non-production endpoint. `fee` is what
     it will cost, when the option can say. Both take a slot as well
     as a string, for a value only the browser knows. --}}
@props(['name', 'available' => true, 'mode' => null, 'fee' => null, 'state' => null])
@if ($available)
    <button type="button" {{ $attributes->merge(['class' => 'v-choice__card' . ($state ? ' v-choice__card--caution' : '')]) }}>
        <span class="v-choice__body">
            <span class="v-choice__head">
                <span class="v-choice__name">{{ $name }}</span>
                <x-admin.icon name="chevron-right" class="w-4 h-4 v-choice__go" />
            </span>
            @if ($mode || $state)
                <span class="v-choice__tags">
                    @if ($mode)<span class="v-choice__mode">{{ $mode }}</span>@endif
                    @if ($state)<span class="v-choice__state">{{ $state }}</span>@endif
                </span>
            @endif
            @if ($fee)<span class="v-choice__fee">{{ $fee }}</span>@endif
            @if (!$slot->isEmpty())<span class="v-choice__msg">{{ $slot }}</span>@endif
        </span>
    </button>
@else
    {{-- Divs rather than the spans the button branch is limited to:
         this branch is not a button, so its message may carry flow
         content (the settings link, a disclosure holding the other
         side's own words). --}}
    <div {{ $attributes->merge(['class' => 'v-choice__card v-choice__card--off']) }}>
        <div class="v-choice__body">
            <div class="v-choice__head">
                <span class="v-choice__name">{{ $name }}</span>
            </div>
            @if ($mode || $state)
                <div class="v-choice__tags">
                    @if ($mode)<span class="v-choice__mode">{{ $mode }}</span>@endif
                    @if ($state)<span class="v-choice__state">{{ $state }}</span>@endif
                </div>
            @endif
            @if ($fee)<span class="v-choice__fee">{{ $fee }}</span>@endif
            @if (!$slot->isEmpty())<div class="v-choice__msg">{{ $slot }}</div>@endif
        </div>
    </div>
@endif
