{{-- The page's title. --}}
<header class="page-head info__head" data-block-id="{{ $block['id'] }}" data-block-type="title" @if ($block['disabled']) hidden @endif>
    <h1 class="page-head__title" id="{{ $section['id'] }}-title">{{ $page['title'] }}</h1>
</header>
