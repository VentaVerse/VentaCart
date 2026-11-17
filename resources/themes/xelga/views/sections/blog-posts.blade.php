{{--
    Blog posts: the newest published posts from the blog extension. While
    the blog is switched off, or has nothing published, shoppers see nothing
    and the editor explains why.
--}}
@php $posts = $data['posts']; @endphp
@if ($posts !== [] || $context->preview)
    <section class="s {{ $section['class'] }} {{ $settings['scheme'] }}" @include('xelga::snippets.section-attrs')
             data-cols="{{ $settings['cols'] }}" data-show-date="{{ $settings['show_date'] ? 'true' : 'false' }}"
             data-show-excerpt="{{ $settings['show_excerpt'] ? 'true' : 'false' }}" data-head-align="{{ $settings['head_align'] }}"
             aria-labelledby="{{ $section['id'] }}-title">
        @include('xelga::snippets.section-bg')
        <div class="s__inner">
            @include('xelga::snippets.section-head', ['link' => $data['link']])
            @if (! $data['enabled'])
                @include('xelga::snippets.editor-note', ['note' => 'The blog is switched off, so shoppers do not see this section. Switch it on in VentaCart, Extensions.'])
            @elseif ($posts === [])
                @include('xelga::snippets.editor-note', ['note' => 'No published posts yet, so shoppers do not see this section.'])
            @else
                <ul class="posts">
                    @foreach ($posts as $post)
                        <li class="post">
                            @if ($post['picture'])
                                <div class="post__media"><img src="{{ $post['picture']['src'] }}" alt="" loading="lazy" decoding="async" {{ \Themes\Xelga\Rendering\Picture::sources($post['picture'], '(min-width: 990px) 33vw, (min-width: 750px) 50vw, 100vw') }} @if ($post['picture']['width']) width="{{ $post['picture']['width'] }}" height="{{ $post['picture']['height'] }}" @endif></div>
                            @endif
                            <div class="post__body">
                                @if ($post['date'] || $post['category'])
                                    <p class="post__meta">
                                        @if ($post['date'])<time class="post__date" datetime="{{ $post['date']->toDateString() }}">{{ $post['date']->format('j M Y') }}</time>@endif
                                        @if ($post['category'])<span class="post__category">{{ $post['category'] }}</span>@endif
                                    </p>
                                @endif
                                <h3 class="post__title"><a href="{{ $post['url'] }}">{{ $post['title'] }}</a></h3>
                                @if ($post['excerpt'] !== '')
                                    <p class="post__excerpt">{{ \Illuminate\Support\Str::limit($post['excerpt'], 220) }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
@endif
