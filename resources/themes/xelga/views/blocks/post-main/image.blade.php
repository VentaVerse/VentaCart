{{-- The post's featured photo. --}}
@if ($post['picture'])
    <figure class="article__figure" data-block-id="{{ $block['id'] }}" data-block-type="image" @if ($block['disabled']) hidden @endif>
        <img src="{{ $post['picture']['src'] }}" alt="" fetchpriority="high" decoding="async" @if ($post['picture']['width']) width="{{ $post['picture']['width'] }}" height="{{ $post['picture']['height'] }}" @endif>
    </figure>
@elseif ($context->preview)
    <div data-block-id="{{ $block['id'] }}" data-block-type="image" @if ($block['disabled']) hidden @endif>
        @include('xelga::snippets.editor-note', ['note' => 'This post has no featured photo. Add one to the post in VentaCart, Blog.'])
    </div>
@endif
