<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\NavMenuItem;
use App\Services\ThemeService;
use Illuminate\Http\Request;

class NavMenuController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'label' => 'required|string|max:255',
            'type' => 'required|in:category,custom',
            'category_id' => 'required_if:type,category|nullable|exists:categories,id',
            'url' => 'required_if:type,custom|nullable|string|max:255',
        ]);

        $maxSort = NavMenuItem::max('sort_order') ?? 0;

        NavMenuItem::create([
            'label' => $request->input('label'),
            'type' => $request->input('type'),
            'category_id' => $request->input('type') === 'category' ? $request->input('category_id') : null,
            'url' => $request->input('type') === 'custom' ? $request->input('url') : null,
            'new_tab' => $request->boolean('new_tab'),
            'sort_order' => $maxSort + 1,
        ]);

        app(ThemeService::class)->clearCache();

        return back()->with('success', 'Menu item added.')->with('tab', 'navigation');
    }

    public function edit(NavMenuItem $item)
    {
        $categories = \App\Models\Category::whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('admin.settings.theme.nav-item', compact('item', 'categories'));
    }

    public function update(Request $request, NavMenuItem $item)
    {
        $request->validate([
            'label' => 'required|string|max:255',
        ]);

        $item->update([
            'label' => $request->input('label'),
            'category_id' => $request->input('type') === 'category' ? $request->input('category_id') : null,
            'url' => $request->input('type') === 'custom' ? $request->input('url') : null,
            'new_tab' => $request->boolean('new_tab'),
            'is_enabled' => $request->boolean('is_enabled'),
        ]);

        app(ThemeService::class)->clearCache();

        return redirect()->route('admin.theme.index', ['tab' => 'navigation'])->with('success', 'Menu item updated.');
    }

    public function destroy(NavMenuItem $item)
    {
        $item->delete();
        app(ThemeService::class)->clearCache();

        return back()->with('success', 'Menu item deleted.')->with('tab', 'navigation');
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'order' => 'required|array',
            'order.*.id' => 'required|exists:nav_menu_items,id',
            'order.*.sort_order' => 'required|integer',
        ]);

        foreach ($request->input('order') as $item) {
            NavMenuItem::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        app(ThemeService::class)->clearCache();

        return response()->json(['success' => true]);
    }
}
