{{-- The list of posts, the view switch and the pager. --}}
@php
    $total = $data['total'];
    $filtered = $data['search'] !== null || $data['category'] !== null || $data['tag'] !== null;
@endphp
<div class="blog-list" data-block-id="{{ $block['id'] }}" data-block-type="posts" @if ($block['disabled']) hidden @endif>
    <div class="blog-list__bar">
        <p class="blog-list__count" role="status">
            @if ($data['search'] !== null)
                {{ $total }} {{ \Illuminate\Support\Str::plural('post', $total) }} found for &ldquo;{{ $data['search'] }}&rdquo;.
            @elseif ($data['tag'] !== null)
                {{ $total }} {{ \Illuminate\Support\Str::plural('post', $total) }} tagged &ldquo;{{ $data['tag'] }}&rdquo;.
            @else
                {{ $total }} {{ \Illuminate\Support\Str::plural('post', $total) }}
            @endif
            @if ($filtered)
                <a class="text-btn" href="{{ $data['resetUrl'] }}">See every post</a>
            @endif
        </p>
        @if ($data['posts'] !== [])
            <div class="view-toggle" role="group" aria-label="Show posts as">
                <button type="button" class="icon-btn" data-view="grid" aria-pressed="{{ $data['variant'] === 'grid' ? 'true' : 'false' }}" aria-label="Grid"><svg class="icon" aria-hidden="true"><use href="#i-grid"></use></svg></button>
                <button type="button" class="icon-btn" data-view="list" aria-pressed="{{ $data['variant'] === 'list' ? 'true' : 'false' }}" aria-label="List"><svg class="icon" aria-hidden="true"><use href="#i-list"></use></svg></button>
            </div>
        @endif
    </div>
    @if ($data['posts'] === [])
        <div class="empty-note">
            @if ($filtered)
                <p class="empty-note__title">No posts match that yet.</p>
                <p class="muted">Try another word, or <a href="{{ $data['resetUrl'] }}">see every post</a>.</p>
            @else
                <p class="empty-note__title" data-setting="empty_text">{{ $block['settings']['empty_text'] }}</p>
            @endif
        </div>
    @else
        <ul class="posts blog-posts">
            @foreach ($data['posts'] as $post)
                <li class="post">
                    @if ($post['picture'])
                        <div class="post__media"><img src="{{ $post['picture']['src'] }}" alt="" loading="lazy" decoding="async" @if ($post['picture']['width']) width="{{ $post['picture']['width'] }}" height="{{ $post['picture']['height'] }}" @endif></div>
                    @endif
                    <div class="post__body">
                        @if ($post['date'] || $post['category'])
                            <p class="post__meta">
                                @if ($post['date'])<time class="post__date" datetime="{{ $post['date']->toDateString() }}">{{ $post['date']->format('j M Y') }}</time>@endif
                                @if ($post['category'])<span class="post__category">{{ $post['category'] }}</span>@endif
                            </p>
                        @endif
                        <h2 class="post__title"><a href="{{ $post['url'] }}">{{ $post['title'] }}</a></h2>
                        @if ($post['excerpt'] !== '')
                            <p class="post__excerpt">{{ \Illuminate\Support\Str::limit($post['excerpt'], 260) }}</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
    @if ($data['pager'])
        <nav class="pager" aria-label="Pages of posts">
            @if ($data['pager']['prev'])
                <a class="pager__btn" href="{{ $data['pager']['prev'] }}" rel="prev"><svg class="icon icon--sm" aria-hidden="true"><use href="#i-chevron-left"></use></svg><span>Newer</span></a>
            @else
                <span class="pager__btn is-disabled" aria-disabled="true"><svg class="icon icon--sm" aria-hidden="true"><use href="#i-chevron-left"></use></svg><span>Newer</span></span>
            @endif
            <ol class="pager__pages">
                @foreach ($data['pager']['pages'] as $page)
                    @if ($page['number'] === null)
                        <li><span class="pager__gap" aria-hidden="true">&hellip;</span></li>
                    @else
                        <li><a class="pager__page" href="{{ $page['url'] }}" @if ($page['current']) aria-current="page" @endif><span class="visually-hidden">Page </span>{{ $page['number'] }}</a></li>
                    @endif
                @endforeach
            </ol>
            @if ($data['pager']['next'])
                <a class="pager__btn" href="{{ $data['pager']['next'] }}" rel="next"><span>Older</span><svg class="icon icon--sm" aria-hidden="true"><use href="#i-chevron-right"></use></svg></a>
            @else
                <span class="pager__btn is-disabled" aria-disabled="true"><span>Older</span><svg class="icon icon--sm" aria-hidden="true"><use href="#i-chevron-right"></use></svg></span>
            @endif
        </nav>
    @endif
</div>
