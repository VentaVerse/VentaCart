<?php

namespace App\Services;

use App\Http\Controllers\Admin\Layout\RefusedSave;
use App\Models\LayoutColumns;
use App\Models\LayoutPlacement;
use App\Models\LayoutRow;
use App\Support\Regions;
use App\Support\AdminPermissions;
use App\Support\ColumnGrid;
use App\Support\RowStyle;
use App\Support\WidgetConfig;
use App\Support\Widgets;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * One save carries the whole page type: its columns and its rows. Shared by
 * every editor that writes a layout (the core Layout page, a theme's own
 * editor), so what "save this page" means is decided once.
 *
 * Placements for OTHER page types are never touched, including the '*' set
 * a page inherits. Editing the cart must not be able to disturb what every
 * other page shows.
 *
 * @return array{kept: list<int>, spared: list<int>, rows: int}
 */
class LayoutSaver
{
    /**
     * @param  array{columns?: array, settings?: array, rows?: array, placements?: array}  $data
     *
     * @param  bool  $trusted  true for a layout the store shipped with (a
     *                          theme's own files), which may carry code
     *                          fields whoever applies it
     * @param  ColumnGrid|null  $grid  the rules the rows' columns follow;
     *                          the scoped theme's grid when not given
     *
     * @throws RefusedSave when nothing sent could be placed
     * @throws AuthorizationException when someone who may not write
     *                                storefront code changed a code field
     */
    public function save(string $pageType, array $data, bool $trusted = false, ?ColumnGrid $grid = null): array
    {
        $grid ??= ColumnGrid::forTheme(LayoutPlacement::scopeTheme());

        // The editor sends rows. A flat placements list (the shape before rows
        // existed, and what an import may still carry) is accepted too: each
        // placement becomes its own one-column row.
        $rows = isset($data['rows']) && is_array($data['rows'])
            ? collect($data['rows'])->values()
            : collect($data['placements'] ?? [])->values()->map(fn ($p) => [
                'id' => null, 'region' => $p['region'] ?? '', 'columns' => $grid->full(), 'enabled' => true,
                'placements' => [$p + ['column' => 0]],
            ]);

        if (! $trusted) {
            $this->refuseCodeChanges($pageType, $rows);
        }

        // Written by this save, and counted in what the merchant is told.
        $kept = [];
        // Protected from the sweep, and deliberately NOT counted: telling a
        // merchant "3 widgets placed" when one of them is a banner they cannot
        // currently see is how a screen earns distrust.
        $spared = [];

        DB::transaction(function () use ($data, $pageType, $rows, $grid, &$kept, &$spared) {
            // Placements this screen can show but cannot send back: a widget
            // whose module is switched off, and a region this theme no longer
            // declares. The editor renders both and PROMISES both are kept.
            $spared = LayoutPlacement::where('theme', LayoutPlacement::scopeTheme())
                ->where('page_type', $pageType)
                ->get()
                ->filter(fn ($p) => ! Regions::exists($p->region) || ! Widgets::has($p->widget))
                ->pluck('id')
                ->all();

            LayoutColumns::updateOrCreate(
                ['theme' => LayoutPlacement::scopeTheme(), 'page_type' => $pageType],
                [
                    'left_width' => (int) ($data['columns']['left'] ?? 0),
                    'right_width' => (int) ($data['columns']['right'] ?? 0),
                    'settings' => (array) ($data['settings'] ?? []),
                ]
            );

            // Placement ids this save may reuse: those already on this theme
            // and page. Any other id the payload names (another page's,
            // another theme's, an id sent twice) is written as a new
            // placement, so a crafted save cannot move or overwrite a
            // placement it does not own.
            $ownedPlacements = LayoutPlacement::where('theme', LayoutPlacement::scopeTheme())
                ->where('page_type', $pageType)
                ->pluck('id')
                ->flip()
                ->all();

            $keptRows = [];
            $order = 0;

            foreach ($rows as $r => $unit) {
                $region = (string) ($unit['region'] ?? '');
                // A region this theme does not declare is not written. The
                // editor cannot invent one.
                if (! Regions::exists($region)) {
                    continue;
                }

                $widths = $grid->normalizeColumns($unit['columns'] ?? $grid->full());

                // A row the editor sent under an id it does not own (another
                // page, another theme) is written as a new row of this page.
                $rowId = isset($unit['id']) && is_numeric($unit['id']) ? (int) $unit['id'] : null;
                $owned = $rowId ? LayoutRow::where('id', $rowId)
                    ->where('theme', LayoutPlacement::scopeTheme())->where('page_type', $pageType)->exists() : false;

                $layoutRow = LayoutRow::updateOrCreate(
                    ['id' => $owned ? $rowId : null],
                    [
                        'theme' => LayoutPlacement::scopeTheme(),
                        'page_type' => $pageType,
                        'region' => $region,
                        'columns' => $widths,
                        'style' => RowStyle::normalizeRow($unit['style'] ?? []),
                        'responsive' => $grid->normalizeResponsive($unit['responsive'] ?? [], count($widths)),
                        'sort_order' => $r,
                        'is_enabled' => (bool) ($unit['enabled'] ?? true),
                    ]
                );
                $keptRows[] = $layoutRow->id;

                foreach (collect($unit['placements'] ?? [])->values() as $row) {
                    $widget = (string) ($row['widget'] ?? '');
                    // A widget nothing registers is not written either.
                    if (! Widgets::has($widget)) {
                        continue;
                    }
                    $placementId = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : null;
                    if ($placementId !== null && isset($ownedPlacements[$placementId])) {
                        unset($ownedPlacements[$placementId]);
                    } else {
                        $placementId = null;
                    }
                    $placement = LayoutPlacement::updateOrCreate(
                        ['id' => $placementId],
                        [
                            'theme' => LayoutPlacement::scopeTheme(),
                            'page_type' => $pageType,
                            'region' => $region,
                            'row_id' => $layoutRow->id,
                            'column_index' => max(0, min((int) ($row['column'] ?? 0), count($widths) - 1)),
                            'widget' => $widget,
                            'title' => isset($row['title']) && is_scalar($row['title']) ? mb_substr((string) $row['title'], 0, 255) : null,
                            'config' => WidgetConfig::clean($widget, $row['config'] ?? []),
                            'style' => isset($row['style']) && is_array($row['style']) ? RowStyle::normalize($row['style']) : null,
                            'sort_order' => $order++,
                            'is_enabled' => (bool) ($row['enabled'] ?? true),
                        ]
                    );
                    $kept[] = $placement->id;
                }
            }

            // A payload we could not read a single widget out of is not an
            // instruction to empty the page, it is a payload we could not
            // read. Refusing the whole save here is the difference between
            // an error message and an error message arriving after the work
            // was already destroyed. An empty placements list is a different
            // thing entirely and still empties the page, below.
            $sent = $rows->sum(fn ($unit) => count($unit['placements'] ?? []));
            if ($kept === [] && $sent > 0) {
                throw new RefusedSave();
            }

            // Whatever this page type had and the editor did not send back was
            // removed on screen. Scoped to this page type, so nothing else moves.
            LayoutPlacement::where('theme', LayoutPlacement::scopeTheme())
                ->where('page_type', $pageType)
                ->whereNotIn('id', array_merge($kept, $spared))
                ->delete();

            // Rows removed on screen go too. A spared placement inside one is
            // left loose (row_id nulled by the schema), never deleted.
            LayoutRow::where('theme', LayoutPlacement::scopeTheme())
                ->where('page_type', $pageType)
                ->whereNotIn('id', $keptRows)
                ->delete();
        });

        return ['kept' => $kept, 'spared' => $spared, 'rows' => $rows->count()];
    }

