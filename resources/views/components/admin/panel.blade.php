{{-- A raised sheet with a ruled head naming what it holds. The
     admin's one container.

     `id` names the heading, and the section points at it, so a panel
     is announced by its own title without the caller writing the
     relationship twice.

     `flush` is for a body that reaches the panel's edge: a table, a
     list of rows, a feed. Anything else takes the panel's gutter. --}}
@props([
    'title' => null,
    'id' => null,
    'note' => null,
    'link' => null,
    'linkLabel' => null,
    'flush' => false,
])
<section {{ $attributes->merge(['class' => 'v-panel']) }} @if ($id) aria-labelledby="{{ $id }}" @endif>
    @if ($title || $note || isset($head))
        <div class="v-panel__head">
            @if ($title)<h2 class="v-subhead" @if ($id) id="{{ $id }}" @endif>{{ $title }}</h2>@endif
            @if ($note)<span class="v-panel__note">{{ $note }}</span>@endif
            @isset($head){{ $head }}@endisset
            @if ($link)<a href="{{ $link }}" class="v-panel__link">{{ $linkLabel }}</a>@endif
        </div>
    @endif

    @if ($flush)
        {{ $slot }}
    @else
        <div class="v-panel__body">{{ $slot }}</div>
    @endif

    @isset($foot)<div class="v-panel__foot">{{ $foot }}</div>@endisset
</section>
