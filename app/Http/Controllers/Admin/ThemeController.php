<?php

namespace App\Http\Controllers\Admin;

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
        $themeSettingsSchema = $theme->getThemeSettingsSchema();

        $themeSettingValues = [];
        foreach ($themeSettingsSchema as $key => $schema) {
            $themeSettingValues[$key] = $theme->getThemeSetting($key, $schema['default'] ?? null);
        }

        return view('admin.theme.index', compact(
            'settings', 'sections', 'navItems', 'footerColumns', 'currencies', 'categories',
            'installedThemes', 'activeTheme', 'themeSettingsSchema', 'themeSettingValues'
        ));
    }

    public function updateSettings(Request $request)
    {
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

    public function uploadLogo(Request $request)
    {
        $request->validate(['logo' => 'required|image|max:2048']);

        $path = $request->file('logo')->store('theme', 'public');

        Setting::updateOrCreate(
            ['group' => 'identity', 'key' => 'logo'],
            ['value' => $path]
        );

        app(ThemeService::class)->clearCache();

        return redirect()->route('admin.settings.index')->with('success', 'Logo uploaded.')->with('tab', 'general');
    }

    public function uploadFavicon(Request $request)
    {
        $request->validate(['favicon' => 'required|file|mimes:ico,png,svg|max:512']);

        $path = $request->file('favicon')->store('theme', 'public');

        Setting::updateOrCreate(
            ['group' => 'identity', 'key' => 'favicon'],
            ['value' => $path]
        );

        app(ThemeService::class)->clearCache();

        return redirect()->route('admin.settings.index')->with('success', 'Favicon uploaded.')->with('tab', 'general');
    }

    public function removeLogo()
    {
        Setting::where('group', 'identity')->where('key', 'logo')->delete();
        app(ThemeService::class)->clearCache();
        return redirect()->route('admin.settings.index', ['tab' => 'general'])->with('success', 'Logo removed.');
    }

    public function removeFavicon()
    {
        Setting::where('group', 'identity')->where('key', 'favicon')->delete();
        app(ThemeService::class)->clearCache();
        return redirect()->route('admin.settings.index', ['tab' => 'general'])->with('success', 'Favicon removed.');
    }

    public function switchTheme(Request $request)
    {
        $request->validate(['theme' => 'required|string']);

        $newTheme = $request->input('theme');
        $themePath = resource_path("themes/{$newTheme}/theme.json");

        if (!file_exists($themePath)) {
            return back()->with('error', 'Theme not found.');
        }

        $themeService = app(ThemeService::class);
        $oldTheme = $themeService->getActiveTheme();

        // Save current colors under the old theme's key
        $currentColors = Setting::where('group', 'colors')->pluck('value', 'key')->all();
        $currentFontsUrl = Setting::where('group', 'fonts')->where('key', 'google_fonts_url')->value('value');
        if (!empty($currentColors)) {
            foreach ($currentColors as $key => $value) {
                Setting::updateOrCreate(
                    ['group' => "colors__{$oldTheme}", 'key' => $key],
                    ['value' => $value]
                );
            }
        }
        if ($currentFontsUrl) {
            Setting::updateOrCreate(
                ['group' => "fonts__{$oldTheme}", 'key' => 'google_fonts_url'],
                ['value' => $currentFontsUrl]
            );
        }

        // Load saved colors for the new theme, or fall back to theme.json defaults
        $themeJson = json_decode(file_get_contents($themePath), true);
        $savedColors = Setting::where('group', "colors__{$newTheme}")->pluck('value', 'key')->all();
        $savedFontsUrl = Setting::where('group', "fonts__{$newTheme}")->where('key', 'google_fonts_url')->value('value');

        $colors = !empty($savedColors) ? $savedColors : ($themeJson['colors'] ?? []);
        $fontsUrl = $savedFontsUrl ?: ($themeJson['fonts']['url'] ?? null);

        foreach ($colors as $key => $value) {
            Setting::updateOrCreate(
                ['group' => 'colors', 'key' => $key],
                ['value' => $value]
            );
        }

        if ($fontsUrl) {
            Setting::updateOrCreate(
                ['group' => 'fonts', 'key' => 'google_fonts_url'],
                ['value' => $fontsUrl]
            );
        }

        $themeService->set('identity', 'active_theme', $newTheme);
        $themeService->clearCache();

        return back()->with('success', 'Theme switched to ' . ($themeJson['name'] ?? $newTheme) . '.');
    }

    public function revertThemeColors()
    {
        $themeService = app(ThemeService::class);
        $activeTheme = $themeService->getActiveTheme();
        $themePath = resource_path("themes/{$activeTheme}/theme.json");

        if (!file_exists($themePath)) {
            return back()->with('error', 'Theme config not found.');
        }

        $themeJson = json_decode(file_get_contents($themePath), true);

        if (!empty($themeJson['colors'])) {
            foreach ($themeJson['colors'] as $key => $value) {
                Setting::updateOrCreate(
                    ['group' => 'colors', 'key' => $key],
                    ['value' => $value]
                );
            }
            // Also update the saved copy
            foreach ($themeJson['colors'] as $key => $value) {
                Setting::updateOrCreate(
                    ['group' => "colors__{$activeTheme}", 'key' => $key],
                    ['value' => $value]
                );
            }
        }

        if (!empty($themeJson['fonts']['url'])) {
            Setting::updateOrCreate(
                ['group' => 'fonts', 'key' => 'google_fonts_url'],
                ['value' => $themeJson['fonts']['url']]
            );
            Setting::updateOrCreate(
                ['group' => "fonts__{$activeTheme}", 'key' => 'google_fonts_url'],
                ['value' => $themeJson['fonts']['url']]
            );
        }

        $themeService->clearCache();

        return back()->with('success', 'Colors reverted to theme defaults.');
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
