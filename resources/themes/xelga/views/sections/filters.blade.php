{{--
    Filters: the category page's filter form in a section of its own, so
    it can sit in a page side or a Content column. From 990px it stands
    where it was put. Below 990px the script draws it as the listing's
    filter drawer (the same form, never a copy), closed until the
    listing's Filter button opens it. A page with nothing to filter draws
    only a note.
--}}
@php
    $filters = $data['filters'];
    $shows = $filters['facets'] !== [] || $filters['price'] !== null;
@endphp
@if ($shows || $context->preview)
<section class="s {{ $section['class'] }} {{ $settings['scheme'] }}" id="{{ $section['id'] }}" @include('xelga::snippets.section-attrs') aria-label="Filters">
    @if ($shows)
        <div class="drawer__head filters-drawer__head">
            <h2 class="filters__title" id="{{ $section['id'] }}-title" tabindex="-1" data-filters-title>Filter @if ($filters['active'] > 0)<span class="toolbar__badge"><span class="visually-hidden">Filters on: </span>{{ $filters['active'] }}</span> @endif</h2>
            <button type="button" class="icon-btn" data-filters-close aria-label="Close filters"><svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg></button>
        </div>
        <div class="s__inner drawer__body filters-drawer__body">
            @include('xelga::blocks.listing.filters')
        </div>
    @else
        <div class="s__inner">
            <p class="filters-box__note muted">There is nothing to filter on this page. Filters show on category pages whose products have prices, brands or details to choose from; which ones show is set in VentaCart, Settings, Catalog.</p>
        </div>
    @endif
</section>
@endif
