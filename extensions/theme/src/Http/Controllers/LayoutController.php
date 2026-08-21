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
        $slug = $theme->getActiveTheme();
        $themeName = $theme->getInstalledThemes()[$slug]['name'] ?? $slug;
        $theme = $slug;

        $counts = LayoutPlacement::query()
            ->where('theme', $theme)
            ->selectRaw('page_type, COUNT(*) as n')
            ->groupBy('page_type')
            ->pluck('n', 'page_type')
            ->all();

        return view('theme::layout', [
            'theme' => $themeName,
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
                : LayoutPlacement::where('theme', $theme)
                    ->where('page_type', LayoutPlacement::EVERYWHERE)
                    ->orderBy('sort_order')->get(),
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

    /** Everything placed on this page type, grouped by region. */
    private function placements(string $pageType): object
    {
        $rows = LayoutPlacement::where('theme', LayoutPlacement::scopeTheme())
            ->where('page_type', $pageType)
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

        // An OBJECT, always. An empty PHP array serialises to [], which is a JS
        // Array, and assigning a string key to it never triggers Alpine's
        // reactivity: the hidden payload input kept its load-time value, so
        // every save on a page with no placements yet wrote nothing and
        // reported success. That was all 11 page types on a fresh install.
        return (object) $out;
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
                'shape' => $spec['shape'] ?? null,
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

        // Written by this save, and counted in what the merchant is told.
        $kept = [];
        // Protected from the sweep, and deliberately NOT counted: telling a
        // merchant "3 widgets placed" when one of them is a banner they cannot
        // currently see is how a screen earns distrust.
        $spared = [];

        try {
            DB::transaction(function () use ($data, $pageType, $rows, &$kept, &$spared) {
            // Placements this screen can show but cannot send back: a widget
            // whose module is switched off, and a region this theme no longer
            // declares. The editor renders both (a Module off chip, the
            // orphans block) and PROMISES both are kept. Without this they
            // fell through the loop below, never reached $kept, and the sweep
            // deleted them. A merchant who switched Banners off for an
            // afternoon, tidied their homepage, and switched it back on had
            // lost the placement and its config, under a success message.
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
                        'theme' => LayoutPlacement::scopeTheme(),
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

            // A payload we could not read a single widget out of is not an
            // instruction to empty the page, it is a payload we could not
            // read. Refusing the whole save here is the difference between
            // an error message and an error message arriving after the work
            // was already destroyed. An empty placements list is a different
            // thing entirely and still empties the page, below.
            if ($kept === [] && $rows->isNotEmpty()) {
                throw new RefusedSave();
            }

            // Whatever this page type had and the editor did not send back was
            // removed on screen. Scoped to this page type, so nothing else moves.
            //
            // Always constrained, never conditionally. The old guard skipped
            // whereNotIn when nothing was kept, so a save where every row was
            // refused deleted the entire page type and then reported an error
            // about a transaction that had already committed. An empty $kept
            // now means exactly what it says: the merchant emptied the page.
            LayoutPlacement::where('theme', LayoutPlacement::scopeTheme())
                ->where('page_type', $pageType)
                ->whereNotIn('id', array_merge($kept, $spared))
                ->delete();
            });
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
        return collect(Widgets::all())->keys()
            ->mapWithKeys(fn ($key) => [$key => array_map(function ($field) {
                // A named route becomes a URL here, so the view never resolves
                // routes and a missing one cannot 500 the editor.
                if (! empty($field['create_route']) && \Illuminate\Support\Facades\Route::has($field['create_route'])) {
                    $field['create_url'] = route($field['create_route']);
                }
                unset($field['create_route']);

                return $field;
            }, Widgets::schema($key))])->all();
    }
}
