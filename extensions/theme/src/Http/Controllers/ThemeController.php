<?php

namespace Extensions\Theme\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Currency;
use App\Models\FooterColumn;
use App\Models\NavMenuItem;
use App\Models\Setting;
use App\Services\ThemeService;
use Illuminate\Http\Request;

class ThemeController extends Controller
{
    public function index(ThemeService $theme)
    {
        $settings = [];
        foreach (Setting::all() as $s) {
            $settings[$s->group][$s->key] = $s->value;
        }

        $navItems = NavMenuItem::orderBy('sort_order')->get();
        $footerColumns = FooterColumn::orderBy('sort_order')->get();
        $currencies = Currency::orderBy('is_default', 'desc')->orderBy('code')->get();
        $categories = Category::whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $installedPresets = $theme->getInstalledPresets();
        $activePreset = $theme->getActivePreset();
        $themeSettingsSchema = $theme->getThemeSettingsSchema();

        $themeSettingValues = [];
        foreach ($themeSettingsSchema as $key => $schema) {
            $themeSettingValues[$key] = $theme->getThemeSetting($key, $schema['default'] ?? null);
        }

        return view('theme::index', compact(
            'settings', 'navItems', 'footerColumns', 'currencies', 'categories',
            'installedPresets', 'activePreset',
            'themeSettingsSchema', 'themeSettingValues'
        ));
    }

    public function updateSettings(Request $request)
    {
        // v doctrine: one save carries every settings tab at once
        // (groups[colors][...], groups[fonts][...], ...). Per-theme
        // snapshots still happen at switch time, unchanged.
        if (is_array($request->input('groups'))) {
            foreach ($request->input('groups') as $group => $data) {
                if (!is_array($data)) {
                    continue;
                }
                foreach ($data as $key => $value) {
                    Setting::updateOrCreate(
                        ['group' => $group, 'key' => $key],
                        ['value' => is_array($value) ? json_encode($value) : $value]
                    );
                }
            }

            app(ThemeService::class)->clearCache();

            return back()->with('success', 'Theme settings saved.');
        }

        $group = $request->input('group');
        $data = $request->input('settings', []);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            Setting::updateOrCreate(
                ['group' => $group, 'key' => $key],
                ['value' => $value]
            );
        }

        // Handle additional groups submitted in the same form (e.g. filters within category_page)
        $filters = $request->input('filters', []);
        if (!empty($filters)) {
            foreach ($filters as $key => $value) {
                Setting::updateOrCreate(
                    ['group' => 'filters', 'key' => $key],
                    ['value' => $value]
                );
            }
        }

        app(ThemeService::class)->clearCache();

