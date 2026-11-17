{{-- Brand products: the brand's logo, text and count, its filters and the page of products core chose. --}}
@php
    $list = $data['list'];
    $filtersOn = $data['filters']['facets'] !== [] || $data['filters']['price'] !== null;
    $hasActive = $data['filters']['active'] > 0;
    // As many cards to a line as the content area holds (the setting on a page without sides).
    $cols = $context->fitColumns($settings['cols'], 3);
@endphp
<section class="s {{ $section['class'] }} s-brand {{ $settings['scheme'] }}" id="{{ $section['id'] }}" @include('xelga::snippets.section-attrs')
         data-variant="{{ $settings['view'] }}" data-default-view="{{ $settings['view'] }}" data-switch-view="{{ $settings['switch_view'] ? 'true' : 'false' }}"
         data-cols="{{ $cols }}" data-mobile-cols="{{ $settings['mobile_cols'] }}"
         data-filters="{{ $filtersOn ? $settings['filters'] : 'none' }}" data-show-logo="{{ $settings['show_logo'] ? 'true' : 'false' }}"
         data-show-description="{{ $settings['show_description'] ? 'true' : 'false' }}" data-show-count="{{ $settings['show_count'] ? 'true' : 'false' }}"
         data-pagination="{{ $settings['pagination'] }}" aria-labelledby="{{ $section['id'] }}-title">
    <div class="s__inner">
        <header class="brand-head @if (! $data['picture']) brand-head--no-logo @endif">
            @if ($data['picture'])
                <div class="brand-head__logo">
                    <img src="{{ $data['picture']['src'] }}" alt="{{ $data['title'] }} logo" {{ \Themes\Xelga\Rendering\Fold::attrs($context) }} {{ \Themes\Xelga\Rendering\Picture::sources($data['picture'], '240px') }}
                         @if ($data['picture']['width']) width="{{ $data['picture']['width'] }}" height="{{ $data['picture']['height'] }}" @endif>
                </div>
            @endif
            <div class="brand-head__text">
                <h1 class="listing-head__title" id="{{ $section['id'] }}-title">{{ $data['title'] }}</h1>
                @if (trim(strip_tags($data['description'])) !== '')
                    {{-- Cleaned by the section before it reaches the page. --}}
                    <div class="rte brand-head__desc">{!! $data['description'] !!}</div>
                @endif
                <p class="muted brand-head__count">{{ $list['total'] }} {{ $list['total'] === 1 ? 'product' : 'products' }}</p>
            </div>
        </header>
        @include('xelga::blocks.listing.editor-note')
        <div class="listing" data-listing>
            @if ($filtersOn)
                <aside class="listing__aside" aria-label="Filters" data-filter-home>
                    @include('xelga::blocks.listing.filters')
                </aside>
            @endif
            <div class="listing__main">
                @include('xelga::blocks.listing.toolbar', ['withFilters' => $filtersOn])
                @include('xelga::blocks.listing.chips')
                @if ($list['cards'] === [])
                    <div class="no-match">
                        @if ($hasActive)
                            <h2 class="no-match__title">Nothing matches these filters</h2>
                            <p class="muted">Remove a filter or two, or widen the price range.</p>
                            <a class="btn btn--secondary" href="{{ $data['filters']['clear_url'] }}">Clear all filters</a>
                        @else
                            <h2 class="no-match__title">No products from this brand yet</h2>
                            <p class="muted">Have a look around the rest of the store.</p>
                            <a class="btn btn--secondary" href="{{ route('home') }}">Back to the home page</a>
                        @endif
                    </div>
                @else
                    @include('xelga::blocks.listing.grid', ['label' => $data['title'], 'cols' => $cols])
                @endif
                @include('xelga::blocks.listing.pagination')
            </div>
        </div>
        @if ($filtersOn)
            @include('xelga::blocks.listing.drawer')
        @endif
    </div>
</section>
