@php $short = \Themes\Xelga\Sections\ProductMain::cut($data['short'], (int) ($block['settings']['length'] ?? 220)); @endphp
@if ($short !== '' || $context->preview)
    <p class="pinfo__short" data-block-id="{{ $block['id'] }}" data-block-type="short-description" @if ($block['disabled'] || $short === '') hidden @endif>{{ $short }}</p>
@endif
