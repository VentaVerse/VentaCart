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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_id' => ['required', 'exists:stores,id'],
            'name' => ['required', 'string', 'max:255'],
            'image' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['slug'] = $this->generateUniqueSlug($validated['name'], $validated['store_id']);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

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
            'image' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validated['name'] !== $brand->name || $validated['store_id'] !== $brand->store_id) {
            $validated['slug'] = $this->generateUniqueSlug($validated['name'], $validated['store_id'], $brand->id);
        }

        $validated['sort_order'] = $validated['sort_order'] ?? 0;

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
