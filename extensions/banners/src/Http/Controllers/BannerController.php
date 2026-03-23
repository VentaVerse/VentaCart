<?php

namespace Extensions\Banners\Http\Controllers;

use App\Http\Controllers\Controller;
use Extensions\Banners\Models\Banner;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function index()
    {
        $banners = Banner::ordered()->get();
        return view('banners::index', compact('banners'));
    }

    public function create()
    {
        $banner = null;
        return view('banners::editor', compact('banner'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateBanner($request);
        $banner = Banner::create($validated);

        return redirect()->route('admin.banners.edit', $banner)->with('success', 'Banner created.');
    }

    public function edit(Banner $banner)
    {
        return view('banners::editor', compact('banner'));
    }

    public function update(Request $request, Banner $banner)
    {
        $validated = $this->validateBanner($request);
        $banner->update($validated);

        return redirect()->route('admin.banners.edit', $banner)->with('success', 'Banner updated.');
    }

    public function destroy(Banner $banner)
    {
        $banner->delete();
        return redirect()->route('admin.banners.index')->with('success', 'Banner deleted.');
    }

    private function validateBanner(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'bg_image' => ['nullable', 'string', 'max:500'],
            'bg_image_mobile' => ['nullable', 'string', 'max:500'],
            'bg_color' => ['nullable', 'string', 'max:30'],
            'bg_size' => ['nullable', 'string', 'max:20'],
            'link' => ['nullable', 'string', 'max:500'],
            'link_new_tab' => ['nullable', 'boolean'],
            'layers' => ['nullable', 'string'],
            'height' => ['nullable', 'integer', 'min:100', 'max:2000'],
            'height_mobile' => ['nullable', 'integer', 'min:100', 'max:2000'],
            'full_width' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['link_new_tab'] = $request->boolean('link_new_tab');
        $validated['full_width'] = $request->boolean('full_width', true);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['height'] = $validated['height'] ?? 500;
        $validated['height_mobile'] = $validated['height_mobile'] ?? 400;
        $validated['layers'] = json_decode($validated['layers'] ?? '[]', true);
        $validated['bg_image'] = $validated['bg_image'] ?: null;
        $validated['bg_image_mobile'] = $validated['bg_image_mobile'] ?: null;

        return $validated;
    }
}
