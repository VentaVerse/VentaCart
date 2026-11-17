{{-- The post's text: the blog extension's rich text, printed as core prints it. --}}
<div class="article__body rte" data-block-id="{{ $block['id'] }}" data-block-type="body" @if ($block['disabled']) hidden @endif>
    {!! $post['content'] !!}
</div>
