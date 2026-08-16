<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Currency;
use App\Models\FooterColumn;
use App\Models\HomepageSection;
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

        $sections = HomepageSection::orderBy('sort_order')->get();
        $navItems = NavMenuItem::orderBy('sort_order')->get();
        $footerColumns = FooterColumn::orderBy('sort_order')->get();
        $currencies = Currency::orderBy('is_default', 'desc')->orderBy('code')->get();
        $categories = Category::whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $installedThemes = $theme->getInstalledThemes();
        $activeTheme = $theme->getActiveTheme();
        $installedPresets = $theme->getInstalledPresets();
        $activePreset = $theme->getActivePreset();
        $themeSettingsSchema = $theme->getThemeSettingsSchema();

        $themeSettingValues = [];
        foreach ($themeSettingsSchema as $key => $schema) {
            $themeSettingValues[$key] = $theme->getThemeSetting($key, $schema['default'] ?? null);
        }

        return view('admin.settings.theme.index', compact(
            'settings', 'sections', 'navItems', 'footerColumns', 'currencies', 'categories',
            'installedThemes', 'activeTheme', 'installedPresets', 'activePreset',
            'themeSettingsSchema', 'themeSettingValues'
        ));
    }

    public function updateSettings(Request $request)
    {
        // v3 doctrine: one save carries every settings tab at once
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
}
