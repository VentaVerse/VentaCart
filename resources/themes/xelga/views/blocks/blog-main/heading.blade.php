{{-- The blog's heading and the line under it. --}}
<header class="page-head" data-block-id="{{ $block['id'] }}" data-block-type="heading" @if ($block['disabled']) hidden @endif>
    <h1 class="page-head__title" id="{{ $section['id'] }}-title" data-setting="heading">{{ $block['settings']['heading'] !== '' ? $block['settings']['heading'] : 'Blog' }}</h1>
    <p class="page-head__text" data-setting="text" data-optional @if ($block['settings']['text'] === '') hidden @endif>{{ $block['settings']['text'] }}</p>
</header>
