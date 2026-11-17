{{-- The message: what happened and what to do next. --}}
<header class="nf__head" data-block-id="{{ $block['id'] }}" data-block-type="heading" @if ($block['disabled']) hidden @endif>
    <p class="nf__code" aria-hidden="true">404</p>
    <h1 class="nf__title" id="{{ $section['id'] }}-title" data-setting="heading">{{ $block['settings']['heading'] !== '' ? $block['settings']['heading'] : 'Page not found' }}</h1>
    <p class="nf__text" data-setting="text" data-optional @if ($block['settings']['text'] === '') hidden @endif>{{ $block['settings']['text'] }}</p>
</header>
