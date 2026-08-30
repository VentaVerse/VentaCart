<?php

namespace App\Support;

use App\Models\LayoutPlacement;
use App\Models\LayoutRow;
use App\Services\ThemeService;

/**
 * What an editor needs to know about a layout, resolved once for every
 * editor: the rows of a page in editor shape, the widget catalogue, every
 * widget's settings form, a real page of each type to look at, and the
 * theme's colours. The core Layout page and a theme's own editor read the
 * same answers, so they cannot drift.
 */
final class LayoutInventory
{
    public static function units(string $pageType): object
    {
        $theme = LayoutPlacement::scopeTheme();

        $placements = LayoutPlacement::where('theme', $theme)
            ->where('page_type', $pageType)
            ->orderBy('sort_order')->orderBy('id')->get();

        $rows = LayoutRow::where('theme', $theme)
            ->where('page_type', $pageType)
            ->get()->keyBy('id');

        $card = function (LayoutPlacement $row): array {
            $widget = \App\Support\Widgets::get($row->widget);

            return [
                'id' => $row->id,
                'widget' => $row->widget,
                'title' => $row->title,
                'label' => $widget['title'] ?? $row->widget,
                'enabled' => (bool) $row->is_enabled,
                // A placement whose module is switched off renders nothing on
                // the storefront. It must read as unavailable, not broken.
                'available' => $widget !== null,
                'config' => $row->config ?? [],
                'style' => $row->style ? \App\Support\RowStyle::normalize($row->style) : null,
            ];
        };

        $units = [];

        foreach ($placements as $placement) {
            $rowId = $placement->row_id && $rows->has($placement->row_id) ? $placement->row_id : null;
            $unitKey = $rowId ? 'row-' . $rowId : 'loose-' . $placement->id;

            if (! isset($units[$unitKey])) {
                $row = $rowId ? $rows->get($rowId) : null;
                $widths = $row ? LayoutRow::normalizeColumns($row->columns) : [LayoutRow::GRID];
                $units[$unitKey] = [
                    'region' => $placement->region,
                    'sort' => $row ? [$row->sort_order, $row->id] : [$placement->sort_order, $placement->id],
                    'id' => $row?->id,
                    'columns' => $widths,
                    'enabled' => $row ? (bool) $row->is_enabled : true,
                    'style' => \App\Support\RowStyle::normalize($row?->style),
                    'cols' => array_fill(0, count($widths), []),
                ];
            }

            $index = $rowId ? max(0, min((int) $placement->column_index, count($units[$unitKey]['columns']) - 1)) : 0;
            $units[$unitKey]['cols'][$index][] = $card($placement);
        }

        // A row with nothing in it yet is still a row the merchant made.
        foreach ($rows as $row) {
            if (! isset($units['row-' . $row->id])) {
                $widths = LayoutRow::normalizeColumns($row->columns);
                $units['row-' . $row->id] = [
                    'region' => $row->region,
                    'sort' => [$row->sort_order, $row->id],
                    'id' => $row->id,
                    'columns' => $widths,
                    'enabled' => (bool) $row->is_enabled,
                    'style' => \App\Support\RowStyle::normalize($row->style),
                    'cols' => array_fill(0, count($widths), []),
                ];
            }
        }

        usort($units, fn ($a, $b) => $a['sort'] <=> $b['sort']);

        $out = [];
        foreach ($units as $unit) {
            $region = $unit['region'];
            unset($unit['region'], $unit['sort']);
            $out[$region][] = $unit;
        }

        // A system region with nothing arranged on the page it belongs to
        // shows the theme's shipped pieces as one row of cards, ids null:
        // saving the page makes them the merchant's own.
        foreach (\App\Support\Regions::all() as $region => $spec) {
            $defaults = \App\Support\Regions::defaults($region);
            if ($defaults === [] || isset($out[$region]) || \App\Support\Regions::page($region) !== $pageType) {
                continue;
            }
            $out[$region][] = [
                'id' => null,
                'columns' => [LayoutRow::GRID],
                'enabled' => true,
                'style' => \App\Support\RowStyle::normalize([]),
                'shipped' => true,
                'cols' => [array_map(fn ($d) => [
                    'id' => null,
                    'widget' => $d['widget'],
                    'title' => $d['title'],
                    'label' => \App\Support\Widgets::get($d['widget'])['title'] ?? $d['widget'],
                    'enabled' => true,
                    'available' => \App\Support\Widgets::has($d['widget']),
                    'config' => $d['config'],
                ], $defaults)],
            ];
        }

        // An OBJECT, always. An empty PHP array serialises to [], which is a JS
        // Array, and assigning a string key to it never triggers Alpine's
        // reactivity: the hidden payload input kept its load-time value.
        return (object) $out;
    }

    public static function catalogue(array $regions): array
    {
        $out = [];

        foreach (\App\Support\Widgets::all() as $key => $spec) {
            $out[] = [
                'key' => $key,
                'title' => $spec['title'],
                'module' => $spec['module'],
                'shape' => $spec['shape'] ?? null,
                'regions' => $spec['regions'] ?: array_keys($regions),
                'pages' => $spec['pages'] ?? null,
                'system' => (bool) ($spec['system'] ?? false),
            ];
        }

        usort($out, fn ($a, $b) => strcmp($a['title'], $b['title']));

        return $out;
    }

    public static function schemaSet(): array
    {
        return collect(\App\Support\Widgets::all())->keys()
            ->mapWithKeys(fn ($key) => [$key => array_map(function ($field) {
                // A named route becomes a URL here, so the view never resolves
                // routes and a missing one cannot 500 the editor.
                if (! empty($field['create_route']) && \Illuminate\Support\Facades\Route::has($field['create_route'])) {
                    $field['create_url'] = route($field['create_route']);
                }
                unset($field['create_route']);

                return $field;
            }, \App\Support\Widgets::schema($key))])->all();
    }

    public static function sampleUrl(string $pageType): string
    {
        $home = url('/');

        try {
            return match ($pageType) {
                'category' => ($c = \App\Models\Category::where('is_active', true)->orderBy('id')->first())
                    ? route('category.show', $c) : $home,
                'product' => ($p = \App\Models\Product::where('is_active', true)->orderBy('id')->first())
                    ? route('product.show', $p) : $home,
                'brand' => ($b = \App\Models\Brand::where('is_active', true)->orderBy('id')->first())
                    ? route('brand.show', $b) : $home,
                'search' => route('search.results', ['q' => 'a']),
                'cart' => route('cart.index'),
                'checkout' => route('checkout.index'),
                'blog' => \Illuminate\Support\Facades\Route::has('blog.index') ? route('blog.index') : $home,
                'account' => \Illuminate\Support\Facades\Route::has('account.dashboard') ? route('account.dashboard') : $home,
                'auth' => \Illuminate\Support\Facades\Route::has('customer.login') ? route('customer.login') : $home,
                default => $home,
            };
        } catch (\Throwable $e) {
            // A missing route or an empty catalogue is not a reason for the
            // layout editor to 500. The link falls back to the storefront.
            return $home;
        }
    }

    public static function themeColors(): array
    {
        $colors = app(ThemeService::class)->getGroup('colors');
        $out = [];

        foreach ($colors as $key => $hex) {
            if (! is_string($hex) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
                continue;
            }
            $out[] = ['key' => $key, 'label' => ucfirst(str_replace('_', ' ', $key)), 'hex' => strtoupper($hex)];
        }

        return $out;
    }
}
