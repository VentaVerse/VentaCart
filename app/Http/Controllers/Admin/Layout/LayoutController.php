<?php

namespace App\Http\Controllers\Admin\Layout;

use App\Http\Controllers\Controller;
use App\Models\LayoutColumns;
use App\Models\LayoutPlacement;
use App\Models\LayoutRow;
use App\Models\LayoutStylePreset;
use App\Models\LayoutTemplate;
use App\Support\RowStyle;
use App\Services\ThemeService;
use App\Support\Regions;
use App\Support\StorefrontPageType;
use App\Support\Widgets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The layout editor: what renders where, on which pages.
 *
 * Core, not the theme extension (owner ruling 2026-08-29): the data is store
 * data, placements and rows survive a theme switch, and switching the theme
 * editor off must not hide the merchant's own layouts. Regions are still
 * declared by the active theme; this screen only reads them.
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
        $slug = $theme->getActiveTheme();
        $themeName = $theme->getInstalledThemes()[$slug]['name'] ?? $slug;
        $theme = $slug;

        $counts = LayoutPlacement::query()
            ->where('theme', $theme)
            ->selectRaw('page_type, COUNT(*) as n')
            ->groupBy('page_type')
            ->pluck('n', 'page_type')
            ->all();

        return view('admin.layout.index', [
            'theme' => $themeName,
            'pageType' => $pageType,
            'pageTypes' => StorefrontPageType::TYPES,
            'counts' => $counts,
            'regions' => $regions,
            'columns' => LayoutColumns::forPage($pageType),
            'grid' => LayoutColumns::GRID,
            'minContent' => LayoutColumns::MIN_CONTENT,
            'rows' => $this->units($pageType),
            // What this page inherits from All pages, in the same shape, so
            // the editor draws it as rows it cannot touch.
            'inherited' => $pageType === LayoutPlacement::EVERYWHERE
                ? (object) []
                : $this->units(LayoutPlacement::EVERYWHERE),
            'layouts' => LayoutRow::LAYOUTS,
            // The theme's own colours, so a row can be painted in the look's
            // vocabulary and keep following it when the look changes.
            'themeColors' => $this->themeColors(),
            'stylePresets' => LayoutStylePreset::where('theme', $theme)->orderBy('name')->get(['id', 'name', 'style'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'style' => RowStyle::normalize($p->style)])->values(),
            'styleDefaults' => RowStyle::DEFAULTS,
            'stylePresetsUrl' => route('admin.layout.style-presets.store'),
            // Saved arrangements, handed over in the editor's own shape so
            // loading one is a local act that arms Save.
            'templates' => LayoutTemplate::where('theme', $theme)->orderBy('name')->get()
                ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'region' => $t->region, 'rows' => LayoutTemplate::toUnits($t->rows)])
                ->concat($this->shippedTemplates())->values(),
            'templatesUrl' => route('admin.layout.templates.store'),
            'exportUrl' => route('admin.layout.templates.export'),
            'importUrl' => route('admin.layout.templates.import'),
            'copyUrl' => route('admin.layout.copy'),
            'widgets' => $this->catalogue($regions),
            // Every widget's settings form, resolved. Without this a merchant
            // can place a banner and never say WHICH banner.
            'schemas' => $this->schemaSet(),
            'pageSettings' => $this->pageSettings($pageType),
            'sampleUrl' => $this->sampleUrl($pageType),
            // Placements in regions this theme no longer declares. Computed
            // since the layout system landed and never once rendered, so a
            // theme update that drops a region hid a merchant's work silently.
            'orphans' => Regions::orphans()->map(fn ($o) => [
                'id' => $o->id,
                'region' => $o->region,
                'widget' => $o->widget,
                'title' => $o->title,
                'page' => $o->pageLabel(),
                'page_type' => $o->page_type,
            ])->values(),
        ]);
    }

    /**
     * Everything placed on this page type, grouped by region, as the rows the
     * editor draws: each row with its column widths and its cards per column.
     * A placement with no row stands as its own one-column row, which is what
     * every placement was before rows existed.
     */
    private function units(string $pageType): object
    {
        return \App\Support\LayoutInventory::units($pageType);
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
        return \App\Support\LayoutInventory::catalogue($regions);
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

        try {
            $result = app(\App\Services\LayoutSaver::class)->save($pageType, $data);
            $kept = $result['kept'];
            $spared = $result['spared'];
            $rows = collect(array_fill(0, $result['rows'], null));
        } catch (RefusedSave $e) {
            return back()->with('error', 'Nothing could be placed on '
                . ($pageType === LayoutPlacement::EVERYWHERE ? 'all pages' : StorefrontPageType::label($pageType))
                . '. Nothing was changed.');
        }

        $where = $pageType === LayoutPlacement::EVERYWHERE ? 'all pages' : StorefrontPageType::label($pageType);

        if ($kept === [] && $rows->isEmpty()) {
            // Not empty if something is still standing that this save could
            // not touch, so the message says what is actually there.
            return back()->with('success', $spared === []
                ? $where . ' is now empty.'
                : $where . ' is now empty apart from ' . count($spared) . ' '
                    . (count($spared) === 1 ? 'widget' : 'widgets') . ' waiting on a module.');
        }

        return back()->with('success', count($kept) . ' ' . (count($kept) === 1 ? 'widget' : 'widgets')
            . ' placed on ' . $where . '.');
    }

    /**
     * A real page of the type being edited, to go and look at.
     *
     * View store always opened the homepage, so a merchant arranging Product
     * pages was sent somewhere their changes did not show. Resolved from the
     * merchant's own catalogue, and never guessed: a type with no real page
     * behind it yet falls back to the homepage rather than linking to a 404.
     */
    private function sampleUrl(string $pageType): string
    {
        return \App\Support\LayoutInventory::sampleUrl($pageType);
    }

    /**
     * The parts of a hardcoded page this theme lets a merchant change.
     *
     * Not a "reduction layer": the page is not being taken apart. Its parts
     * simply have controls, and hiding is one of them. Declared by the theme in
     * theme.json, so a theme with nothing to offer offers nothing, and core
     * never learns what a breadcrumb is.
     */
    private function pageSettings(string $pageType): array
    {
        $meta = json_decode((string) @file_get_contents(
            app(ThemeService::class)->getThemePath() . '/theme.json'
        ), true) ?: [];

        $declared = $meta['page_settings'] ?? [];
        $stored = LayoutColumns::forPage($pageType)['settings'] ?? [];

        $out = [];

        foreach ($declared as $key => $field) {
            $out[] = [
                'name' => $key,
                'label' => $field['label'] ?? ucfirst(str_replace('_', ' ', $key)),
                'type' => $field['type'] ?? 'toggle',
                // Where in the page this part sits, so the editor can draw its
                // control on the thing it governs rather than in a box off to
                // one side whose scope has to be stated in words.
                'part' => $field['part'] ?? null,
                'default' => $field['default'] ?? true,
                'value' => $stored[$key] ?? ($field['default'] ?? true),
                'pages' => $field['pages'] ?? null,
            ];
        }

        return array_values(array_filter(
            $out,
            fn ($f) => $f['pages'] === null || in_array($pageType, $f['pages'], true)
        ));
    }

    /**
     * The active theme's colours as the row inspector offers them: key, a
     * readable label, and the current value so the chip can show it.
     */
    private function themeColors(): array
    {
        return \App\Support\LayoutInventory::themeColors();
    }

    /**
     * A row style kept under a name. Its own record, saved on its own
     * explicit control, like a module's record from the subject panel; the
     * page's Save is for the layout.
     */
    public function storeStylePreset(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'style' => ['required', 'array'],
        ]);

        $preset = LayoutStylePreset::create([
            'theme' => LayoutPlacement::scopeTheme(),
            'name' => $data['name'],
            'style' => RowStyle::normalize($data['style']),
        ]);

        return response()->json(['id' => $preset->id, 'name' => $preset->name, 'style' => $preset->style]);
    }

    public function destroyStylePreset(LayoutStylePreset $preset)
    {
        abort_unless($preset->theme === LayoutPlacement::scopeTheme(), 404);
        $preset->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Arrangements the active theme ships in its layouts folder: a demo home
     * page, a starting point. Read-only; loading one makes editor rows like
     * any saved template.
     */
    private function shippedTemplates(): \Illuminate\Support\Collection
    {
        $dir = app(ThemeService::class)->getThemePath() . '/layouts';
        if (! is_dir($dir)) {
            return collect();
        }

        return collect(glob($dir . '/*.json'))->map(function ($file) {
            $data = json_decode((string) file_get_contents($file), true);
            if (! is_array($data) || empty($data['venta_layout']) || ! isset($data['rows'])) {
                return null;
            }

            return [
                'id' => 'theme:' . basename($file, '.json'),
                'name' => (string) ($data['name'] ?? ucfirst(basename($file, '.json'))) . ' (shipped)',
                'region' => $data['region'] ?? null,
                'rows' => LayoutTemplate::toUnits($data['rows']),
            ];
        })->filter()->values();
    }

    /**
     * The rows of one region kept under a name. Its own record on its own
     * control, like a style preset; loading it later is editor state.
     */
    public function storeTemplate(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'region' => ['nullable', 'string', 'max:64'],
            'rows' => ['required', 'array'],
        ]);

        $rows = LayoutTemplate::cleanRows($data['rows']);
        if ($rows === []) {
            return response()->json(['message' => 'Nothing in this position could be kept as a template.'], 422);
        }

        $template = LayoutTemplate::create([
            'theme' => LayoutPlacement::scopeTheme(),
            'name' => $data['name'],
            'region' => $data['region'] ?? null,
            'rows' => $rows,
        ]);

        return response()->json(['id' => $template->id, 'name' => $template->name, 'region' => $template->region, 'rows' => LayoutTemplate::toUnits($rows)]);
    }

    public function destroyTemplate(LayoutTemplate $template)
    {
        abort_unless($template->theme === LayoutPlacement::scopeTheme(), 404);
        $template->delete();

        return response()->json(['ok' => true]);
    }

    /** One region's rows as a file the merchant can carry to another store. */
    public function exportTemplate(Request $request)
    {
        $data = $request->validate(['region' => ['required', 'string', 'max:64'], 'rows' => ['required', 'string']]);
        $rows = LayoutTemplate::cleanRows(json_decode($data['rows'], true));

        $file = json_encode([
            'venta_layout' => 1,
            'theme' => LayoutPlacement::scopeTheme(),
            'region' => $data['region'],
            'rows' => $rows,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $name = 'layout-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($data['region'])) . '-' . now()->format('Ymd-His') . '.json';

        return response($file, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
        ]);
    }

    /**
     * A file back in. Nothing is written: the rows come back in the editor's
     * shape and land in the position the merchant chose, where Save decides.
     */
    public function importTemplate(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'max:2048']]);
        $data = json_decode((string) file_get_contents($request->file('file')->getRealPath()), true);

        if (! is_array($data) || empty($data['venta_layout']) || ! isset($data['rows'])) {
            return response()->json(['message' => 'Not a layout file.'], 422);
        }

        $units = LayoutTemplate::toUnits($data['rows']);
        if ($units === []) {
            return response()->json(['message' => 'The file holds no rows this store can place.'], 422);
        }

        return response()->json(['region' => $data['region'] ?? null, 'rows' => $units]);
    }

    /**
     * The rows of one region, written onto another page type as new rows
     * after that page's own. A write on its own control: the merchant is
     * copying somewhere they are not looking, and Save only covers here.
     */
    public function copyRegion(Request $request)
    {
        $data = $request->validate([
            'region' => ['required', 'string', 'max:64'],
            'target_page' => ['required', 'string', 'max:32'],
            'target_region' => ['nullable', 'string', 'max:64'],
            'rows' => ['required', 'array'],
        ]);

        $target = $data['target_page'];
        if ($target !== LayoutPlacement::EVERYWHERE && ! StorefrontPageType::exists($target)) {
            return response()->json(['message' => 'Unknown page type.'], 422);
        }
        $targetRegion = ($data['target_region'] ?? null) ?: $data['region'];
        if (! Regions::exists($targetRegion)) {
            return response()->json(['message' => 'This theme has no such position.'], 422);
        }

        $rows = LayoutTemplate::cleanRows($data['rows']);
        if ($rows === []) {
            return response()->json(['message' => 'Nothing here could be copied.'], 422);
        }

        $theme = LayoutPlacement::scopeTheme();
        $count = 0;

        DB::transaction(function () use ($rows, $theme, $target, $targetRegion, &$count) {
            $after = (int) LayoutRow::where('theme', $theme)->where('page_type', $target)->max('sort_order');
            $order = (int) LayoutPlacement::where('theme', $theme)->where('page_type', $target)->max('sort_order');

            foreach ($rows as $i => $row) {
                $layoutRow = LayoutRow::create([
                    'theme' => $theme, 'page_type' => $target, 'region' => $targetRegion,
                    'columns' => $row['columns'], 'style' => $row['style'], 'sort_order' => $after + 1 + $i, 'is_enabled' => $row['enabled'],
                ]);
                foreach ($row['placements'] as $p) {
                    LayoutPlacement::create([
                        'theme' => $theme, 'page_type' => $target, 'region' => $targetRegion, 'row_id' => $layoutRow->id,
                        'column_index' => $p['column'], 'widget' => $p['widget'], 'title' => $p['title'], 'config' => $p['config'],
                        'sort_order' => ++$order, 'is_enabled' => $p['enabled'],
                    ]);
                    $count++;
                }
            }
        });

        $where = $target === LayoutPlacement::EVERYWHERE ? 'all pages' : StorefrontPageType::label($target);

        return response()->json(['ok' => true, 'message' => count($rows) . ' ' . (count($rows) === 1 ? 'row' : 'rows') . ' with ' . $count . ' ' . ($count === 1 ? 'widget' : 'widgets') . ' copied to ' . $where . '.']);
    }

    /** The widget settings forms, refetched when the merchant comes back. */
    public function schemas()
    {
        return response()->json($this->schemaSet());
    }

    /**
     * Every widget's settings form, resolved the same way twice.
     *
     * This endpoint used to build its own, without the create_route mapping
     * the page does, so a refresh replaced every schema with one whose New
     * banner group link had no href. It was invisible for as long as the
     * refresh itself was broken, and became a live regression the moment that
     * was fixed. One resolver now, so the two cannot drift again.
     */
    private function schemaSet(): array
    {
        return \App\Support\LayoutInventory::schemaSet();
    }
}
