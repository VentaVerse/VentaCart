<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CustomerGroup;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tag;
use App\Models\VariantType;
use App\Models\VariantTypeValue;
use App\Support\LowStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        // categories was eager-loaded and never read by the view. variants
        // is read on every row, and now carries the values that name each one.
        $query = Product::with(['variants.values']);

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

        // The set behind the day sheet's "Stock about to run out" line, and
        // behind the stock:check-low alert mail: one definition, shared, so
        // the number the merchant clicks and the list they land on cannot
        // drift apart (App\Support\LowStock).
        if ($request->input('stock') === 'low') {
            LowStock::scopeProductListing($query);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  // A variant SKU is the code on the picking slip and on the
                  // supplier's invoice. It was the one thing a merchant would
                  // paste into this box and the one thing it could not find,
                  // because a product with variants shows no SKU of its own.
                  ->orWhereHas('variants', function ($v) use ($search) {
                      $v->where('sku', 'like', "%{$search}%");
                  });
            });
        }

        // What the merchant asked to see first. The list only ever answered
        // "what exists, in creation order", so the questions a packing desk
        // actually asks (what is nearly out, what costs the most, what changed
        // overnight) had no way in. Allow-listed rather than passed through:
        // an order-by taken from the querystring is a column name taken from a
        // stranger.
        $sortable = [
            'name' => 'name',
            'price' => 'price',
            'stock' => 'quantity',
            'updated' => 'updated_at',
            'created' => 'created_at',
        ];

        $sort = $request->input('sort');
        $dir = $request->input('dir') === 'asc' ? 'asc' : 'desc';

        if (isset($sortable[$sort])) {
            $query->orderBy($sortable[$sort], $dir);
        } else {
            $sort = null;
            $query->orderBy('created_at', 'desc');
        }

        // Bounded, and never zero: a per-page from the querystring is a
        // number a stranger chose, and 100,000 rows is a denial of service
        // with a friendly name.
        $perPage = (int) $request->input('per', 20);
        $perPage = in_array($perPage, [20, 50, 100], true) ? $perPage : 20;

        $products = $query->paginate($perPage)->withQueryString();

        $categories = Category::orderBy('name')->get();

        return view('admin.catalog.products.index', compact('products', 'categories', 'sort', 'dir', 'perPage'));
    }

    public function create(Request $request)
    {
        $categories = Category::orderBy('name')->get();
        $customerGroups = CustomerGroup::orderBy('name')->get();
        $tags = Tag::orderBy('name')->get();

        return view('admin.catalog.products.form', [
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

    public function quickCreate(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'brand_id' => ['required', 'exists:brands,id'],
        ]);

        $storeId = (int) config('app.store_id', 1);
        $product = Product::create([
            'store_id' => $storeId,
            'name' => $validated['name'],
            'slug' => $this->generateUniqueSlug($validated['name'], $storeId),
            'brand_id' => $validated['brand_id'],
            'is_active' => false,
            'price' => 0,
            'quantity' => 0,
            'sort_order' => 0,
        ]);

        return response()->json([
            'success' => true,
            'product' => $product,
            'redirect' => route('admin.products.edit', $product),
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
        $product->load(['brand', 'categories', 'customerGroups', 'tags', 'specials', 'images', 'variantTypes.values', 'variants.values']);
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

            $modifier = (float) $v->price_modifier;
            return [
                'sku' => $v->sku,
                'price' => $v->price,
                'modifier_sign' => $modifier < 0 ? '-' : '+',
                'modifier_amount' => number_format(abs($modifier), 2, '.', ''),
                'quantity' => $v->quantity,
                'weight' => $v->weight,
                'is_active' => $v->is_active,
                'image' => $v->image ?? '',
                'label' => $v->name,
                'value_keys' => $keys,
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

        return view('admin.catalog.products.form', compact('product', 'categories', 'customerGroups', 'tags', 'existingTypes', 'existingVariants', 'existingSpecials', 'existingImages', 'pimToken'));
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

    /**
     * Hard delete. The row goes, so the SKU is genuinely free again for the
     * ERP and every other integration that syncs on it.
     *
     * Image FILES are deliberately left on disk. A media file is shared: the
     * same catalog path is routinely referenced by several products, so
     * deleting the file with the product would blank the image on every other
     * product using it. Unlinking a product from an image is not a reason to
     * destroy the image.
     *
     * Children (variants, variant types, images, specials, category and tag
     * links, reviews, stock notifications, seo suggestions, cart items, low
     * stock alerts) are removed by the ON DELETE CASCADE foreign keys.
     * order_products is ON DELETE SET NULL and carries its own snapshot of
     * name/sku/price, so order history survives intact.
     */
    public function destroy(Product $product)
    {
        DB::transaction(function () use ($product) {
            $product->delete();
        });

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
            'brand_id' => ['nullable', 'exists:brands,id'],
            'description' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['nullable', 'integer'],
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

            // The variants payload had no rules at all, so a row with a blank
            // SKU was accepted, silently dropped by the writer, and reported
            // as a success. A merchant filling stock across thirty-six
            // generated rows lost thirty of them and was told the product had
            // been updated.
            'variant_types' => ['nullable', 'array'],
            'variant_types.*.name' => ['required', 'string', 'max:100'],
            'variant_types.*.values' => ['required', 'array', 'min:1'],
            'variant_types.*.values.*' => ['required', 'string', 'max:100'],
            'variants' => ['nullable', 'array'],
            'variants.*.sku' => ['required', 'string', 'max:100'],
            'variants.*.quantity' => ['nullable', 'integer', 'min:0'],
            'variants.*.modifier_sign' => ['nullable', 'in:+,-'],
            'variants.*.modifier_amount' => ['nullable', 'numeric', 'min:0'],
            'variants.*.weight' => ['nullable', 'numeric', 'min:0'],
            'variants.*.is_active' => ['nullable', 'boolean'],
            'variants.*.image' => ['nullable', 'string', 'max:2048'],
            'variants.*.value_keys' => ['nullable', 'array'],
        ], [
            // Named by row, because the generic message points at the General
            // tab and leaves a merchant hunting thirty-six rows for the one
            // that is empty.
            'variants.*.sku.required' => 'Every variant needs its own SKU. Row :position has none.',
        ]);

        // Validate SKU uniqueness across products + variants in this store
        $this->validateSkuUniqueness($request, $storeId, $productId);

        return $validated;
    }

    /**
     * Every SKU on this form, and where each one came from.
     *
     * All three checks below used to throw on the key `sku`, which is the
     * PARENT's field on the General tab. So a merchant who typed a duplicate
     * into row 31 of the Variants tab was thrown to General, shown a bare
     * string, and left to find the row themselves. The message now lands on
     * the input that caused it.
     */
    private function skuOrigins(Request $request): array
    {
        $origins = [];

        foreach ((array) $request->input('variants', []) as $index => $variant) {
            $sku = $variant['sku'] ?? null;
            if ($sku) {
                $origins[$sku] ??= 'variants.' . $index . '.sku';
            }
        }

        if ($parent = $request->input('sku')) {
            $origins[$parent] ??= 'sku';
        }

        return $origins;
    }

    /** One message per offending input, so the form can point at each. */
    private function skuFailure(array $origins, iterable $skus, string $message): void
    {
        $errors = [];

        foreach ($skus as $sku) {
            $errors[$origins[$sku] ?? 'sku'] = str_replace(':sku', $sku, $message);
        }

        throw \Illuminate\Validation\ValidationException::withMessages($errors);
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

        $origins = $this->skuOrigins($request);

        // Check for duplicates within the submitted form itself
        $duplicates = $allSkus->duplicates()->unique()->values();
        if ($duplicates->isNotEmpty()) {
            // Every row carrying the repeated code is named, not just the
            // first: telling a merchant "P222-V3 is duplicated" while marking
            // one of the two rows involved is half an answer.
            $errors = [];
            foreach ($duplicates as $sku) {
                foreach ($variantSkus as $i => $vSku) {
                    if ($vSku === $sku) {
                        $errors['variants.' . $i . '.sku'] = $sku . ' is used more than once on this product.';
                    }
                }
                if ($parentSku === $sku) {
                    $errors['sku'] = $sku . ' is used more than once on this product.';
                }
            }

            throw \Illuminate\Validation\ValidationException::withMessages($errors);
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
            $this->skuFailure($origins, $existingProductSkus, ':sku already belongs to another product.');
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
            $this->skuFailure($origins, $existingVariantSkus, ':sku already belongs to a variant on another product.');
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

        // The types and their values ARE rebuilt: nothing outside this product
        // references them, and the pivot is re-attached below.
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
        $keptIds = [];

        foreach ($variantsData as $variantData) {
            if (empty($variantData['sku'])) {
                continue;
            }

            $weight = !empty($variantData['weight']) ? $variantData['weight'] : null;

            // Compute the absolute price from product base + signed modifier.
            // The modifier_sign / modifier_amount fields are the source of truth
            // in the admin form; `price` is kept populated for ERP/API consumers
            // that read the absolute total.
            $sign = ($variantData['modifier_sign'] ?? '+') === '-' ? -1 : 1;
            $amount = (float) ($variantData['modifier_amount'] ?? 0);
            $modifier = round($sign * $amount, 2);
            $absolute = max(0, round((float) $product->price + $modifier, 2));

            $fields = [
                'price' => $absolute,
                'price_modifier' => $modifier,
                'quantity' => $variantData['quantity'] ?? 0,
                'weight' => $weight,
                // The switch posts 1 and its companion posts 0, so the value
                // is read rather than the key's presence. isset() was correct
                // for a bare checkbox and would have read every switched-off
                // variant as switched ON the moment the companion arrived.
                'is_active' => (bool) ($variantData['is_active'] ?? false),
            ];

            // Absent and empty are different answers. The matrix always posts
            // the key, so an empty value is a merchant who pressed Remove and
            // must be obeyed; a missing key is a caller that never knew about
            // photos, and there whatever is stored stands untouched.
            if (array_key_exists('image', $variantData)) {
                $fields['image'] = $variantData['image'] ?: null;
            }

            // Matched, not recreated.
            //
            // This method used to delete every variant and build them again,
            // so a routine product edit churned every variant id. Order lines
            // carry product_variant_id with ON DELETE SET NULL, which means
            // each save quietly cut the link between an order and the exact
            // colourway it sold: the denormalised sku and label on the line
            // survived, the machine link did not. legacy_oc_id went the same
            // way, and so did anything else the form does not round-trip.
            //
            // SKU is the identity: it is required, unique across the store,
            // and it is what the ERP matches on too.
            $variant = $product->variants()->updateOrCreate(
                ['sku' => $variantData['sku']],
                $fields
            );

            $keptIds[] = $variant->id;

            // Attach variant type values
            $valueIds = [];
            foreach ($variantData['value_keys'] ?? [] as $key) {
                if (isset($typeValueMap[$key])) {
                    $valueIds[] = $typeValueMap[$key];
                }
            }
            // sync, not attach: the row may be one this save just updated
            // rather than one it created, and attach would double it.
            $variant->values()->sync($valueIds);
        }

        // Only what the merchant actually removed on screen.
        $product->variants()->whereNotIn('id', $keptIds)->delete();

        $product->update(['has_variants' => $product->variants()->count() > 0]);
    }

    protected function syncQuantityFromVariants(Product $product): void
    {
        $product->syncStockFromVariants();
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

        // Only the product_images rows are rewritten here. Removing an image
        // from a product never deletes the file: catalog media is shared, so
        // the same path is often referenced by other products, and deleting
        // the file would blank their image too.

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
        // Image files are persisted at whatever path the upload helper produced
        // (products/{brand}/… etc.). No relocation here — see the image storage
        // convention spec. Relocating was the source of a silent-failure bug.
    }
}
