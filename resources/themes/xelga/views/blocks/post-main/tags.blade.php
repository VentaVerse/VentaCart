{{-- The post's tags, each a link to the posts that share it, and the way back to the blog. --}}
@if ($post['tags'] !== [] || $block['settings']['show_back'] || $context->preview)
    <footer class="article__foot" data-block-id="{{ $block['id'] }}" data-block-type="tags"
            data-show-back="{{ $block['settings']['show_back'] ? 'true' : 'false' }}" @if ($block['disabled']) hidden @endif>
        @if ($post['tags'] !== [])
            <h2 class="article__foot-title">Tags</h2>
            <ul class="tags">
                @foreach ($post['tags'] as $tag)
                    <li><a class="chip chip--sm" href="{{ $tag['url'] }}">{{ $tag['label'] }}</a></li>
                @endforeach
            </ul>
        @endif
        <a class="link-arrow article__back" href="{{ $data['blogUrl'] }}"><svg class="icon icon--sm" aria-hidden="true"><use href="#i-arrow-left"></use></svg><span>Back to the blog</span></a>
    </footer>
@endif
