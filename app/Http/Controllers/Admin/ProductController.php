<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CustomerGroup;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tag;
use App\Models\VariantType;
use App\Models\VariantTypeValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['categories', 'variants']);

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->input('store_id'));
        }

        if ($request->filled('category_id')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('categories.id', $request->input('category_id'));
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('model', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();
        $categories = Category::orderBy('name')->get();

        return view('admin.products.index', compact('products', 'categories'));
    }

    public function create(Request $request)
    {
        $categories = Category::orderBy('name')->get();
        $customerGroups = CustomerGroup::orderBy('name')->get();
        $tags = Tag::orderBy('name')->get();

        return view('admin.products.form', [
            'product' => null,
            'categories' => $categories,
            'customerGroups' => $customerGroups,
            'tags' => $tags,
            'existingTypes' => [],
            'existingVariants' => [],
            'existingSpecials' => [],
            'existingImages' => [],
            'pimToken' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateProduct($request);

        DB::transaction(function () use ($request, $validated) {
            $validated['slug'] = !empty($validated['slug'])
                ? $validated['slug']
                : $this->generateUniqueSlug($validated['name'], $validated['store_id']);
            $validated['has_variants'] = $request->has('variants') && count($request->input('variants', [])) > 0;

            // Default price to 0 when variants handle pricing
            if ($validated['has_variants']) {
                $validated['price'] = $validated['price'] ?? 0;
            }

            $product = Product::create($validated);

            if ($request->filled('category_ids')) {
                $product->categories()->sync($request->input('category_ids'));
            }

            $product->tags()->sync($request->input('tag_ids', []));
            $product->customerGroups()->sync($request->input('customer_group_ids', []));

            $this->syncVariants($product, $request);
            $this->syncQuantityFromVariants($product);
            $this->syncSpecials($product, $request);
            $this->syncImagesFromJson($product, $request);
        });

        return redirect()->route('admin.products.edit', $product)
            ->with('success', 'Product created successfully.');
    }

    public function edit(Product $product)
    {
        $product->load(['brand', 'categories', 'customerGroups', 'tags', 'specials', 'images', 'variantTypes.values', 'variants.values', 'variants.groupPrices']);
        $categories = Category::where('store_id', $product->store_id)->orderBy('name')->get();
        $customerGroups = CustomerGroup::orderBy('name')->get();
        $tags = Tag::orderBy('name')->get();

        $existingTypes = $product->variantTypes->map(function ($t) {
            return [
                'name' => $t->name,
                'values' => $t->values->pluck('value')->toArray(),
            ];
        })->toArray();

        // Build value ID => type_index + value_index key map for reconstruction
        $valueKeyMap = [];
        foreach ($product->variantTypes as $typeIndex => $type) {
            foreach ($type->values as $valueIndex => $val) {
                $valueKeyMap[$val->id] = $typeIndex . '_' . $valueIndex;
            }
        }

        $existingVariants = $product->variants->map(function ($v) use ($valueKeyMap) {
            $keys = $v->values->map(function ($val) use ($valueKeyMap) {
                return $valueKeyMap[$val->id] ?? '';
            })->filter()->values()->toArray();

            $groupPrices = [];
            foreach ($v->groupPrices as $gp) {
                $groupPrices[$gp->customer_group_id] = (float) $gp->price;
            }

            return [
                'sku' => $v->sku,
                'price' => $v->price,
                'quantity' => $v->quantity,
                'weight' => $v->weight,
                'is_active' => $v->is_active,
                'label' => $v->name,
                'value_keys' => $keys,
                'group_prices' => $groupPrices,
            ];
        })->toArray();

        $existingImages = $product->images->sortBy('sort_order')->map(function ($img) {
            return [
                'path' => $img->path,
                'url' => storage_url($img->path),
            ];
        })->values()->toArray();

        // Ensure main image is first and not duplicated
        if ($product->image) {
            $mainIdx = collect($existingImages)->search(fn ($img) => $img['path'] === $product->image);
            if ($mainIdx !== false && $mainIdx !== 0) {
                // Move it to the front
                $main = array_splice($existingImages, $mainIdx, 1);
                array_unshift($existingImages, $main[0]);
            } elseif ($mainIdx === false) {
                // Not in gallery at all — prepend it
                array_unshift($existingImages, [
                    'path' => $product->image,
                    'url' => storage_url($product->image),
                ]);
            }
            // If $mainIdx === 0, it's already in the right position
        }

        $existingSpecials = $product->specials->map(function ($s) {
            return [
                'customer_group_id' => $s->customer_group_id ?? '',
                'price' => $s->price,
                'quantity' => $s->quantity ?? 1,
                'display_mode' => $s->display_mode,
                'priority' => $s->priority,
                'date_start' => $s->date_start?->format('Y-m-d') ?? '',
                'date_end' => $s->date_end?->format('Y-m-d') ?? '',
            ];
        })->toArray();

        $pimToken = (string) Str::uuid();

        // Derive the brand folder from the product's first image path
        $brandFolder = '';
        if ($product->image && str_starts_with($product->image, 'catalog/')) {
            $relative = substr($product->image, strlen('catalog/'));
            $dir = dirname($relative);
            if ($dir !== '.') {
                $brandFolder = $dir;
            }
        }

        return view('admin.products.form', compact('product', 'categories', 'customerGroups', 'tags', 'existingTypes', 'existingVariants', 'existingSpecials', 'existingImages', 'pimToken', 'brandFolder'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $this->validateProduct($request, $product->id);

        DB::transaction(function () use ($request, $product, $validated) {
            if (!empty($validated['slug']) && $validated['slug'] !== $product->slug) {
                // User explicitly set a slug
            } elseif ($validated['name'] !== $product->name || $validated['store_id'] !== $product->store_id) {
                $validated['slug'] = $this->generateUniqueSlug($validated['name'], $validated['store_id'], $product->id);
            } else {
                unset($validated['slug']);
            }

            $validated['has_variants'] = $request->has('variants') && count($request->input('variants', [])) > 0;

            if ($validated['has_variants']) {
                $validated['price'] = $validated['price'] ?? 0;
            }

            $product->update($validated);

            if ($request->filled('category_ids')) {
                $product->categories()->sync($request->input('category_ids'));
            } else {
                $product->categories()->detach();
            }

            $product->tags()->sync($request->input('tag_ids', []));
            $product->customerGroups()->sync($request->input('customer_group_ids', []));

            $this->syncVariants($product, $request);
            $this->syncQuantityFromVariants($product);
            $this->syncSpecials($product, $request);
            $this->syncImagesFromJson($product, $request);
        });

        return redirect()->route('admin.products.edit', $product)
            ->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        // Delete main image and its thumbnail
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
            $info = pathinfo($product->image);
            Storage::disk('public')->delete($info['dirname'] . '/' . $info['filename'] . '_thumb.' . ($info['extension'] ?? 'jpg'));
        }

        // Delete additional images and thumbnails
        foreach ($product->images as $img) {
            Storage::disk('public')->delete($img->path);
            $info = pathinfo($img->path);
            Storage::disk('public')->delete($info['dirname'] . '/' . $info['filename'] . '_thumb.' . ($info['extension'] ?? 'jpg'));
        }

        $product->delete();

        return redirect()->route('admin.products.index')
            ->with('success', 'Product deleted successfully.');
    }

    protected function validateProduct(Request $request, ?int $productId = null): array
    {
        $storeId = $request->input('store_id');

        $validated = $request->validate([
            'store_id' => ['required', 'exists:stores,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9\-]*$/'],
            'sku' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'description' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['nullable', 'integer', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'weight_unit' => ['nullable', 'string', 'in:kg,g,lb,oz'],
            'length' => ['nullable', 'numeric', 'min:0'],
            'width' => ['nullable', 'numeric', 'min:0'],
            'height' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'date_available' => ['nullable', 'date'],
            'customer_group_ids' => ['nullable', 'array'],
            'customer_group_ids.*' => ['exists:customer_groups,id'],
            'minimum_quantity' => ['nullable', 'integer', 'min:1'],
            'track_stock' => ['boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
            'images_json' => ['nullable', 'string'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['exists:categories,id'],
        ]);

        // Validate SKU uniqueness across products + variants in this store
        $this->validateSkuUniqueness($request, $storeId, $productId);

        return $validated;
    }

    protected function validateSkuUniqueness(Request $request, $storeId, ?int $productId = null): void
    {
        $parentSku = $request->input('sku');
        $variantsData = $request->input('variants', []);
        $variantSkus = collect($variantsData)->pluck('sku')->filter()->values()->all();
        $allSkus = collect($variantSkus);
        if ($parentSku) {
            $allSkus->prepend($parentSku);
        }

        // Check for duplicates within the submitted form itself
        $duplicates = $allSkus->duplicates()->unique()->values();
        if ($duplicates->isNotEmpty()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'sku' => 'Duplicate SKU(s) found: ' . $duplicates->implode(', '),
            ]);
        }

        if ($allSkus->isEmpty()) {
            return;
        }

        // Check against other products' parent SKUs in this store
        $existingProductSkus = Product::where('store_id', $storeId)
            ->when($productId, fn ($q) => $q->where('id', '!=', $productId))
            ->whereIn('sku', $allSkus)
            ->pluck('sku');

        if ($existingProductSkus->isNotEmpty()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'sku' => 'SKU already used by another product: ' . $existingProductSkus->implode(', '),
            ]);
        }

        // Check against other products' variant SKUs in this store
        $existingVariantSkus = ProductVariant::whereHas('product', function ($q) use ($storeId, $productId) {
                $q->where('store_id', $storeId);
                if ($productId) {
                    $q->where('id', '!=', $productId);
                }
            })
            ->whereIn('sku', $allSkus)
            ->pluck('sku');

        if ($existingVariantSkus->isNotEmpty()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'sku' => 'SKU already used by a variant on another product: ' . $existingVariantSkus->implode(', '),
            ]);
        }
    }

    protected function generateUniqueSlug(string $name, int $storeId, ?int $excludeId = null): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $counter = 1;

        while (Product::where('store_id', $storeId)
            ->where('slug', $slug)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists()
        ) {
            $slug = "{$original}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    protected function syncVariants(Product $product, Request $request): void
    {
        $variantTypesData = $request->input('variant_types', []);
        $variantsData = $request->input('variants', []);

        if (empty($variantTypesData)) {
            $product->variants()->delete();
            $product->variantTypes()->delete();
            $product->update(['has_variants' => false]);
            return;
        }

        // Delete old variant types and variants
        $product->variants()->delete();
        $product->variantTypes()->delete();

        // Create variant types and their values
        $typeValueMap = [];
        foreach ($variantTypesData as $typeIndex => $typeData) {
            if (empty($typeData['name'])) {
                continue;
            }

            $variantType = $product->variantTypes()->create([
                'name' => $typeData['name'],
                'sort_order' => $typeIndex,
            ]);

            foreach ($typeData['values'] ?? [] as $valueIndex => $valueText) {
                if (empty($valueText)) {
                    continue;
                }

                $typeValue = $variantType->values()->create([
                    'value' => $valueText,
                    'sort_order' => $valueIndex,
                ]);

                $typeValueMap["{$typeIndex}_{$valueIndex}"] = $typeValue->id;
            }
        }

        // Create variant combinations
        foreach ($variantsData as $variantData) {
            if (empty($variantData['sku'])) {
                continue;
            }

            $weight = !empty($variantData['weight']) ? $variantData['weight'] : null;

            $variant = $product->variants()->create([
                'sku' => $variantData['sku'],
                'price' => $variantData['price'] ?? 0,
                'quantity' => $variantData['quantity'] ?? 0,
                'weight' => $weight,
                'is_active' => isset($variantData['is_active']),
            ]);

            // Attach variant type values
            $valueIds = [];
            foreach ($variantData['value_keys'] ?? [] as $key) {
                if (isset($typeValueMap[$key])) {
                    $valueIds[] = $typeValueMap[$key];
                }
            }
            $variant->values()->attach($valueIds);

            // Save group prices
            foreach ($variantData['group_prices'] ?? [] as $groupId => $groupPrice) {
                if ($groupPrice !== null && $groupPrice !== '') {
                    $variant->groupPrices()->create([
                        'customer_group_id' => $groupId,
                        'price' => $groupPrice,
                    ]);
                }
            }
        }

        $product->update(['has_variants' => $product->variants()->count() > 0]);
    }

    protected function syncQuantityFromVariants(Product $product): void
    {
        if ($product->has_variants) {
            $product->update(['quantity' => $product->variants()->sum('quantity')]);
        }
    }

    protected function syncSpecials(Product $product, Request $request): void
    {
        $product->specials()->delete();

        $specialsData = $request->input('specials', []);
        foreach ($specialsData as $data) {
            if (!isset($data['price']) || $data['price'] === '') {
                continue;
            }

            $product->specials()->create([
                'customer_group_id' => $data['customer_group_id'] ?: null,
                'price' => $data['price'],
                'quantity' => max(1, (int) ($data['quantity'] ?? 1)),
                'display_mode' => $data['display_mode'] ?? 'sale',
                'priority' => $data['priority'] ?? 0,
                'date_start' => $data['date_start'] ?: null,
                'date_end' => $data['date_end'] ?: null,
            ]);
        }
    }

    protected function syncImagesFromJson(Product $product, Request $request): void
    {
        $imagesJson = $request->input('images_json', '[]');
        $images = json_decode($imagesJson, true) ?: [];

        // Collect current paths for cleanup
        $oldPaths = [];
        if ($product->image) {
            $oldPaths[] = $product->image;
        }
        foreach ($product->images as $img) {
            $oldPaths[] = $img->path;
        }

        // New paths from JSON
        $newPaths = array_column($images, 'path');

        // Delete files that are no longer referenced
        foreach ($oldPaths as $oldPath) {
            if (!in_array($oldPath, $newPaths)) {
                Storage::disk('public')->delete($oldPath);
                // Delete thumbnail
                $info = pathinfo($oldPath);
                $thumbPath = $info['dirname'] . '/' . $info['filename'] . '_thumb.' . ($info['extension'] ?? 'jpg');
                Storage::disk('public')->delete($thumbPath);
            }
        }

        // Clear existing image records
        $product->images()->delete();

        if (empty($images)) {
            $product->update(['image' => null]);
            return;
        }

        // First image = main product image
        $product->update(['image' => $images[0]['path']]);

        // Remaining images go to product_images table
        foreach ($images as $index => $img) {
            if ($index === 0) {
                continue;
            }
            $product->images()->create([
                'path' => $img['path'],
                'sort_order' => $index,
            ]);
        }

        // Determine catalog folder based on brand
        $catalogDir = $this->getCatalogDir($product);
        $this->moveImagesToDir($product, $catalogDir);
    }

    protected function getCatalogDir(Product $product): string
    {
        $brand = $product->brand;
        $folder = $brand ? $brand->slug : 'unsorted';

        return "catalog/{$folder}";
    }

    protected function moveImagesToDir(Product $product, string $catalogDir): void
    {
        // Skip symlinked OpenCart images (catalog/ prefix) — they live outside open_basedir
        // Move main image if not already in the target directory
        if ($product->image && !str_starts_with($product->image, 'catalog/') && !str_starts_with($product->image, $catalogDir . '/')) {
            $newPath = $this->moveFileToDir($product->image, $catalogDir);
            if ($newPath) {
                $product->update(['image' => $newPath]);
            }
        }

        // Move additional images
        foreach ($product->images as $img) {
            if (!str_starts_with($img->path, 'catalog/') && !str_starts_with($img->path, $catalogDir . '/')) {
                $newPath = $this->moveFileToDir($img->path, $catalogDir);
                if ($newPath) {
                    $img->update(['path' => $newPath]);
                }
            }
        }
    }

    protected function moveFileToDir(string $currentPath, string $targetDir): ?string
    {
        if (!Storage::disk('public')->exists($currentPath)) {
            return null;
        }

        $newPath = $targetDir . '/' . basename($currentPath);
        Storage::disk('public')->move($currentPath, $newPath);

        // Move thumbnail too
        $info = pathinfo($currentPath);
        $thumbOld = $info['dirname'] . '/' . $info['filename'] . '_thumb.' . ($info['extension'] ?? 'jpg');
        if (Storage::disk('public')->exists($thumbOld)) {
            $thumbNew = $targetDir . '/' . $info['filename'] . '_thumb.' . ($info['extension'] ?? 'jpg');
            Storage::disk('public')->move($thumbOld, $thumbNew);
        }

        return $newPath;
    }
}
