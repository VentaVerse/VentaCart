@if ($data['rating']['count'] > 0)
    <a class="pinfo__rating" href="#reviews" data-block-id="{{ $block['id'] }}" data-block-type="rating" @if ($block['disabled']) hidden @endif>
        @include('xelga::snippets.stars', ['rating' => $data['rating']['average']])
        @if ($block['settings']['show_count'] ?? true)
            <span>{{ $data['rating']['count'] }} {{ $data['rating']['count'] === 1 ? 'review' : 'reviews' }}</span>
        @endif
    </a>
@elseif ($context->preview)
    <p class="pinfo__rating" data-block-id="{{ $block['id'] }}" data-block-type="rating" hidden></p>
@endif
