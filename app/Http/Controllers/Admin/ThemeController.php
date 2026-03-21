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

    public function switchTheme(Request $request)
    {
        $request->validate(['theme' => 'required|string']);

        $theme = $request->input('theme');
        $themePath = resource_path("themes/{$theme}/theme.json");

        if (!file_exists($themePath)) {
            return back()->with('error', 'Theme not found.');
        }

        $themeService = app(ThemeService::class);
        $themeService->set('identity', 'active_theme', $theme);
        $themeService->clearCache();

        return back()->with('success', 'Theme switched successfully.');
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
