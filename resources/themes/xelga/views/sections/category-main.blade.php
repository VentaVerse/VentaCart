{{--
    Category products: the category's name, text and photo, its
    subcategories, filters, sort and the page of products core chose.
--}}
@php
    $list = $data['list'];
    $filtersOn = $data['filters']['facets'] !== [] || $data['filters']['price'] !== null;
    $hasActive = $data['filters']['active'] > 0;
    // As many cards to a line as the content area holds (the setting on a page without sides).
    $cols = $context->fitColumns($settings['cols'], 3);
@endphp
<section class="s {{ $section['class'] }} {{ $settings['scheme'] }}" id="{{ $section['id'] }}" @include('xelga::snippets.section-attrs')
         data-variant="{{ $settings['view'] }}" data-default-view="{{ $settings['view'] }}" data-switch-view="{{ $settings['switch_view'] ? 'true' : 'false' }}"
         data-cols="{{ $cols }}" data-mobile-cols="{{ $settings['mobile_cols'] }}" data-subcats="{{ $settings['subcats'] }}"
         data-filters="{{ $filtersOn ? $settings['filters'] : 'none' }}" data-show-description="{{ $settings['show_description'] ? 'true' : 'false' }}"
         data-show-image="{{ $settings['show_image'] ? 'true' : 'false' }}" data-pagination="{{ $settings['pagination'] }}"
         aria-labelledby="{{ $section['id'] }}-title">
    <div class="s__inner">
        <header class="listing-head @if ($data['picture']) listing-head--media @endif">
            @if ($data['picture'])
                <div class="listing-head__media">
                    <img src="{{ $data['picture']['src'] }}" alt="" {{ \Themes\Xelga\Rendering\Fold::attrs($context) }} {{ \Themes\Xelga\Rendering\Picture::sources($data['picture'], \Themes\Xelga\Rendering\Picture::contentSizes($context->span, '(min-width: 750px) 40vw, 100vw', .5)) }}
                         @if ($data['picture']['width']) width="{{ $data['picture']['width'] }}" height="{{ $data['picture']['height'] }}" @endif>
                </div>
            @endif
            <div class="listing-head__body">
                <h1 class="listing-head__title" id="{{ $section['id'] }}-title">{{ $data['title'] }}</h1>
                @if (trim(strip_tags($data['description'])) !== '')
                    {{-- The category description is core's rich text, cleaned by CategoryMain before it reaches the page. --}}
                    <div class="listing-head__text rte">{!! $data['description'] !!}</div>
                @endif
            </div>
        </header>
        @if ($data['subcategories'] !== [])
            <nav class="subcats" aria-label="{{ $data['title'] }} categories">
                <ul class="subcats__list">
                    @foreach ($data['subcategories'] as $sub)
                        <li class="subcat"><a class="subcat__link" href="{{ $sub['url'] }}">@if ($sub['picture'])<span class="subcat__media"><img src="{{ $sub['picture']['src'] }}" alt="" loading="lazy" decoding="async" {{ \Themes\Xelga\Rendering\Picture::sources($sub['picture'], '48px') }}></span>@endif<span class="subcat__name">{{ $sub['name'] }}</span></a></li>
                    @endforeach
                </ul>
            </nav>
        @endif
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
                            <h2 class="no-match__title">No products here yet</h2>
                            <p class="muted">This category has nothing for sale right now. Have a look around the rest of the store.</p>
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
