@php
    $config = $placement->config ?? [];
    $columns = max(2, min(4, (int) ($config['columns'] ?? 3)));
    $posts = $posts ?? collect();
@endphp
@if ($posts->isNotEmpty())
<section class="py-10 md:py-14">
    <div class="container-store">
        @if ($placement->title)
            <h2 class="section-title reveal">{{ $placement->title }}</h2>
        @endif
        <div class="wposts wposts--{{ $columns }} reveal-stagger">
            @foreach ($posts as $post)
                <a href="{{ route('blog.show', $post->slug) }}" class="wposts__card">
                    @if ($post->featured_image)
                        <img class="wposts__img" src="{{ thumb_url($post->featured_image) }}" alt="" loading="lazy" decoding="async">
                    @endif
                    <span class="wposts__body">
                        <b class="wposts__title">{{ $post->title }}</b>
                        @if ($post->published_at)
                            <time class="wposts__date" datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->format('M j, Y') }}</time>
                        @endif
                        @if ($post->excerpt)
                            <span class="wposts__excerpt">{{ \Illuminate\Support\Str::limit(strip_tags($post->excerpt), 120) }}</span>
                        @endif
                    </span>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif
