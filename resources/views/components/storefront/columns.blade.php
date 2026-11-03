@php
    /**
     * The page row: left column, content, right column.
     *
     * The merchant sets the side columns' widths and the content takes the
     * remainder, so a narrow filter rail beside a wide catalog is a setting
     * rather than something the template decided for them. A theme that
     * keeps its own storage supplies the widths through
     * LayoutColumns::register().
     *
     * A page with no columns renders NO grid at all, just its content. A page
     * that is full width should not pay for a wrapper that does nothing.
     */
    $vPageType = \App\Support\LayoutPage::currentKey();
    $vCols = \App\Models\LayoutColumns::forPage($vPageType);

    // A column with a width but nothing in it is not drawn: an empty rail
    // holding the page open is worse than no rail. What is in it is what the
    // region will draw, so rows a theme's own storage supplies count too.
    $vFilled = function (string $region) use ($vPageType) {
        $vUnits = \App\Support\LayoutHooks::resolveUnits($vPageType, $region);
        if ($vUnits === null) {
            return \App\Models\LayoutPlacement::forRegion($vPageType, $region)->isNotEmpty();
        }

        return $vUnits->contains(fn ($vUnit) => array_merge([], ...array_values((array) $vUnit['columns'])) !== []);
    };
    $vLeft = $vCols['left'] > 0 && $vFilled('column_left');
    $vRight = $vCols['right'] > 0 && $vFilled('column_right');

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
