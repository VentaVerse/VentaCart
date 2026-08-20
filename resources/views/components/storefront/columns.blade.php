@php
    /**
     * The Journal row: left column, content, right column.
     *
     * Journal computes the content width from which sidebars exist (col-sm-6,
     * col-sm-9, col-sm-12). Here the merchant sets the ratio and the content
     * takes the remainder, so a narrow filter rail beside a wide catalogue is
     * a setting rather than something the template decided for them.
     *
     * A page with no columns renders NO grid at all, just its content. A page
     * that is full width should not pay for a wrapper that does nothing.
     */
    $vPageType = \App\Support\StorefrontPageType::current();
    $vCols = \App\Models\LayoutColumns::forPage($vPageType);

    // A column with a width but nothing in it is not drawn: an empty rail
    // holding the page open is worse than no rail.
    $vLeft = $vCols['left'] > 0 && \App\Models\LayoutPlacement::forRegion($vPageType, 'column_left')->isNotEmpty();
    $vRight = $vCols['right'] > 0 && \App\Models\LayoutPlacement::forRegion($vPageType, 'column_right')->isNotEmpty();

    $vContent = \App\Models\LayoutColumns::GRID
        - ($vLeft ? $vCols['left'] : 0)
        - ($vRight ? $vCols['right'] : 0);
@endphp

@if ($vLeft || $vRight)
    <div class="store-row">
        @if ($vLeft)
            <aside class="store-col store-col--{{ $vCols['left'] }}">
                <x-storefront.region name="column_left" />
            </aside>
        @endif

        <div class="store-col store-col--{{ $vContent }}">
            {{ $slot }}
        </div>

        @if ($vRight)
            <aside class="store-col store-col--{{ $vCols['right'] }}">
                <x-storefront.region name="column_right" />
            </aside>
        @endif
    </div>
@else
    {{ $slot }}
@endif
