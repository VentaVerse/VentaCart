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

    // A system region nobody has arranged yet shows the theme's own pieces,
    // in the theme's order, as if they were placed: the page looks as it
    // always did until the merchant moves something.
    if ($vUnits->isEmpty() && ($vDefaults = \App\Support\Regions::defaults($name)) !== []) {
        $vUnits = collect([[
            'row' => null,
            'columns' => [array_map(fn ($d) => new \App\Models\LayoutPlacement([
                'theme' => \App\Models\LayoutPlacement::scopeTheme(), 'page_type' => $vPageType ?? '*', 'region' => $name,
                'widget' => $d['widget'], 'title' => $d['title'], 'config' => $d['config'], 'is_enabled' => true,
            ]), $vDefaults)],
        ]]);
    }

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
    @php
        $vRow = $vUnit['row'];
        $vStyled = $vRow && \App\Support\RowStyle::isStyled($vRow->style);
    @endphp
    @if ($vRow && ($vRow->isSplit() || $vStyled))
        @php $vWidths = \App\Models\LayoutRow::normalizeColumns($vRow->columns); @endphp
        {{-- The style attribute carries the merchant's chosen values as custom
             properties (data, not authored CSS); the rules live in
             resources/css/storefront/layout-rows.css. --}}
        <div class="layout-row layout-row--{{ count($vWidths) }} {{ $vStyled ? implode(' ', \App\Support\RowStyle::classes($vRow->style)) : '' }}"
             data-layout-row="{{ $vRow->id }}" @if ($vStyled) style="{{ \App\Support\RowStyle::vars($vRow->style) }}" @endif>
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
