@props(['name'])
@php
    /**
     * Everything the merchant placed in this region of this page.
     *
     * The theme calls this wherever it wants a slot. Core resolves which
     * widgets belong there; the theme decides where the slot IS and what wraps
     * it. That division is what makes a layout portable between themes that
     * look nothing alike.
     */
    $vPageType = \App\Support\StorefrontPageType::current();
    $vUnits = \App\Models\LayoutRow::forRegion($vPageType, $name);

    // One placement, rendered by its widget's view. A placement whose module
    // is disabled or uninstalled renders NOTHING on the storefront, and is
    // reported in the layout editor instead. A shopper must never meet a
    // broken slot because an admin turned an extension off.
    $vRender = function ($placement) {
        $vWidget = \App\Support\Widgets::get($placement->widget);
        $vView = \App\Support\Widgets::view($placement->widget);
        if (! $vWidget || ! $vView) {
            return '';
        }
        $vData = \App\Support\Widgets::resolve($placement->widget, $placement->config ?? []);

        return view($vView, array_merge(['placement' => $placement], $vData))->render();
    };
@endphp

{{-- A row with one column is drawn as its placements, nothing around them,
     so a store that never split a row renders exactly as it did. A split row
     is core markup on the theme's eight-column grid: the theme styles
     .layout-row and .layout-col, and decides nothing about what is inside. --}}
@foreach ($vUnits as $vUnit)
    @if ($vUnit['row'] && $vUnit['row']->isSplit())
        @php $vWidths = \App\Models\LayoutRow::normalizeColumns($vUnit['row']->columns); @endphp
        <div class="layout-row layout-row--{{ count($vWidths) }}" data-layout-row="{{ $vUnit['row']->id }}">
            @foreach ($vWidths as $vIndex => $vWidth)
                <div class="layout-col layout-col--{{ $vWidth }}">
                    @foreach ($vUnit['columns'][$vIndex] ?? [] as $placement)
                        {!! $vRender($placement) !!}
                    @endforeach
                </div>
            @endforeach
        </div>
    @else
        @foreach ($vUnit['columns'][0] ?? [] as $placement)
            {!! $vRender($placement) !!}
        @endforeach
    @endif
@endforeach
