{{-- Nothing here, in two shapes.

     `empty` (the default) is the Blotter's proportions: room, then a
     15px title, then one line capped at 46ch so it never runs the
     width of a wide table, then the action set apart below. It stands
     IN PLACE OF the thing that is missing.

     `notice` is the blocking setup state: a band above the thing it
     is blocking, warning-toned, carrying its own way out. It is
     louder on purpose. A screen that cannot work yet has to say so
     where the work would have been.

     The `icon` prop is still accepted so no caller needs editing,
     but nothing draws it any more. An outlined pictogram in a grey
     circle above a heading is the stock-template empty state, and it
     says nothing the title does not already say. --}}
@props(['icon' => null, 'title', 'description' => null, 'variant' => 'empty'])
@if ($variant === 'notice')
    <div {{ $attributes->merge(['class' => 'v-notice']) }}>
        <div class="v-notice__body">
            <p class="v-notice__title">{{ $title }}</p>
            @if ($description)<p class="v-notice__text">{{ $description }}</p>@endif
        </div>
        @if (trim($slot)){{ $slot }}@endif
    </div>
@else
    <div {{ $attributes->merge(['class' => 'v-empty']) }}>
        <p class="v-empty__title">{{ $title }}</p>
        @if ($description)<p class="v-empty__desc">{{ $description }}</p>@endif
        @if (trim($slot))<div class="v-empty__action">{{ $slot }}</div>@endif
    </div>
@endif
