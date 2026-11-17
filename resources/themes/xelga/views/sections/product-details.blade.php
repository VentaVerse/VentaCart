{{--
    Product details as tabs or as an accordion. Both are drawn so the
    layout setting can switch live; the stylesheet shows one. The tabs
    follow the tab pattern (arrow keys move between tabs).
--}}
@php
    $panels = $data['panels'];
    $firstOpen = (bool) $settings['first_open'];
    $visible = array_values(array_filter($panels, fn ($p) => ! $p['disabled'] && ! $p['empty']));
    $firstId = $visible[0]['id'] ?? null;
@endphp
@if ($panels !== [])
    <section class="s {{ $section['class'] }} {{ $settings['scheme'] }}" id="{{ $section['id'] }}" @include('xelga::snippets.section-attrs')
             data-layout="{{ $settings['layout'] }}" aria-label="Product details">
        <div class="s__inner details">
            <div class="tabs" data-tabs>
                <div class="tabs__list" role="tablist" aria-label="Product details">
                    @foreach ($panels as $panel)
                        <button type="button" class="tabs__tab" role="tab" id="{{ $panel['id'] }}-tab" aria-controls="{{ $panel['id'] }}-panel"
                                aria-selected="{{ $panel['id'] === $firstId ? 'true' : 'false' }}" tabindex="{{ $panel['id'] === $firstId ? '0' : '-1' }}"
                                data-tab="{{ $panel['id'] }}" @if ($panel['disabled'] || $panel['empty']) hidden @endif>{{ $panel['title'] }}</button>
                    @endforeach
                </div>
                @foreach ($panels as $panel)
                    <div class="tabs__panel" role="tabpanel" id="{{ $panel['id'] }}-panel" aria-labelledby="{{ $panel['id'] }}-tab" tabindex="0"
                         data-block-id="{{ $panel['id'] }}" data-block-type="{{ $panel['type'] }}" @if ($panel['settings']['variant_link'] ?? false) data-variant-link="{{ $panel['title'] }}" @endif
                         @if ($panel['id'] !== $firstId || $panel['disabled'] || $panel['empty']) hidden @endif>
                        @include('xelga::blocks.product-details.' . $panel['type'], ['panel' => $panel])
                    </div>
                @endforeach
            </div>
            <div class="accs" data-accs>
                @foreach ($panels as $panel)
                    <details class="acc" data-acc="{{ $panel['id'] }}" @if ($firstOpen && $panel['id'] === $firstId) open @endif @if ($panel['disabled'] || $panel['empty']) hidden @endif>
                        <summary class="acc__summary">{{ $panel['title'] }}<svg class="icon" aria-hidden="true"><use href="#i-plus"></use></svg></summary>
                        <div class="acc__body">@include('xelga::blocks.product-details.' . $panel['type'], ['panel' => $panel])</div>
                    </details>
                @endforeach
            </div>
        </div>
    </section>
@endif
