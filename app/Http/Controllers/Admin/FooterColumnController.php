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

    public function bulkUpdate(Request $request)
    {
        $columns = $request->input('columns', []);
        $allUploads = $request->file('icon_uploads', []);

        foreach ($columns as $id => $data) {
            $column = FooterColumn::find($id);
            if (!$column) continue;

            $config = $data['config'] ?? [];

            // Clean up links
            if (isset($config['links'])) {
                $config['links'] = array_values(array_filter($config['links'], fn($l) => !empty($l['label']) || !empty($l['url'])));
            }

            // Handle payment icon uploads
            if (isset($config['icons']) && $column->type === 'payment_icons') {
                $uploads = $allUploads[$id] ?? [];
                foreach ($config['icons'] as $idx => &$icon) {
                    if (is_string($icon)) {
                        $icon = ['type' => 'text', 'value' => $icon];
                    }
                    if (isset($uploads[$idx])) {
                        $icon['value'] = $uploads[$idx]->store('payment-icons', 'public');
                        $icon['type'] = 'image';
                    }
                }
                unset($icon);
                $config['icons'] = array_values(array_filter($config['icons'], fn($i) => !empty($i['value'])));
            }

            $column->update([
                'title' => $data['title'] ?? $column->title,
                'config' => !empty($config) ? $config : $column->config,
                'is_enabled' => (bool) ($data['is_enabled'] ?? false),
            ]);
        }

        app(ThemeService::class)->clearCache();

        return back()->with('success', 'Footer updated.')->with('tab', 'footer');
    }

    public function update(Request $request, FooterColumn $column)
    {
        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $config = $request->input('config', []);

        // Clean up: remove empty links/socials
        if (isset($config['links'])) {
            $config['links'] = array_values(array_filter($config['links'], function ($link) {
                return !empty($link['label']) || !empty($link['url']);
            }));
        }

        // Handle payment icon uploads
        if (isset($config['icons']) && $column->type === 'payment_icons') {
            $uploads = $request->file('icon_uploads', []);
            foreach ($config['icons'] as $idx => &$icon) {
                if (is_string($icon)) {
                    $icon = ['type' => 'text', 'value' => $icon];
                }
                if (isset($uploads[$idx])) {
                    $path = $uploads[$idx]->store('payment-icons', 'public');
                    $icon['value'] = $path;
                    $icon['type'] = 'image';
                }
            }
            unset($icon);
            $config['icons'] = array_values(array_filter($config['icons'], function ($icon) {
                return !empty($icon['value']);
            }));
        } elseif (isset($config['icons'])) {
            $config['icons'] = array_values(array_filter($config['icons']));
        }

        $column->update([
            'title' => $request->input('title'),
            'config' => !empty($config) ? $config : $column->config,
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
