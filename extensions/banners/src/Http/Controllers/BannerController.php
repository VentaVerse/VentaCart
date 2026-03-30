<?php

namespace Extensions\Banners\Http\Controllers;

use App\Http\Controllers\Controller;
use Extensions\Banners\Models\Banner;
use Extensions\Banners\Models\BannerGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BannerController extends Controller
{
    // --- Groups ---

    public function index()
    {
        $groups = BannerGroup::withCount('slides')->orderBy('name')->get();
        return view('banners::index', compact('groups'));
    }

    public function createGroup()
    {
        return view('banners::group-form', ['group' => null]);
    }

    public function storeGroup(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:banner_groups,slug',
            'autoplay' => 'boolean',
            'speed' => 'required|integer|min:1000|max:30000',
            'is_active' => 'boolean',
        ]);

        $validated['autoplay'] = $request->boolean('autoplay');
        $validated['is_active'] = $request->boolean('is_active');
        $validated['slug'] = Str::slug($validated['slug']);

        BannerGroup::create($validated);

        return redirect()->route('admin.banners.index')->with('success', 'Banner group created.');
    }

    public function editGroup(BannerGroup $group)
    {
        return view('banners::group-form', compact('group'));
    }

    public function updateGroup(Request $request, BannerGroup $group)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:banner_groups,slug,' . $group->id,
            'autoplay' => 'boolean',
            'speed' => 'required|integer|min:1000|max:30000',
            'is_active' => 'boolean',
        ]);

        $validated['autoplay'] = $request->boolean('autoplay');
        $validated['is_active'] = $request->boolean('is_active');
        $validated['slug'] = Str::slug($validated['slug']);

        $group->update($validated);

        return redirect()->route('admin.banners.index')->with('success', 'Banner group updated.');
    }

    public function destroyGroup(BannerGroup $group)
    {
        $group->slides()->delete();
        $group->delete();

        return redirect()->route('admin.banners.index')->with('success', 'Banner group and all slides deleted.');
    }

    // --- Slides ---

    public function slides(BannerGroup $group)
    {
        $banners = $group->slides;
        return view('banners::slides', compact('group', 'banners'));
    }

    public function create(BannerGroup $group)
    {
        $banner = null;
        return view('banners::editor', compact('group', 'banner'));
    }

    public function store(Request $request, BannerGroup $group)
    {
        $validated = $this->validateBanner($request);
        $validated['banner_group_id'] = $group->id;
        $banner = Banner::create($validated);

        return redirect()->route('admin.banners.slides.edit', [$group, $banner])->with('success', 'Slide created.');
    }

    public function edit(BannerGroup $group, Banner $banner)
    {
        return view('banners::editor', compact('group', 'banner'));
    }

    public function update(Request $request, BannerGroup $group, Banner $banner)
    {
        $validated = $this->validateBanner($request);
        $banner->update($validated);

        $layerIndex = $request->input('_selected_layer');
        $url = route('admin.banners.slides.edit', [$group, $banner]);
        if ($layerIndex !== null && $layerIndex !== '') {
            $url .= '#layer-' . $layerIndex;
        }

        return redirect($url)->with('success', 'Slide updated.');
    }

    public function duplicate(BannerGroup $group, Banner $banner)
    {
        $copy = $banner->replicate();
        $copy->name = ($banner->name ?: 'Untitled') . ' (Copy)';
        $copy->is_active = false;
        $copy->save();

        return redirect()->route('admin.banners.slides.edit', [$group, $copy])->with('success', 'Slide duplicated.');
    }

    public function destroy(BannerGroup $group, Banner $banner)
    {
        $banner->delete();
        return redirect()->route('admin.banners.slides', $group)->with('success', 'Slide deleted.');
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
