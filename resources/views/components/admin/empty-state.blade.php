{{-- The Blotter's proportions: room, then a 15px title, then one
     line capped at 46ch so it never runs the width of a wide table,
     then the action set apart below.

     The `icon` prop is still accepted so no caller needs editing,
     but nothing draws it any more. An outlined pictogram in a grey
     circle above a heading is the stock-template empty state, and it
     says nothing the title does not already say. --}}
@props(['icon' => null, 'title', 'description' => null])
<div {{ $attributes->merge(['class' => 'v-empty']) }}>
    <p class="v-empty__title">{{ $title }}</p>
    @if ($description)<p class="v-empty__desc">{{ $description }}</p>@endif
    @if (trim($slot))<div class="v-empty__action">{{ $slot }}</div>@endif
</div>
