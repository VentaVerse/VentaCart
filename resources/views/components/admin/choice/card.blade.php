{{-- One option.

     An option that cannot be picked yet renders as a <div>, never as
     a disabled <button>: it structurally cannot advance, rather than
     merely being told not to. It stays on the list instead of
     vanishing, because an option somebody switched on and then cannot
     find reads as a bug.

     `mode` is a qualifier that changes what picking this means: a
     sandbox, a staging key, a non-production endpoint. `fee` is what
     it will cost, when the option can say. --}}
@props(['name', 'available' => true, 'mode' => null, 'fee' => null, 'state' => null])
@if ($available)
    <button type="button" {{ $attributes->merge(['class' => 'v-choice__card']) }}>
        <span>
            <span class="v-choice__name">{{ $name }}</span>
            @if ($mode)<span class="v-choice__mode">{{ $mode }}</span>@endif
            @if ($fee)<span class="v-choice__fee">{{ $fee }}</span>@endif
        </span>
        <x-admin.icon name="chevron-right" class="w-4 h-4 v-choice__go" />
    </button>
@else
    <div {{ $attributes->merge(['class' => 'v-choice__card v-choice__card--off']) }}>
        <span class="v-choice__offbody">
            <span class="v-choice__offhead">
                <span class="v-choice__name">{{ $name }}</span>
                @if ($state)<span class="v-choice__state">{{ $state }}</span>@endif
            </span>
            <span class="v-choice__offmsg">{{ $slot }}</span>
        </span>
    </div>
@endif
