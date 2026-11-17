{{-- The post's category, date, title and summary. --}}
@php $s = $block['settings']; @endphp
<header class="article__head" data-block-id="{{ $block['id'] }}" data-block-type="title"
        data-show-date="{{ $s['show_date'] ? 'true' : 'false' }}" data-show-category="{{ $s['show_category'] ? 'true' : 'false' }}"
        data-show-excerpt="{{ $s['show_excerpt'] ? 'true' : 'false' }}" @if ($block['disabled']) hidden @endif>
    @if ($post['category'] || $post['date'])
        <p class="article__meta">
            @if ($post['category'])<a class="article__cat" href="{{ $post['category']['url'] }}">{{ $post['category']['label'] }}</a>@endif
            @if ($post['date'])<time datetime="{{ $post['date']->toDateString() }}">{{ $post['date']->format('j M Y') }}</time>@endif
        </p>
    @endif
    <h1 class="article__title" id="{{ $section['id'] }}-title">{{ $post['title'] }}</h1>
    @if ($post['excerpt'] !== '')
        <p class="article__lede" data-part="excerpt">{{ $post['excerpt'] }}</p>
    @endif
</header>
