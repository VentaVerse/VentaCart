<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomepageSection;
use App\Services\ThemeService;
use Illuminate\Http\Request;

class HomepageSectionController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:banner,products,brands,recently_viewed,category_tabs,text_block,blog_posts',
            'title' => 'required|string|max:255',
        ]);

        $defaults = $this->defaultConfig($request->input('type'));
        $maxSort = HomepageSection::max('sort_order') ?? 0;

        HomepageSection::create([
            'type' => $request->input('type'),
            'title' => $request->input('title'),
            'config' => $defaults,
            'sort_order' => $maxSort + 1,
        ]);

        app(ThemeService::class)->clearCache();

        return back()->with('success', 'Section added.')->with('tab', 'homepage');
    }

    public function update(Request $request, HomepageSection $section)
    {
        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $section->update([
            'title' => $request->input('title'),
            'config' => $request->input('config', $section->config),
            'is_enabled' => $request->boolean('is_enabled'),
        ]);

        app(ThemeService::class)->clearCache();

        return back()->with('success', 'Section updated.')->with('tab', 'homepage');
    }

    public function destroy(HomepageSection $section)
    {
        $section->delete();
        app(ThemeService::class)->clearCache();

        return back()->with('success', 'Section deleted.')->with('tab', 'homepage');
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'order' => 'required|array',
            'order.*.id' => 'required|exists:homepage_sections,id',
            'order.*.sort_order' => 'required|integer',
        ]);

        foreach ($request->input('order') as $item) {
            HomepageSection::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        app(ThemeService::class)->clearCache();

        return response()->json(['success' => true]);
    }

    private function defaultConfig(string $type): array
    {
        return match ($type) {
            'banner' => ['width' => 'full', 'autoplay' => true, 'speed' => 5000, 'slides' => []],
            'products' => ['filter' => 'new', 'limit' => 12, 'columns_mobile' => 2, 'columns_tablet' => 3, 'columns_desktop' => 6],
            'brands' => ['per_page' => 8, 'autoplay' => true, 'speed' => 3000],
            'category_tabs' => ['categories' => [], 'limit' => 6, 'columns_mobile' => 2, 'columns_tablet' => 3, 'columns_desktop' => 6],
            'text_block' => ['content' => '', 'alignment' => 'center'],
            'blog_posts' => ['limit' => 4, 'columns_mobile' => 2, 'columns_tablet' => 3, 'columns_desktop' => 4],
            'recently_viewed' => [],
            default => [],
        };
    }
}
