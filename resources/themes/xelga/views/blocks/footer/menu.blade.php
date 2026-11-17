{{-- One footer column: a heading and a menu made in VentaCart admin. --}}
@php $links = array_values(array_filter($data['menus'][$block['id']] ?? [], fn ($n) => trim((string) ($n['label'] ?? '')) !== '')); @endphp
@if ($links !== [] || $context->preview)
    <nav class="footer__col" data-block-id="{{ $block['id'] }}" data-block-type="menu" aria-labelledby="{{ $block['id'] }}-h"
         @if ($block['disabled']) hidden @endif>
        <h2 class="footer__heading" id="{{ $block['id'] }}-h" data-setting="heading">{{ $block['settings']['heading'] }}</h2>
        @if ($links !== [])
            <ul class="footer__links">
                @foreach ($links as $node)
                    @php $url = \App\Support\SafeInput::link((string) ($node['url'] ?? '')); @endphp
                    <li>@if ($url !== '')<a href="{{ $url }}" @if (! empty($node['new_tab'])) target="_blank" rel="noopener" @endif>{{ $node['label'] }}</a>@else<span>{{ $node['label'] }}</span>@endif</li>
                @endforeach
            </ul>
        @else
            <p class="footer__empty">Pick a menu for this column.</p>
        @endif
    </nav>
@endif