    /**
     * A widget field marked 'code' (the HTML block's markup) is printed on
     * the storefront unescaped, so only someone allowed to write storefront
     * code may change one. Everyone else can still move, switch off, delete
     * or re-save such a block, as long as the code itself arrives as stored.
     * With nobody signed in (a console command) the operator is trusted.
     */
    private function refuseCodeChanges(string $pageType, Collection $rows): void
    {
        $user = auth()->user();

        if ($user === null || Gate::forUser($user)->allows(AdminPermissions::STOREFRONT_CODE)) {
            return;
        }

        $stored = null;

        foreach ($rows as $unit) {
            foreach ((array) ($unit['placements'] ?? []) as $row) {
                $widget = (string) ($row['widget'] ?? '');
                $fields = array_keys(array_filter(
                    (array) (Widgets::get($widget)['schema'] ?? []),
                    fn ($field) => is_array($field) && ($field['code'] ?? false) === true
                ));

                if ($fields === []) {
                    continue;
                }

                $stored ??= LayoutPlacement::where('theme', LayoutPlacement::scopeTheme())
                    ->where('page_type', $pageType)
                    ->get(['id', 'widget', 'config'])
                    ->keyBy('id');

                $id = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : null;
                $before = $id !== null && $stored->get($id)?->widget === $widget
                    ? (array) $stored->get($id)->config
                    : [];

                foreach ($fields as $field) {
                    if ((string) ($row['config'][$field] ?? '') !== (string) ($before[$field] ?? '')) {
                        throw new AuthorizationException('Only a super admin can change an HTML block.');
                    }
                }
            }
        }
    }
}
