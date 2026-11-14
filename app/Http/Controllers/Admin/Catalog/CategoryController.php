<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkSelectionRequest;
use App\Models\Category;
use App\Support\CategoryTree;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $filtered = $request->filled('search') || $request->filled('status');

        $categories = Category::withCount('products')
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%' . $request->input('search') . '%'))
            ->when($request->input('status') === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->input('status') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->get()
            ->keyBy('id');

        // Tree order: each category after its parent, at its depth. A
        // filtered list keeps the order and shows each match's full path,
        // since its parents may not be among the matches.
        $rows = [];
        foreach (CategoryTree::options() as $option) {
            if ($category = $categories->get($option['id'])) {
                $rows[] = ['category' => $category, 'depth' => $filtered ? 0 : $option['depth'], 'path' => $option['name']];
            }
        }

        return view('admin.catalog.categories.index', compact('rows', 'filtered'));
    }

    public function create(Request $request)
    {
        return view('admin.catalog.categories.form', [
            'category' => null,
            'parentOptions' => CategoryTree::parentOptions(),
            'pimToken' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateCategory($request);
        $validated['slug'] = !empty($validated['slug'])
            ? $validated['slug']
            : $this->generateUniqueSlug($validated['name'], $validated['store_id']);

        $inMenu = (bool) ($validated['in_menu'] ?? false);
        unset($validated['in_menu']);

        $category = Category::create($validated);
        \App\Support\MainMenu::set($category, $inMenu);

        return redirect()->route('admin.categories.index')
            ->with('success', 'Category created successfully.');
    }

    public function edit(Category $category)
    {
        return view('admin.catalog.categories.form', [
            'category' => $category,
            'parentOptions' => CategoryTree::parentOptions($category->id),
            'pimToken' => (string) Str::uuid(),
        ]);
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

        $inMenu = (bool) ($validated['in_menu'] ?? false);
        unset($validated['in_menu']);

        $category->update($validated);
        \App\Support\MainMenu::set($category, $inMenu);

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

    /** Enable or disable the ticked categories. */
    public function bulkStatus(BulkSelectionRequest $request)
    {
        $active = $request->boolean('active');
        $count = Category::whereIn('id', $request->ids())->update(['is_active' => $active]);
        CategoryTree::forget();

        return redirect()->route('admin.categories.index')->with('success', sprintf(
            '%d %s %s.', $count, Str::plural('category', $count), $active ? 'enabled' : 'disabled'
        ));
    }

    /** Delete the ticked categories; their subcategories move up. */
    public function bulkDelete(BulkSelectionRequest $request)
    {
        $ids = Category::whereIn('id', $request->ids())->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->deleteMany($ids);

        return redirect()->route('admin.categories.index')->with('success', sprintf(
            '%d %s deleted. Their subcategories moved up.', count($ids), Str::plural('category', count($ids))
        ));
    }

    /**
     * Delete several categories. A subcategory that stays moves up to its
     * nearest ancestor that stays, or to the top level when there is none.
     *
     * @param  list<int>  $ids
     */
    private function deleteMany(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            $going = array_flip($ids);
            $parentOf = Category::pluck('parent_id', 'id')->map(fn ($p) => $p ? (int) $p : null)->all();

            foreach ($parentOf as $id => $parentId) {
                if (isset($going[$id]) || $parentId === null || ! isset($going[$parentId])) {
                    continue;
                }
                $up = $parentId;
                $steps = 0;
                while ($up !== null && isset($going[$up]) && $steps++ < count($parentOf)) {
                    $up = $parentOf[$up] ?? null;
                }
                Category::whereKey($id)->update(['parent_id' => isset($going[$up]) ? null : $up]);
            }

            Category::whereIn('id', $ids)->get()->each->delete();
        });
        CategoryTree::forget();
    }

    protected function validateCategory(Request $request, ?int $categoryId = null): array
    {
        return $request->validate([
            'store_id' => ['required', 'exists:stores,id'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id', function (string $attribute, mixed $value, \Closure $fail) use ($categoryId) {
                // Under itself or one of its own subcategories would be a loop.
                if ($categoryId && $value && in_array((int) $value, CategoryTree::branch($categoryId), true)) {
                    $fail('A category cannot sit under itself or one of its own subcategories.');
                }
            }],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9\-]*$/'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'in_menu' => ['boolean'],
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
