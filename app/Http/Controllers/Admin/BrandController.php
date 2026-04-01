<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        $query = Brand::withCount('products');

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->input('store_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $brands = $query->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.brands.index', compact('brands'));
    }

    public function create()
    {
        return view('admin.brands.form', [
            'brand' => null,
            'pimToken' => (string) Str::uuid(),
        ]);
    }

    public function quickCreate(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $storeId = (int) config('app.store_id', 1);
        $brand = Brand::create([
            'store_id' => $storeId,
            'name' => $validated['name'],
            'slug' => $this->generateUniqueSlug($validated['name'], $storeId),
            'is_active' => true,
            'sort_order' => 0,
        ]);

        return response()->json([
            'success' => true,
            'brand' => $brand,
            'redirect' => route('admin.brands.edit', $brand),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_id' => ['required', 'exists:stores,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9\-]*$/'],
            'image' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'meta_keywords' => ['nullable', 'string', 'max:500'],
        ]);

        $validated['slug'] = !empty($validated['slug'])
            ? $validated['slug']
            : $this->generateUniqueSlug($validated['name'], $validated['store_id']);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = $request->boolean('is_active', true);

        Brand::create($validated);

        return redirect()->route('admin.brands.index')
            ->with('success', 'Brand created successfully.');
    }

    public function edit(Brand $brand)
    {
        $pimToken = (string) Str::uuid();

        return view('admin.brands.form', compact('brand', 'pimToken'));
    }

    public function update(Request $request, Brand $brand)
    {
        $validated = $request->validate([
            'store_id' => ['required', 'exists:stores,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9\-]*$/'],
            'image' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'meta_keywords' => ['nullable', 'string', 'max:500'],
        ]);

        if (!empty($validated['slug']) && $validated['slug'] !== $brand->slug) {
            $validated['slug'] = $validated['slug'];
        } elseif ($validated['name'] !== $brand->name || $validated['store_id'] !== $brand->store_id) {
            $validated['slug'] = $this->generateUniqueSlug($validated['name'], $validated['store_id'], $brand->id);
        } else {
            unset($validated['slug']);
        }

        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = $request->boolean('is_active', true);

        $brand->update($validated);

        return redirect()->route('admin.brands.index')
            ->with('success', 'Brand updated successfully.');
    }

    public function destroy(Brand $brand)
    {
        $brand->delete();

        return redirect()->route('admin.brands.index')
            ->with('success', 'Brand deleted successfully.');
    }

    public function search(Request $request)
    {
        $request->validate([
            'store_id' => ['required', 'exists:stores,id'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $query = Brand::where('store_id', $request->input('store_id'));

        if ($request->filled('q')) {
            $query->where('name', 'like', '%' . $request->input('q') . '%');
        }

        $brands = $query->orderBy('name')->limit(20)->get(['id', 'name']);

        return response()->json($brands);
    }

    protected function generateUniqueSlug(string $name, int $storeId, ?int $excludeId = null): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $counter = 1;

        while (Brand::where('store_id', $storeId)
            ->where('slug', $slug)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists()
        ) {
            $slug = "{$original}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
