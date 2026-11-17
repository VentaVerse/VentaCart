{{-- An extension's block inside one of the theme's sections: the extension's own markup in the wrapper the editor selects blocks by. --}}
<div class="xblock {{ $class }}" data-block-id="{{ $block['id'] }}" data-block-type="{{ $block['type'] }}" @if ($block['disabled']) hidden @endif>{{ $inner }}</div>
