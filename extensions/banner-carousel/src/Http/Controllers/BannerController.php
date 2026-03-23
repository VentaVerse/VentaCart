<?php

namespace Extensions\BannerCarousel\Http\Controllers;

use App\Http\Controllers\Controller;
use Extensions\BannerCarousel\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    public function index()
    {
        $banners = Banner::ordered()->get();
        return view('banner-carousel::index', compact('banners'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'button_text' => ['nullable', 'string', 'max:100'],
            'button_url' => ['nullable', 'string', 'max:500'],
            'button_new_tab' => ['nullable', 'boolean'],
            'text_color' => ['nullable', 'string', 'max:7'],
            'overlay_color' => ['nullable', 'string', 'max:30'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'image' => ['required', 'string', 'max:500'],
            'image_mobile' => ['nullable', 'string', 'max:500'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['button_new_tab'] = $request->boolean('button_new_tab');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['image_mobile'] = $validated['image_mobile'] ?: null;

        Banner::create($validated);

        return redirect()->route('admin.banners.index')->with('success', 'Banner created.');
    }

    public function update(Request $request, Banner $banner)
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'button_text' => ['nullable', 'string', 'max:100'],
            'button_url' => ['nullable', 'string', 'max:500'],
            'button_new_tab' => ['nullable', 'boolean'],
            'text_color' => ['nullable', 'string', 'max:7'],
            'overlay_color' => ['nullable', 'string', 'max:30'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'image' => ['required', 'string', 'max:500'],
            'image_mobile' => ['nullable', 'string', 'max:500'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['button_new_tab'] = $request->boolean('button_new_tab');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['image_mobile'] = $validated['image_mobile'] ?: null;

        $banner->update($validated);

        return redirect()->route('admin.banners.index')->with('success', 'Banner updated.');
    }

    public function destroy(Banner $banner)
    {
        if ($banner->image) {
            Storage::disk('public')->delete($banner->image);
        }
        if ($banner->image_mobile) {
            Storage::disk('public')->delete($banner->image_mobile);
        }
        $banner->delete();

        return redirect()->route('admin.banners.index')->with('success', 'Banner deleted.');
    }

    public function reorder(Request $request)
    {
        $request->validate(['order' => 'required|array', 'order.*' => 'integer']);

        foreach ($request->order as $position => $id) {
            Banner::where('id', $id)->update(['sort_order' => $position]);
        }

        return response()->json(['ok' => true]);
    }
}
