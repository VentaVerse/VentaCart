{{-- A menu of the store's other pages, beside the text. --}}
@php $links = $data['menus'][$block['id']] ?? []; @endphp
@if ($links !== [] || ($context->preview && $settings['variant'] === 'sidebar'))
    <nav class="info__nav" data-block-id="{{ $block['id'] }}" data-block-type="pages" aria-labelledby="{{ $block['id'] }}-h" @if ($block['disabled']) hidden @endif>
        <h2 class="info__nav-title" id="{{ $block['id'] }}-h" data-setting="heading">{{ $block['settings']['heading'] }}</h2>
        @if ($links === [])
            @include('xelga::snippets.editor-note', ['note' => 'Pick a menu for this list, or add pages in VentaCart, Pages.'])
        @else
            <ul>
                @foreach ($links as $link)
                    <li><a href="{{ $link['url'] }}" @if ($link['current']) aria-current="page" @endif>{{ $link['label'] }}</a></li>
                @endforeach
            </ul>
        @endif
    </nav>
@endif
