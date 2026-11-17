{{-- The post's featured photo. --}}
@if ($post['picture'])
    <figure class="article__figure" data-block-id="{{ $block['id'] }}" data-block-type="image" @if ($block['disabled']) hidden @endif>
        <img src="{{ $post['picture']['src'] }}" alt="" {{ \Themes\Xelga\Rendering\Fold::attrs($context) }} {{ \Themes\Xelga\Rendering\Picture::sources($post['picture'], \Themes\Xelga\Rendering\Picture::contentSizes($context->span, '(min-width: 1000px) 960px, 100vw')) }} @if ($post['picture']['width']) width="{{ $post['picture']['width'] }}" height="{{ $post['picture']['height'] }}" @endif>
    </figure>
    @php \Themes\Xelga\Rendering\Fold::close(); @endphp
@elseif ($context->preview)
    <div data-block-id="{{ $block['id'] }}" data-block-type="image" @if ($block['disabled']) hidden @endif>
        @include('xelga::snippets.editor-note', ['note' => 'This post has no featured photo. Add one to the post in VentaCart, Blog.'])
    </div>
@endif
