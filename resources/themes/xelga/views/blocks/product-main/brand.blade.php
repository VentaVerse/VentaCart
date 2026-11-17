@if ($data['brand'] || $context->preview)
    <p class="pinfo__brand" data-block-id="{{ $block['id'] }}" data-block-type="brand" @if ($block['disabled'] || ! $data['brand']) hidden @endif>
        @if ($data['brand'])
            <a href="{{ \Illuminate\Support\Facades\Route::has('brand.show') ? route('brand.show', $data['brand']->slug) : '#' }}">{{ $data['brand']->name }}</a>
        @endif
    </p>
@endif
