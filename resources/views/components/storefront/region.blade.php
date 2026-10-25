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
    // The most specific page key this request is on: a category's own
    // rows win over its level's, over all categories', over every page's.
    $vPageType = \App\Support\LayoutPage::currentKey();
    $vUnits = \App\Support\LayoutHooks::resolveUnits($vPageType, $name)
        ?? \App\Models\LayoutRow::forRegion($vPageType, $name);

    // A system region nobody has arranged yet shows the theme's own pieces,
    // in the theme's order, as if they were placed: the page looks as it
    // always did until the merchant moves something.
    // A shipped row with columns is drawn as an unsaved row, so it renders
    // through the same grid markup a saved one does.
    if ($vUnits->isEmpty() && ($vShipped = \App\Support\Regions::defaultRows($name)) !== []) {
        $vTheme = \App\Models\LayoutPlacement::scopeTheme();
        $vUnits = collect(array_map(fn ($r) => [
            'row' => count($r['columns']) > 1 ? new \App\Models\LayoutRow([
                'theme' => $vTheme, 'page_type' => $vPageType ?? '*', 'region' => $name,
                'columns' => $r['columns'], 'responsive' => $r['responsive'], 'is_enabled' => true,
            ]) : null,
            'columns' => array_map(fn ($pieces) => array_map(fn ($d) => new \App\Models\LayoutPlacement([
                'theme' => $vTheme, 'page_type' => $vPageType ?? '*', 'region' => $name,
                'widget' => $d['widget'], 'title' => $d['title'], 'config' => $d['config'], 'is_enabled' => true,
            ]), $pieces), $r['cols']),
        ], $vShipped));
    }

    // One placement, rendered by its widget's view. A placement whose module
    // is disabled or uninstalled renders NOTHING on the storefront, and is
    // reported in the layout editor instead. A shopper must never meet a
    // broken slot because an admin turned an extension off. A column is
    // walked by App\Support\BlockGroup, so a block group can take the
    // blocks it holds.
    $vRender = function ($placement, array $vExtra = []) use ($name) {
        $vWidget = \App\Support\Widgets::get($placement->widget);
        $vView = \App\Support\Widgets::view($placement->widget);
        if (! $vWidget || ! $vView) {
            return \App\Support\LayoutHooks::wrapPlacement('', $placement, $name);
        }
        $vData = \App\Support\Widgets::resolve($placement->widget, $placement->config ?? []);
        $vHtml = view($vView, array_merge(['placement' => $placement], $vData, $vExtra))->render();

        // A block styled on its own (box, title, an extra class, hidden on
        // a device) is wrapped in core markup on the same vocabulary as a
        // styled row.
        if (\App\Support\RowStyle::isStyled($placement->style)) {
            $vHtml = '<div' . \App\Support\RowStyle::attributes($placement->style, 'layout-block', ['layout-block']) . '>' . $vHtml . '</div>';
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
        $vColStyled = $vRow && \App\Support\RowStyle::hasColumnStyles($vRow->style);
        $vHtml = '';

        if ($vRow && ($vRow->isSplit() || $vStyled || $vColStyled)) {
            $vWidths = \App\Models\LayoutRow::normalizeColumns($vRow->columns);
            $vResp = \App\Models\LayoutRow::isResponsive($vRow->responsive, count($vWidths))
                ? \App\Models\LayoutRow::normalizeResponsive($vRow->responsive, count($vWidths)) : null;
            $vBase = ['layout-row', 'layout-row--' . count($vWidths)];
            // Columns given a phone width keep a grid on phones instead of stacking.
            if ($vResp && array_filter($vResp['phone'], fn ($w) => $w !== null) !== []) {
                $vBase[] = 'layout-row--phone-cols';
            }
            // The style attribute carries the merchant's chosen values as
            // custom properties (data, not authored CSS); the rules live in
            // resources/css/storefront/layout-rows.css.
            $vHtml .= '<div' . ($vStyled
                    ? \App\Support\RowStyle::attributes($vRow->style, 'layout-row', $vBase)
                    : ' class="' . e(implode(' ', $vBase)) . '"')
                . ' data-layout-row="' . e((string) $vRow->id) . '">';
            foreach ($vWidths as $vCol => $vWidth) {
                $vColVars = [];
                if ($vResp) {
                    foreach (['tablet' => '--col-tablet', 'phone' => '--col-phone'] as $vDevice => $vVar) {
                        if ($vResp[$vDevice][$vCol] !== null) {
                            $vColVars[] = $vVar . ': ' . (int) $vResp[$vDevice][$vCol];
                        }
                    }
                    foreach (['tablet_order' => '--col-tablet-order', 'phone_order' => '--col-phone-order'] as $vKey => $vVar) {
                        if ($vResp[$vKey][$vCol] !== $vCol + 1) {
                            $vColVars[] = $vVar . ': ' . (int) $vResp[$vKey][$vCol];
                        }
                    }
                }
                $vColBase = ['layout-col', 'layout-col--' . (int) $vWidth];
                $vColStyle = \App\Support\RowStyle::columnStyle($vRow->style, $vCol);
                // A column's own style shares the style attribute with its
                // device widths and orders.
                $vHtml .= '<div' . ($vColStyle
                        ? \App\Support\RowStyle::attributes($vColStyle, 'layout-col', $vColBase, $vColVars)
                        : ' class="' . e(implode(' ', $vColBase)) . '"' . ($vColVars !== [] ? ' style="' . e(implode('; ', $vColVars)) . '"' : ''))
                    . '>';
                $vHtml .= \App\Support\BlockGroup::renderColumn($vUnit['columns'][$vCol] ?? [], $vRender);
                $vHtml .= '</div>';
            }
            $vHtml .= '</div>';
        } else {
            $vHtml .= \App\Support\BlockGroup::renderColumn($vUnit['columns'][0] ?? [], $vRender);
        }

        $vOut .= \App\Support\LayoutHooks::wrapRow($vHtml, $vRow, $name, (int) $vIndex, $vPageType);
    }

    if ($vUnits->isEmpty()) {
        $vOut = \App\Support\LayoutHooks::renderEmpty($name, $vPageType);
    }
@endphp
{!! $vOut !!}
