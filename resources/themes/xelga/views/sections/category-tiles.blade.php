{{-- Category tiles: links to core categories with a photo each, as cards, circles, names on photos or a compact list. --}}
@php
    $tiles = [];
    foreach ($blocks as $block) {
        $tile = $data['tiles'][$block['id']] ?? null;
        // Shoppers only see tiles that lead to a category they can open.
        if ($tile && ($tile['url'] !== null || $context->preview)) {
            $tiles[] = ['block' => $block, 'tile' => $tile];
        }
    }
@endphp
@if ($tiles !== [] || $context->preview)
    <section class="s {{ $section['class'] }} {{ $settings['scheme'] }}" @include('xelga::snippets.section-attrs')
             data-cols="{{ $data['cols'] }}" data-ratio="{{ $settings['ratio'] }}"
             data-show-note="{{ $settings['show_note'] ? 'true' : 'false' }}" data-mobile-layout="{{ $settings['mobile_layout'] }}"
             data-head-align="{{ $settings['head_align'] }}" data-count="{{ count($tiles) }}" aria-labelledby="{{ $section['id'] }}-title">
        @include('xelga::snippets.section-bg')
        <div class="s__inner">
            @include('xelga::snippets.section-head', ['link' => $data['link']])
            @if ($tiles === [])
                @include('xelga::snippets.editor-note', ['note' => 'Add a category tile and pick its category.'])
            @endif
            <ul class="tiles">
                @foreach ($tiles as $item)
                    @php [$block, $tile] = [$item['block'], $item['tile']]; @endphp
                    <li class="tile" data-block-id="{{ $block['id'] }}" data-block-type="category"
                        data-focal="{{ $block['settings']['focal'] ?? 'center' }}" data-focal-y="{{ $block['settings']['focal_y'] ?? 'center' }}"
                        @if ($block['disabled']) hidden @endif>
                        <a class="tile__link" href="{{ $tile['url'] ?? '#' }}">
                            <span class="tile__media">
                                @if ($tile['picture'])
                                    <img src="{{ $tile['picture']['src'] }}" alt="" {{ \Themes\Xelga\Rendering\Fold::attrs($context) }} {{ \Themes\Xelga\Rendering\Picture::sources($tile['picture'], \Themes\Xelga\Rendering\Picture::gridSizes($context->span, '(min-width: 990px) 25vw, 50vw', 2, $data['cols'], $context->inSide)) }}
                                         @if ($tile['picture']['width']) width="{{ $tile['picture']['width'] }}" height="{{ $tile['picture']['height'] }}" @endif>
                                @endif
                            </span>
                            <span class="tile__text">
                                <span class="tile__title"><span data-setting="label">{{ $tile['label'] !== '' ? $tile['label'] : 'Pick a category' }}</span><svg class="icon" aria-hidden="true"><use href="#i-arrow-right"></use></svg></span>
                                <span class="tile__note" data-setting="note" data-optional @if (($block['settings']['note'] ?? '') === '') hidden @endif>{{ $block['settings']['note'] ?? '' }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
