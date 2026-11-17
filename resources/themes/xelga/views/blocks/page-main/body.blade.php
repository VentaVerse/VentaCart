{{-- The page's text: core's rich text, printed as core prints it. --}}
<div class="info__body rte" data-block-id="{{ $block['id'] }}" data-block-type="body" @if ($block['disabled']) hidden @endif>
    {!! $page['body'] !!}
</div>