        return back()->with('success', ucfirst($group) . ' settings updated.')->with('tab', $group);
    }

    public function switchTheme(Request $request, ThemeService $theme)
    {
        $request->validate(['preset' => 'required|string']);

        $slug = $request->input('preset');
        $presets = $theme->getInstalledPresets();
        if (!isset($presets[$slug])) {
            return back()->with('error', 'Preset not found.');
        }

        $theme->applyPreset($slug);

        return back()->with('success', 'Storefront look switched to ' . ($presets[$slug]['name'] ?? $slug) . '.');
    }

    public function storePreset(Request $request, ThemeService $theme)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:200'],
        ]);

        try {
            $slug = $theme->saveCurrentAsPreset($validated['name'], $validated['description'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "The live look is saved as the '{$slug}' preset.");
    }

    public function destroyPreset(string $slug, ThemeService $theme)
    {
        try {
            $theme->deletePreset($slug);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Preset deleted.');
    }

    public function exportPreset(string $slug, ThemeService $theme)
    {
        $preset = $theme->getInstalledPresets()[$slug] ?? null;
        if (!$preset) {
            abort(404);
        }

        unset($preset['slug']);

        return response()->streamDownload(
            fn () => print(json_encode($preset, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)),
            "venta-preset-{$slug}.json",
            ['Content-Type' => 'application/json']
        );
    }

    public function importPreset(Request $request, ThemeService $theme)
    {
        $request->validate([
            'preset_file' => ['required', 'file', 'max:512', 'mimetypes:application/json,text/plain'],
        ]);

        try {
            $slug = $theme->importPreset($request->file('preset_file')->get());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Preset '{$slug}' imported.");
    }

    public function revertThemeColors(ThemeService $themeService)
    {
        $activeTheme = $themeService->getActiveTheme();
        $themePath = resource_path("themes/{$activeTheme}/theme.json");

        if (!file_exists($themePath)) {
            return back()->with('error', 'Theme config not found.');
        }

        $themeJson = json_decode(file_get_contents($themePath), true);
        $preset = $themeService->getInstalledPresets()[$themeService->getActivePreset()] ?? [];

        // The preset's factory look: theme.json base with the preset overlaid
        $colors = array_merge($themeJson['colors'] ?? [], $preset['colors'] ?? []);
        $fonts = array_merge($themeJson['fonts'] ?? [], $preset['fonts'] ?? []);
        $presetSlug = $preset['slug'] ?? null;

        if (!empty($colors)) {
            // Clear and rewrite, removing orphan keys from old color systems
            Setting::where('group', 'colors')->delete();
            foreach ($colors as $key => $value) {
                Setting::create(['group' => 'colors', 'key' => $key, 'value' => $value]);
            }
        }

        Setting::where('group', 'fonts')->delete();
        foreach ($fonts as $key => $value) {
            Setting::create(['group' => 'fonts', 'key' => $key, 'value' => $value]);
        }

        if ($presetSlug) {
            Setting::where('group', "colors__{$presetSlug}")->delete();
            Setting::where('group', "fonts__{$presetSlug}")->delete();
        }

        $themeService->clearCache();

        return back()->with('success', 'Colors and fonts reverted to the preset defaults.');
    }

    public function updateThemeSettings(Request $request)
    {
        $themeService = app(ThemeService::class);
        $schema = $themeService->getThemeSettingsSchema();

        foreach ($request->input('theme_settings', []) as $key => $value) {
            if (array_key_exists($key, $schema)) {
                $themeService->setThemeSetting($key, $value);
            }
        }

        $themeService->clearCache();

        return back()->with('success', 'Theme settings updated.')->with('tab', 'theme-settings');
    }

    /**
     * The bench: the storefront in a frame, with the controls beside it.
     *
     * A dedicated full-width surface rather than a panel in the Colors tab.
     * Two deliberate exceptions to the admin record are taken here and nowhere
     * else: a second chrome, and the 1080 centered measure. A preview whose
     * whole purpose is showing a real storefront cannot be shown at 600px, or
     * the merchant spends the session styling a tablet breakpoint they never
     * chose.
     */
    public function preview(ThemeService $theme)
    {
        $meta = json_decode((string) @file_get_contents($theme->getThemePath() . '/theme.json'), true) ?: [];
        $defaults = $meta['colors'] ?? [];

        $values = [];
        foreach (\Extensions\Theme\ColorGroups::tokens() as $token) {
            $values[$token] = $theme->get("colors.{$token}", $defaults[$token] ?? '#000000');
        }

        $overrides = [];
        foreach (array_keys(\App\Support\StorefrontPageType::TYPES) as $type) {
            $set = $theme->pageOverrides($type, 'colors');
            if ($set !== []) {
                $overrides[$type] = $set;
            }
        }

        return view('theme::preview', [
            'groups' => \Extensions\Theme\ColorGroups::all(),
            'values' => $values,
            'overrides' => $overrides,
            'pageTypes' => \App\Support\StorefrontPageType::TYPES,
            // The ledger. Without it a merchant can only discover that a page
            // type has drifted by standing on it, which is the drift the pin
            // was meant to prevent.
            'overrideCounts' => $theme->pageOverrideCounts(),
        ]);
    }

    /**
     * One save carries both scopes, because one editing session crosses them:
     * a merchant fixes the store's muted grey, then pins the account cards.
     * Splitting it into two submits would mean two save bars for one intent.
     */
    public function savePreview(Request $request, ThemeService $theme)
    {
        $data = json_decode((string) $request->input('payload'), true);

        if (! is_array($data)) {
            return back()->with('error', 'The editor sent nothing this save could apply.');
        }

        $known = \Extensions\Theme\ColorGroups::tokens();
        $globals = array_intersect_key((array) ($data['global'] ?? []), array_flip($known));

        if ($globals !== []) {
            $theme->setMany('colors', $globals);
        }

        // Counted separately, because a merchant needs to know WHICH of the two
        // things happened. "14 colours saved" is what you write when you cannot
        // describe the change; a store-wide edit and a change confined to the
        // account pages are not the same event and must not read the same.
        $pinned = 0;
        $cleared = 0;
        $pages = [];

        foreach ((array) ($data['pages'] ?? []) as $type => $set) {
            if (! \App\Support\StorefrontPageType::exists($type)) {
                continue;
            }

            $set = array_intersect_key((array) $set, array_flip($known));
            if ($set === []) {
                continue;
            }

            $before = count($theme->pageOverrides($type, 'colors'));
            $theme->setPageOverrides($type, 'colors', $set);
            $after = count($theme->pageOverrides($type, 'colors'));

            $pinned += max(0, $after - $before);
            $cleared += max(0, $before - $after);

            if ($after > 0) {
                $pages[] = \App\Support\StorefrontPageType::label($type);
            }
        }

        $theme->clearCache();

        return back()->with('success', $this->savedMessage(count($globals), $pinned, $cleared, $pages));
    }

    /** States what actually happened, in the merchant's own terms. */
    private function savedMessage(int $store, int $pinned, int $cleared, array $pages): string
    {
        $parts = [];

        if ($store > 0) {
            $parts[] = $store . ' store ' . ($store === 1 ? 'colour' : 'colours');
        }

        if ($pinned > 0) {
            $parts[] = $pinned . ' ' . ($pinned === 1 ? 'colour' : 'colours') . ' pinned to '
                . (count($pages) === 1 ? $pages[0] : count($pages) . ' page types');
        }

        if ($cleared > 0) {
            $parts[] = $cleared . ' page ' . ($cleared === 1 ? 'override' : 'overrides') . ' cleared';
        }

        return $parts === [] ? 'Nothing changed.' : ucfirst(implode(', ', $parts)) . ' saved.';
    }

    /**
     * The theme directory: what is installed, and which one the storefront runs.
     *
     * The extension's home, like every other extension page. Going straight
     * into the editor made the platform look like it had one possible
     * appearance, which is the opposite of what a theme system is for. With a
     * single theme installed this is one card, and that is worth showing: it
     * tells a merchant a second theme is a thing that can exist.
     */
    public function directory(ThemeService $theme)
    {
        return view('theme::directory', [
            'themes' => $theme->getAvailableThemes(),
            'active' => $theme->getActiveTheme(),
            'live' => $theme->hasActiveTheme(),
        ]);
    }

    /**
     * Install a theme package found on disk. The kernel checks the contract
     * and refuses a package that would not draw every page.
     */
    public function install(Request $request, ThemeService $theme, \App\Services\ExtensionManager $extensions)
    {
        $slug = (string) $request->input('theme');
        $meta = $theme->getAvailableThemes()[$slug] ?? null;

        if (! $meta) {
            return back()->with('error', 'That theme is not on disk.');
        }

        try {
            $extensions->install(ThemeService::registryName($slug));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $theme->clearCache();

        return back()->with('success', $meta['name'] . ' is installed.');
    }

    /**
     * Uninstall a theme package. Its folder stays; its layout rows and
     * settings stay too, so installing it again finds them. Uninstalling
     * the live theme leaves the storefront on core's plain page until
     * another theme is made live.
     */
    public function uninstall(Request $request, ThemeService $theme, \App\Services\ExtensionManager $extensions)
    {
        $slug = (string) $request->input('theme');
        $meta = $theme->getAvailableThemes()[$slug] ?? null;

        if (! $meta || ! $meta['installed']) {
            return back()->with('error', 'That theme is not installed.');
        }

        $extensions->uninstall(ThemeService::registryName($slug));
        $theme->clearCache();

        return back()->with(
            $slug === $theme->getActiveTheme() ? 'warning' : 'success',
            $meta['name'] . ' is uninstalled.' . ($slug === $theme->getActiveTheme() ? ' The storefront shows "No theme installed" until a theme is made live.' : '')
        );
    }

    /**
     * Point the storefront at another theme.
     *
     * Layout placements are NOT touched. They are store data keyed by page
     * type, and they survive a theme switch by design; a region the new theme
     * does not declare simply stops rendering and is reported as orphaned.
     * Deleting a merchant's layout because they tried a theme is how you teach
     * them never to try another one.
     */
    public function activate(Request $request, ThemeService $theme)
    {
        $slug = (string) $request->input('theme');

        try {
            $theme->activateTheme($slug);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $orphans = \App\Support\Regions::orphans()->count();

        return redirect()->route('admin.theme.index')->with(
            'success',
            'The storefront now runs ' . ($theme->getInstalledThemes()[$slug]['name'] ?? $slug) . '.'
                . ($orphans > 0
                    ? ' ' . $orphans . ' placement' . ($orphans === 1 ? '' : 's')
                        . ' sit in regions this theme does not have, and are waiting in Layout.'
                    : '')
        );
    }
}
