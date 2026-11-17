{{-- Brand logos: the store's brands from VentaCart, as a row or a grid with lines, each linking to its page. --}}
@php $items = $data['items']; @endphp
@if ($items !== [] || $context->preview)
    <section class="s {{ $section['class'] }} {{ $settings['scheme'] }}" @include('xelga::snippets.section-attrs')
             data-cols="{{ $settings['cols'] }}" data-grayscale="{{ $settings['grayscale'] ? 'true' : 'false' }}"
             data-head-align="{{ $settings['head_align'] }}" data-count="{{ count($items) }}" data-odd="{{ count($items) % 2 === 1 ? 'true' : 'false' }}"
             aria-labelledby="{{ $section['id'] }}-title">
        @include('xelga::snippets.section-bg')
        <div class="s__inner">
            @include('xelga::snippets.section-head', ['link' => null])
            @if ($items === [])
                @include('xelga::snippets.editor-note', ['note' => $data['from_blocks'] ? 'Add a brand and pick it from your brands.' : 'The store has no active brands yet. Add them in VentaCart, Catalog, Brands.'])
            @endif
            <ul class="brands">
                @foreach ($items as $item)
                    @php $tag = $item['url'] ? 'a' : 'span'; @endphp
                    <li @if ($item['block']) data-block-id="{{ $item['block']['id'] }}" data-block-type="brand" @if ($item['block']['disabled']) hidden @endif @endif>
                        <{{ $tag }} class="brand" @if ($item['url']) href="{{ $item['url'] }}" @endif>
                            @if ($item['picture'])
                                <img src="{{ $item['picture']['src'] }}" alt="{{ $item['name'] }}" loading="lazy" decoding="async" {{ \Themes\Xelga\Rendering\Picture::sources($item['picture'], '160px') }}
                                     @if ($item['picture']['width']) width="{{ $item['picture']['width'] }}" height="{{ $item['picture']['height'] }}" @endif>
                            @else
                                <span @if ($item['block']) data-setting="name" @endif>{{ $item['name'] !== '' ? $item['name'] : 'Pick a brand' }}</span>
                            @endif
                        </{{ $tag }}>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
