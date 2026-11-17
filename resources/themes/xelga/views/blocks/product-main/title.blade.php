<h1 class="pinfo__title" data-block-id="{{ $block['id'] }}" data-block-type="title" @if ($block['disabled']) hidden @endif>{{ $product->name }}</h1>
@if ($block['disabled'])
    {{-- The page keeps one heading for screen readers even with the name hidden. --}}
    <h1 class="visually-hidden">{{ $product->name }}</h1>
@endif
