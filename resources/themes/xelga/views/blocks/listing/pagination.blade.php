{{--
    More products: page numbers, or a Load more button that is a real link
    to the next page (the script fetches it and adds its cards in place).
--}}
@php $list = $data['list']; @endphp
@if ($list['last'] > 1)
    @if ($settings['pagination'] === 'load-more')
        <div class="load-more" data-load-more-wrap>
            <p class="listing__note muted" data-load-note>Showing {{ $list['to'] }} of {{ $list['total'] }}</p>
            @if ($list['next'])
                <a class="btn btn--secondary" href="{{ $list['next'] }}" data-load-more>Load more</a>
            @endif
        </div>
    @else
        <nav class="pagination" aria-label="Pages">
            @if ($list['prev'])
                <a class="icon-btn icon-btn--outline" href="{{ $list['prev'] }}" rel="prev" aria-label="Previous page"><svg class="icon" aria-hidden="true"><use href="#i-chevron-left"></use></svg></a>
            @else
                <span class="icon-btn icon-btn--outline" aria-disabled="true"><svg class="icon" aria-hidden="true"><use href="#i-chevron-left"></use></svg></span>
            @endif
            <ol class="pagination__list">
                @foreach ($list['pages'] as $page)
                    @if ($page['number'] === null)
                        <li><span class="pagination__gap" aria-hidden="true">...</span></li>
                    @else
                        <li><a class="pagination__link" href="{{ $page['url'] }}" @if ($page['current']) aria-current="page" @endif aria-label="Page {{ $page['number'] }}">{{ $page['number'] }}</a></li>
                    @endif
                @endforeach
            </ol>
            @if ($list['next'])
                <a class="icon-btn icon-btn--outline" href="{{ $list['next'] }}" rel="next" aria-label="Next page"><svg class="icon" aria-hidden="true"><use href="#i-chevron-right"></use></svg></a>
            @else
                <span class="icon-btn icon-btn--outline" aria-disabled="true"><svg class="icon" aria-hidden="true"><use href="#i-chevron-right"></use></svg></span>
            @endif
        </nav>
        <p class="listing__note muted">Showing {{ $list['from'] }} to {{ $list['to'] }} of {{ $list['total'] }} products</p>
    @endif
@endif
