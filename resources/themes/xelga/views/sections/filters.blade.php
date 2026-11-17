{{--
    Filters: the category page's filter form in a section of its own, so
    it can sit in a page side. Below 990px it is a box that starts closed
    (the listing's Filter button opens it); from 990px it stands open.
    A page with nothing to filter draws nothing, so its side takes no room.
--}}
@php
    $filters = $data['filters'];
    $shows = $filters['facets'] !== [] || $filters['price'] !== null;
@endphp
@if ($shows || $context->preview)
<section class="s {{ $section['class'] }} {{ $settings['scheme'] }}" id="{{ $section['id'] }}" @include('xelga::snippets.section-attrs') aria-label="Filters">
    <div class="s__inner">
        @if ($shows)
            <details class="filters-box" data-filters-box>
                <summary class="filters-box__summary">
                    <span class="filters-box__label"><svg class="icon" aria-hidden="true"><use href="#i-filter"></use></svg>Filter</span>
                    @if ($filters['active'] > 0)<span class="toolbar__badge"><span class="visually-hidden">Filters on: </span>{{ $filters['active'] }}</span>@endif
                    <svg class="icon filters-box__chevron" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
                </summary>
                @include('xelga::blocks.listing.filters')
            </details>
        @else
            <p class="filters-box__note muted">There is nothing to filter on this page. Filters show on category pages whose products have prices, brands or details to choose from; which ones show is set in VentaCart, Settings, Catalog.</p>
        @endif
    </div>
</section>
@endif
