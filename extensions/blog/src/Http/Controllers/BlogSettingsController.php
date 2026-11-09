<?php

namespace Extensions\Blog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\ThemeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * How the storefront blog lists its posts. Stored in the `blog` settings
 * group, which the storefront blog pages read.
 */
class BlogSettingsController extends Controller
{
    public function edit(): View
    {
        return view('blog::settings', [
            'settings' => Setting::where('group', 'blog')->pluck('value', 'key')->all(),
        ]);
    }

    public function update(Request $request, ThemeService $theme): RedirectResponse
    {
        $validated = $request->validate([
            'posts_per_page' => ['required', 'integer', 'min:4', 'max:48'],
            'default_view' => ['required', 'in:grid,list'],
            'excerpt_length' => ['required', 'integer', 'min:50', 'max:500'],
            'show_view_count' => ['nullable', 'boolean'],
            'show_related_articles' => ['nullable', 'boolean'],
        ]);

        foreach (['show_view_count', 'show_related_articles'] as $toggle) {
            $validated[$toggle] = $request->boolean($toggle) ? '1' : '0';
        }

        foreach ($validated as $key => $value) {
            Setting::updateOrCreate(['group' => 'blog', 'key' => $key], ['value' => (string) $value]);
        }
        $theme->clearCache();

        return redirect()->route('admin.blog.settings')->with('success', 'Blog settings saved.');
    }
}
