{{-- One part of the store: what it is, whether it is on, and the
     way in.

     `href` makes the whole record the target rather than a 13px link
     at the bottom of it. A slate that carries controls of its own
     cannot also be a link, so it renders as a section and the
     controls are the targets.

     Slots: `state` is the badge or switch beside the name, `actions`
     the control set on the foot, `meta` the one quiet fact opposite
     them. An empty `meta` draws nothing, so a caller can pass a
     value that is sometimes blank without leaving a gap. --}}
@props([
    'title',
    'icon' => null,
    'number' => null,
    'href' => null,
    'note' => null,
])
@php
    $vTag = $href ? 'a' : 'section';
    $vMeta = isset($meta) ? trim($meta) : '';
    $vActs = isset($actions) ? trim($actions) : '';
@endphp
<{{ $vTag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'v-slate']) }}>
    @if ($icon)
        <span class="v-slate__mark"><x-admin.icon :name="$icon" class="w-[18px] h-[18px]" /></span>
    @elseif ($number !== null)
        <span class="v-slate__mark v-slate__mark--num">{{ $number }}</span>
    @endif
    <div class="v-slate__body">
        <div class="v-slate__head">
            <span class="v-slate__name">{{ $title }}</span>
            @isset($state){{ $state }}@endisset
        </div>
        @if ($note)<span class="v-slate__note">{{ $note }}</span>@endif
        {{ $slot }}
        @if ($vMeta !== '' || $vActs !== '')
            <div class="v-slate__foot">
                @if ($vMeta !== '')<span class="v-slate__meta">{!! $vMeta !!}</span>@endif
                @if ($vActs !== '')<div class="v-slate__acts">{!! $vActs !!}</div>@endif
            </div>
        @endif
    </div>
</{{ $vTag }}>
