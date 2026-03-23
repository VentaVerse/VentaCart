<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Category::with(['parent'])->withCount('products');

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->input('store_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->filled('parent')) {
            if ($request->input('parent') === 'top') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $request->input('parent'));
            }
        }

        $categories = $query->orderBy('sort_order')->orderBy('name')->paginate(30)->withQueryString();

        return view('admin.categories.index', compact('categories'));
    }

    public function create(Request $request)
    {
        $parentCategories = Category::whereNull('parent_id')->orderBy('name')->get();

        return view('admin.categories.form', [
            'category' => null,
            'parentCategories' => $parentCategories,
            'pimToken' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateCategory($request);
        $validated['slug'] = !empty($validated['slug'])
            ? $validated['slug']
            : $this->generateUniqueSlug($validated['name'], $validated['store_id']);

        Category::create($validated);

        return redirect()->route('admin.categories.index')
            ->with('success', 'Category created successfully.');
    }

    public function edit(Category $category)
    {
        $parentCategories = Category::whereNull('parent_id')
            ->where('id', '!=', $category->id)
            ->orderBy('name')->get();

        $pimToken = (string) Str::uuid();

        return view('admin.categories.form', compact('category', 'parentCategories', 'pimToken'));
    }

    public function update(Request $request, Category $category)
    {
        $validated = $this->validateCategory($request, $category->id);

        if (!empty($validated['slug']) && $validated['slug'] !== $category->slug) {
            // User explicitly set a slug
        } elseif ($validated['name'] !== $category->name || $validated['store_id'] !== $category->store_id) {
            $validated['slug'] = $this->generateUniqueSlug($validated['name'], $validated['store_id'], $category->id);
        } else {
            unset($validated['slug']);
        }

        $category->update($validated);

        return redirect()->route('admin.categories.index')
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(Category $category)
    {
        // Move children to parent (or top-level) before deleting
        $category->children()->update(['parent_id' => $category->parent_id]);

        $category->delete();

        return redirect()->route('admin.categories.index')
            ->with('success', 'Category deleted. Child categories were moved up.');
    }

    protected function validateCategory(Request $request, ?int $categoryId = null): array
    {
        return $request->validate([
            'store_id' => ['required', 'exists:stores,id'],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9\-]*$/'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
        ]);
    }

    protected function generateUniqueSlug(string $name, int $storeId, ?int $excludeId = null): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $counter = 1;

        while (Category::where('store_id', $storeId)
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
