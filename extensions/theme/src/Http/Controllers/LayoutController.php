<?php

namespace Extensions\Theme\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LayoutColumns;
use App\Models\LayoutPlacement;
use App\Services\ThemeService;
use App\Support\Regions;
use App\Support\StorefrontPageType;
use App\Support\Widgets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The layout editor: what renders where, on which pages.
 *
 * Owned by the theme extension because regions are declared by the theme, but
 * it is its own work page rather than a tab inside the theme editor. The data
 * is store data: placements survive a theme switch, and this is the screen a
 * merchant opens weekly while the theme editor is the one they open twice.
 */
class LayoutController extends Controller
{
    public function index(Request $request, ThemeService $theme)
    {
        $pageType = (string) $request->query('page', LayoutPlacement::EVERYWHERE);

        if ($pageType !== LayoutPlacement::EVERYWHERE && ! StorefrontPageType::exists($pageType)) {
            $pageType = LayoutPlacement::EVERYWHERE;
        }

        $regions = Regions::all();

        // Counts per page type, so the rail answers "which pages have I
        // touched" without opening each one.
        $counts = LayoutPlacement::query()
            ->selectRaw('page_type, COUNT(*) as n')
            ->groupBy('page_type')
            ->pluck('n', 'page_type')
            ->all();

        return view('theme::layout', [
            'pageType' => $pageType,
            'pageTypes' => StorefrontPageType::TYPES,
            'counts' => $counts,
            'regions' => $regions,
            'columns' => LayoutColumns::forPage($pageType),
            'grid' => LayoutColumns::GRID,
            'minContent' => LayoutColumns::MIN_CONTENT,
            'placements' => $this->placements($pageType),
            'inherited' => $pageType === LayoutPlacement::EVERYWHERE
                ? collect()
                : LayoutPlacement::where('page_type', LayoutPlacement::EVERYWHERE)->orderBy('sort_order')->get(),
            'widgets' => $this->catalogue($regions),
            'orphans' => Regions::orphans(),
            'activeTheme' => $theme->getInstalledThemes()[$theme->getActiveTheme()]['name'] ?? $theme->getActiveTheme(),
        ]);
    }

    /** Everything placed on this page type, grouped by region. */
    private function placements(string $pageType): array
    {
        $rows = LayoutPlacement::where('page_type', $pageType)
            ->orderBy('sort_order')->orderBy('id')->get();

        $out = [];

        foreach ($rows as $row) {
            $widget = Widgets::get($row->widget);

            $out[$row->region][] = [
                'id' => $row->id,
                'widget' => $row->widget,
                'title' => $row->title,
                'label' => $widget['title'] ?? $row->widget,
                'enabled' => (bool) $row->is_enabled,
                // A placement whose module is switched off renders nothing on
                // the storefront. It must read as unavailable, not broken.
                'available' => $widget !== null,
                'config' => $row->config ?? [],
            ];
        }

        return $out;
    }

    /**
     * What can be added, and where.
     *
     * Journal filters its module list by the position you are standing in
     * (positions:"column_left,column_right,..."). Same here: a widget declares
     * the regions it accepts, and one that declares none is offered anywhere.
     */
    private function catalogue(array $regions): array
    {
        $out = [];

        foreach (Widgets::all() as $key => $spec) {
            $out[] = [
                'key' => $key,
                'title' => $spec['title'],
                'module' => $spec['module'],
                'regions' => $spec['regions'] ?: array_keys($regions),
            ];
        }

        usort($out, fn ($a, $b) => strcmp($a['title'], $b['title']));

        return $out;
    }

    /**
     * One save carries the whole page type: its columns and its placements.
     *
     * Placements for OTHER page types are never touched, including the '*' set
     * a page inherits. Editing the cart must not be able to disturb what every
     * other page shows.
     */
    public function update(Request $request)
    {
        $data = json_decode((string) $request->input('payload'), true);

        if (! is_array($data)) {
            return back()->with('error', 'The editor sent nothing this save could apply.');
        }

        $pageType = (string) ($data['page_type'] ?? '');

        if ($pageType !== LayoutPlacement::EVERYWHERE && ! StorefrontPageType::exists($pageType)) {
            return back()->with('error', 'Unknown page type.');
        }

        $rows = collect($data['placements'] ?? [])->values();
        $kept = [];

        DB::transaction(function () use ($data, $pageType, $rows, &$kept) {
            LayoutColumns::updateOrCreate(
                ['page_type' => $pageType],
                [
                    'left_width' => (int) ($data['columns']['left'] ?? 0),
                    'right_width' => (int) ($data['columns']['right'] ?? 0),
                ]
            );

            foreach ($rows as $i => $row) {
                $region = (string) ($row['region'] ?? '');
                $widget = (string) ($row['widget'] ?? '');

                // A region this theme does not declare, or a widget nothing
                // registers, is not written. The editor cannot invent either.
                if (! Regions::exists($region) || ! Widgets::has($widget)) {
                    continue;
                }

                $placement = LayoutPlacement::updateOrCreate(
                    ['id' => $row['id'] ?? null],
                    [
                        'page_type' => $pageType,
                        'region' => $region,
                        'widget' => $widget,
                        'title' => $row['title'] ?? null,
                        'config' => $row['config'] ?? [],
                        'sort_order' => $i,
                        'is_enabled' => (bool) ($row['enabled'] ?? true),
                    ]
                );

                $kept[] = $placement->id;
            }

            // Whatever this page type had and the editor did not send back was
            // removed on screen. Scoped to this page type, so nothing else moves.
            LayoutPlacement::where('page_type', $pageType)
                ->when($kept !== [], fn ($q) => $q->whereNotIn('id', $kept))
                ->delete();
        });

        return back()->with('success', count($kept) . ' ' . (count($kept) === 1 ? 'widget' : 'widgets')
            . ' placed on ' . ($pageType === LayoutPlacement::EVERYWHERE ? 'all pages' : StorefrontPageType::label($pageType)) . '.');
    }
}
