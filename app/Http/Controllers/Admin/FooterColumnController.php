<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FooterColumn;
use App\Services\ThemeService;
use Illuminate\Http\Request;

class FooterColumnController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:links,payment_icons,social,text',
        ]);

        $maxSort = FooterColumn::max('sort_order') ?? 0;
        $defaults = match ($request->input('type')) {
            'links' => ['items' => []],
            'payment_icons' => ['items' => []],
            'social' => ['items' => []],
            'text' => ['content' => ''],
        };

        FooterColumn::create([
            'title' => $request->input('title'),
            'type' => $request->input('type'),
            'config' => $defaults,
            'sort_order' => $maxSort + 1,
        ]);

        app(ThemeService::class)->clearCache();

        return back()->with('success', 'Footer column added.')->with('tab', 'footer');
    }

    public function update(Request $request, FooterColumn $column)
    {
        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $column->update([
            'title' => $request->input('title'),
            'config' => $request->input('config', $column->config),
            'is_enabled' => $request->boolean('is_enabled'),
        ]);

        app(ThemeService::class)->clearCache();

        return back()->with('success', 'Footer column updated.')->with('tab', 'footer');
    }

    public function destroy(FooterColumn $column)
    {
        $column->delete();
        app(ThemeService::class)->clearCache();

        return back()->with('success', 'Footer column deleted.')->with('tab', 'footer');
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'order' => 'required|array',
            'order.*.id' => 'required|exists:footer_columns,id',
            'order.*.sort_order' => 'required|integer',
        ]);

        foreach ($request->input('order') as $item) {
            FooterColumn::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        app(ThemeService::class)->clearCache();

        return response()->json(['success' => true]);
    }
}
