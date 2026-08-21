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
            // Derived from the name unless the merchant insists. Asking someone
            // who wants a picture on a page to invent a URL slug first is where
            // this flow lost people.
            'slug' => 'nullable|string|max:255|unique:banner_groups,slug',
            // The first image, uploaded here. Creating a group and then hunting
            // for Slides was three pages of ceremony before anything appeared.
            'image' => 'nullable|image|max:8192',
            'link' => 'nullable|string|max:2000',
            'autoplay' => 'boolean',
            'speed' => 'required|integer|min:1000|max:30000',
            'height' => 'required|integer|min:100|max:2000',
            'height_mobile' => 'required|integer|min:100|max:2000',
            'full_width' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $validated['autoplay'] = $request->boolean('autoplay');
        $validated['full_width'] = $request->boolean('full_width', true);
        $validated['is_active'] = $request->boolean('is_active');
        // ?? null, not just ?:. A nullable field the merchant left out never
        // reaches $validated at all, so the key is missing rather than empty.
        $validated['slug'] = Str::slug(($validated['slug'] ?? null) ?: $validated['name']);

        $image = $request->file('image');
        $link = $validated['link'] ?? null;
        unset($validated['image'], $validated['link']);

        $group = BannerGroup::create($validated);

        // One image is one banner. The merchant leaves with something that
        // renders, instead of an empty group and three more pages to visit.
        if ($image) {
            $path = $image->store('banners', 'public');

            $group->slides()->create([
                'name' => $validated['name'],
                'bg_image' => $path,
                'link' => $link,
                'sort_order' => 0,
                'is_active' => true,
            ]);

            return redirect()
                ->route('admin.banners.slides', $group)
                ->with('success', 'Banner created with its first image.');
        }

        return redirect()->route('admin.banners.slides', $group)
            ->with('success', 'Banner group created. Add an image to it.');
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
            'height' => 'required|integer|min:100|max:2000',
            'height_mobile' => 'required|integer|min:100|max:2000',
            'full_width' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $validated['autoplay'] = $request->boolean('autoplay');
        $validated['full_width'] = $request->boolean('full_width', true);
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
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['link_new_tab'] = $request->boolean('link_new_tab');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['layers'] = json_decode($validated['layers'] ?? '[]', true);
        $validated['bg_image'] = $validated['bg_image'] ?: null;
        $validated['bg_image_mobile'] = $validated['bg_image_mobile'] ?: null;

        return $validated;
    }
}
