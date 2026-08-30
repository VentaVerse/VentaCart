@props(['name'])
@php
    /**
     * Everything the merchant placed in this region of this page.
     *
     * The theme calls this wherever it wants a slot. Core resolves which
     * widgets belong there; the theme decides where the slot IS and what wraps
     * it. That division is what makes a layout portable between themes that
     * look nothing alike.
     *
     * A theme's own editor may step in through App\Support\LayoutHooks: draw
     * a draft instead of the saved rows, wrap rows and placements so it can
     * point at them, and show an empty region as somewhere to drop into.
     */
    $vPageType = \App\Support\StorefrontPageType::current();
    $vUnits = \App\Support\LayoutHooks::resolveUnits($vPageType, $name)
        ?? \App\Models\LayoutRow::forRegion($vPageType, $name);

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
    $vRender = function ($placement) use ($name) {
        $vWidget = \App\Support\Widgets::get($placement->widget);
        $vView = \App\Support\Widgets::view($placement->widget);
        if (! $vWidget || ! $vView) {
            return \App\Support\LayoutHooks::wrapPlacement('', $placement, $name);
        }
        $vData = \App\Support\Widgets::resolve($placement->widget, $placement->config ?? []);
        $vHtml = view($vView, array_merge(['placement' => $placement], $vData))->render();

        // A block styled on its own (box padding, background, border,
        // corners, shadow, tone, title alignment) is wrapped in core markup
        // on the same vocabulary as a styled row.
        if (\App\Support\RowStyle::isStyled($placement->style)) {
            $vHtml = '<div class="layout-block ' . e(implode(' ', \App\Support\RowStyle::classes($placement->style, 'layout-block'))) . '"'
                . ' style="' . e(\App\Support\RowStyle::vars($placement->style)) . '">' . $vHtml . '</div>';
        }

        return \App\Support\LayoutHooks::wrapPlacement($vHtml, $placement, $name);
    };

    // A row with one column is drawn as its placements, nothing around them,
    // so a store that never split a row renders exactly as it did. A split
    // row is core markup on the theme's eight-column grid: the theme styles
    // .layout-row and .layout-col, and decides nothing about what is inside.
    $vOut = '';
    foreach ($vUnits as $vIndex => $vUnit) {
        $vRow = $vUnit['row'];
        $vStyled = $vRow && \App\Support\RowStyle::isStyled($vRow->style);
        $vHtml = '';

        if ($vRow && ($vRow->isSplit() || $vStyled)) {
            $vWidths = \App\Models\LayoutRow::normalizeColumns($vRow->columns);
            // The style attribute carries the merchant's chosen values as
            // custom properties (data, not authored CSS); the rules live in
            // resources/css/storefront/layout-rows.css.
            $vHtml .= '<div class="layout-row layout-row--' . count($vWidths)
                . ($vStyled ? ' ' . e(implode(' ', \App\Support\RowStyle::classes($vRow->style))) : '') . '"'
                . ' data-layout-row="' . e((string) $vRow->id) . '"'
                . ($vStyled ? ' style="' . e(\App\Support\RowStyle::vars($vRow->style)) . '"' : '') . '>';
            foreach ($vWidths as $vCol => $vWidth) {
                $vHtml .= '<div class="layout-col layout-col--' . (int) $vWidth . '">';
                foreach ($vUnit['columns'][$vCol] ?? [] as $placement) {
                    $vHtml .= $vRender($placement);
                }
                $vHtml .= '</div>';
            }
            $vHtml .= '</div>';
        } else {
            foreach ($vUnit['columns'][0] ?? [] as $placement) {
                $vHtml .= $vRender($placement);
            }
        }

        $vOut .= \App\Support\LayoutHooks::wrapRow($vHtml, $vRow, $name, (int) $vIndex, $vPageType);
    }

    if ($vUnits->isEmpty()) {
        $vOut = \App\Support\LayoutHooks::renderEmpty($name, $vPageType);
    }
@endphp
{!! $vOut !!}
