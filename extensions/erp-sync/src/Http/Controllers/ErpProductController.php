<?php

namespace Extensions\ErpSync\Http\Controllers;

use App\Exceptions\RemoteImageFetchException;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ErpProductController extends Controller
{
    use \App\Http\Controllers\Api\FetchesRemoteImages;
    use \App\Http\Controllers\Api\LogsApiCalls;

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 50), 200);

        $query = Product::query()
            ->with('brand:id,name,slug')
            ->select([
                'id', 'sku', 'name', 'slug', 'price', 'cost', 'quantity',
                'weight', 'brand_id', 'is_active', 'has_variants',
                'created_at', 'updated_at',
            ]);

        if ($request->filled('sku')) {
            $query->where('sku', $request->input('sku'));
        }

        if ($request->filled('updated_since')) {
            $query->where('updated_at', '>=', $request->input('updated_since'));
        }

        if ($request->filled('brand')) {
            $query->whereHas('brand', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->input('brand') . '%');
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', (bool) $request->input('status'));
        }

        $products = $query->orderBy('updated_at', 'desc')->paginate($perPage);

        $data = $products->through(function ($product) {
            return [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'slug' => $product->slug,
                'price' => $product->price,
                'cost' => $product->cost,
                'quantity' => $product->quantity,
                'weight' => $product->weight,
                'brand' => $product->brand?->name,
                'status' => $product->is_active,
                'has_variants' => $product->has_variants,
                'created_at' => $product->created_at?->toIso8601String(),
                'updated_at' => $product->updated_at?->toIso8601String(),
            ];
        });

        return $this->logAndRespond($request, $data);
    }

    public function show(Request $request, string $sku): JsonResponse
    {
        $product = Product::where('sku', $sku)
            ->with(['brand:id,name,slug', 'categories:id,name,slug', 'images', 'variants.values', 'variantTypes.values'])
            ->first();

        if (!$product) {
            return $this->logAndRespond($request, ['error' => 'Product not found'], 404, $sku);
        }

        $data = [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'price' => $product->price,
            'cost' => $product->cost,
            'quantity' => $product->quantity,
            'weight' => $product->weight,
            'brand' => $product->brand ? [
                'id' => $product->brand->id,
                'name' => $product->brand->name,
                'slug' => $product->brand->slug,
            ] : null,
            'categories' => $product->categories->map(function ($cat) {
                return ['id' => $cat->id, 'name' => $cat->name, 'slug' => $cat->slug];
            }),
            'images' => $product->images->map(function ($img) {
                return ['id' => $img->id, 'path' => $img->path, 'sort_order' => $img->sort_order];
            }),
            'variant_types' => $product->variantTypes->map(function ($type) {
                return [
                    'id' => $type->id,
                    'name' => $type->name,
                    'values' => $type->values->map(function ($val) {
                        return ['id' => $val->id, 'value' => $val->value];
                    }),
                ];
            }),
            'variants' => $product->variants->map(function ($variant) {
                return [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'price' => $variant->price,
                    'cost' => $variant->cost,
                    'quantity' => $variant->quantity,
                    'weight' => $variant->weight,
                    'is_active' => $variant->is_active,
                    'option_values' => $variant->values->map(function ($val) {
                        return ['id' => $val->id, 'value' => $val->value];
                    }),
                ];
            }),
            'status' => $product->is_active,
            'has_variants' => $product->has_variants,
            'meta_title' => $product->meta_title,
            'meta_description' => $product->meta_description,
            'created_at' => $product->created_at?->toIso8601String(),
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];

        return $this->logAndRespond($request, $data, 200, $sku);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100|unique:products,sku',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'brand_id' => 'nullable|integer|exists:brands,id',
            'brand_name' => 'nullable|string|max:255',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:categories,id',
            'category_names' => 'nullable|array',
            'category_names.*' => 'string|max:255',
            'quantity' => 'nullable|integer|min:0',
            'weight' => 'nullable|numeric|min:0',
            'length' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'status' => 'nullable|boolean',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'images' => 'nullable|array|max:20',
            'images.*' => 'required|url|max:2048',
            'variants' => 'nullable|array',
            'variants.*.sku' => 'required|string|max:100',
            'variants.*.name' => 'required|string|max:100',
            'variants.*.option_name' => 'required|string|max:100',
            'variants.*.price' => 'required|numeric|min:0',
            'variants.*.cost' => 'nullable|numeric|min:0',
            'variants.*.quantity' => 'nullable|integer|min:0',
            'variants.*.weight' => 'nullable|numeric|min:0',
            'variants.*.status' => 'nullable|boolean',
        ]);

        $storeId = (int) config('app.store_id', 1);
        $hasVariants = !empty($validated['variants']);

        $slug = Str::slug($validated['name']);
        $originalSlug = $slug;
        $counter = 1;
        while (Product::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        $imageUrls = $validated['images'] ?? [];
        $writtenPaths = [];

        try {
            // The image downloads run inside the transaction deliberately. A
            // push is all or nothing: if one image cannot be fetched, the
            // product, its brand, its categories and its variants all have to
            // disappear again rather than leave a half-synced product behind.
            [$product, $uploadedImages] = DB::transaction(function () use ($validated, $storeId, $slug, $hasVariants, $imageUrls, &$writtenPaths) {
                // Resolve brand: brand_id takes priority, then brand_name (find-or-create)
                $brandId = $validated['brand_id'] ?? null;
                if (!$brandId && !empty($validated['brand_name'])) {
                    $brandId = $this->resolveOrCreateBrand($validated['brand_name'], $storeId);
                }

                // Resolve categories: category_ids takes priority, then category_names (find-or-create)
                $categoryIds = $validated['category_ids'] ?? [];
                if (empty($categoryIds) && !empty($validated['category_names'])) {
                    $categoryIds = $this->resolveOrCreateCategories($validated['category_names'], $storeId);
                }

                $product = Product::create([
                    'store_id' => $storeId,
                    'name' => $validated['name'],
                    'sku' => $validated['sku'],
                    'slug' => $slug,
                    'price' => $validated['price'],
                    'description' => $validated['description'] ?? null,
                    'brand_id' => $brandId,
                    'quantity' => $validated['quantity'] ?? 0,
                    'weight' => $validated['weight'] ?? 0,
                    // length/width/height are NOT NULL DEFAULT 0. An explicit
                    // null overrides the column default and blows up the
                    // insert, so an omitted dimension has to become 0 here.
                    'length' => $validated['length'] ?? 0,
                    'width' => $validated['width'] ?? 0,
                    'height' => $validated['height'] ?? 0,
                    'cost' => $validated['cost'] ?? 0,
                    'is_active' => $validated['status'] ?? true,
                    'has_variants' => $hasVariants,
                    'meta_title' => $validated['meta_title'] ?? null,
                    'meta_description' => $validated['meta_description'] ?? null,
                ]);

                if (!empty($categoryIds)) {
                    $product->categories()->sync($categoryIds);
                }

                if ($hasVariants) {
                    $this->syncVariantsFromErp($product, $validated['variants']);
                }

                $imageResult = $this->downloadAndAttachImages($product, $imageUrls, $writtenPaths);

                if (!empty($imageResult['failed'])) {
                    throw new RemoteImageFetchException($imageResult['failed'], count($imageUrls));
                }

                return [$product, $imageResult['uploaded']];
            });
        } catch (RemoteImageFetchException $e) {
            $this->discardProductImageFiles($writtenPaths);

            return $this->logAndRespond($request, $this->imageFailureBody($e, 'created'), 422, $validated['sku']);
        }

        $product->load(['brand:id,name,slug', 'categories:id,name,slug']);

        $data = [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => $product->price,
            'quantity' => $product->quantity,
            'has_variants' => $product->has_variants,
            'status' => $product->is_active,
            'images' => $uploadedImages,
            'created_at' => $product->created_at?->toIso8601String(),
        ];

        if ($product->has_variants) {
            $product->load('variants.values');
            $data['variants'] = $product->variants->map(function ($v) {
                return [
                    'id' => $v->id,
                    'sku' => $v->sku,
                    'price' => $v->price,
                    'cost' => $v->cost,
                    'quantity' => $v->quantity,
                    'weight' => $v->weight,
                    'options' => $v->values->map(fn($val) => $val->value)->implode(' / '),
                ];
            });
        }

        return $this->logAndRespond($request, $data, 201, $product->sku);
    }

    public function update(Request $request, string $sku): JsonResponse
    {
        $product = Product::where('sku', $sku)->first();

        if (!$product) {
            return $this->logAndRespond($request, ['error' => 'Product not found'], 404, $sku);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'sku' => ['sometimes', 'string', 'max:100', Rule::unique('products')->ignore($product->id)],
            'price' => 'sometimes|numeric|min:0',
            'description' => 'nullable|string',
            'brand_id' => 'nullable|integer|exists:brands,id',
            'brand_name' => 'nullable|string|max:255',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:categories,id',
            'category_names' => 'nullable|array',
            'category_names.*' => 'string|max:255',
            'quantity' => 'sometimes|integer|min:0',
            'weight' => 'nullable|numeric|min:0',
            'length' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'status' => 'sometimes|boolean',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'images' => 'nullable|array|max:20',
            'images.*' => 'required|url|max:2048',
            'variants' => 'nullable|array',
            'variants.*.sku' => 'required|string|max:100',
            'variants.*.name' => 'required|string|max:100',
            'variants.*.option_name' => 'required|string|max:100',
            'variants.*.price' => 'required|numeric|min:0',
            'variants.*.cost' => 'nullable|numeric|min:0',
            'variants.*.quantity' => 'nullable|integer|min:0',
            'variants.*.weight' => 'nullable|numeric|min:0',
            'variants.*.status' => 'nullable|boolean',
        ]);

        $storeId = (int) config('app.store_id', 1);
        $imageUrls = $validated['images'] ?? [];
        $writtenPaths = [];

        // Columns that are NOT NULL DEFAULT 0. The ERP is allowed to send them
        // as null (the rules say nullable), but an explicit null overrides the
        // column default and fails the write, so null lands on the default.
        $notNullDefaults = [
            'weight' => 0,
            'length' => 0,
            'width' => 0,
            'height' => 0,
            'cost' => 0,
        ];

        try {
            // As with store(), the downloads run inside the transaction so a
            // single unfetchable image leaves the product exactly as it was.
            $imageResult = DB::transaction(function () use ($request, $product, &$validated, $storeId, $imageUrls, &$writtenPaths, $notNullDefaults) {
                // Resolve brand_name to brand_id if provided
                if (!$request->has('brand_id') && !empty($validated['brand_name'])) {
                    $validated['brand_id'] = $this->resolveOrCreateBrand($validated['brand_name'], $storeId);
                }

                $fillable = [];
                $fieldMap = [
                    'name' => 'name',
                    'sku' => 'sku',
                    'price' => 'price',
                    'description' => 'description',
                    'brand_id' => 'brand_id',
                    'quantity' => 'quantity',
                    'weight' => 'weight',
                    'length' => 'length',
                    'width' => 'width',
                    'height' => 'height',
                    'cost' => 'cost',
                    'meta_title' => 'meta_title',
                    'meta_description' => 'meta_description',
                ];

                foreach ($fieldMap as $input => $column) {
                    if ($request->has($input) || ($input === 'brand_id' && !empty($validated['brand_name']))) {
                        $fillable[$column] = $validated[$input] ?? ($notNullDefaults[$column] ?? null);
                    }
                }

                if ($request->has('status')) {
                    $fillable['is_active'] = $validated['status'];
                }

                if (isset($fillable['name']) && !$request->has('slug')) {
                    $slug = Str::slug($fillable['name']);
                    $originalSlug = $slug;
                    $counter = 1;
                    while (Product::where('slug', $slug)->where('id', '!=', $product->id)->exists()) {
                        $slug = $originalSlug . '-' . $counter++;
                    }
                    $fillable['slug'] = $slug;
                }

                // Handle variants
                if ($request->has('variants')) {
                    if (!empty($validated['variants'])) {
                        $fillable['has_variants'] = true;
                        $product->update($fillable);
                        $this->syncVariantsFromErp($product, $validated['variants']);
                    } else {
                        // Empty variants array = remove all variants
                        $product->variants()->delete();
                        $product->variantTypes()->delete();
                        $fillable['has_variants'] = false;
                        $product->update($fillable);
                    }
                } else {
                    // Variants stay decoupled from a base-price-only update: their
                    // absolute prices don't move, but their modifier deltas need to
                    // refresh against the new base so the admin "+/-" widget reads
                    // truthful values.
                    $basePriceChanged = isset($fillable['price'])
                        && (float) $fillable['price'] !== (float) $product->price;
                    $product->update($fillable);
                    if ($basePriceChanged && $product->has_variants) {
                        foreach ($product->variants as $v) {
                            $v->price_modifier = round((float) $v->price - (float) $product->price, 2);
                            $v->save();
                        }
                    }
                }

                // Resolve category_names to category_ids if provided
                if (!$request->has('category_ids') && !empty($validated['category_names'])) {
                    $categoryIds = $this->resolveOrCreateCategories($validated['category_names'], $storeId);
                    if (!empty($categoryIds)) {
                        $product->categories()->sync($categoryIds);
                    }
                } elseif ($request->has('category_ids')) {
                    $product->categories()->sync($validated['category_ids'] ?? []);
                }

                // Download and attach images from URLs (appends to existing)
                $imageResult = $this->downloadAndAttachImages($product, $imageUrls, $writtenPaths);

                if (!empty($imageResult['failed'])) {
                    throw new RemoteImageFetchException($imageResult['failed'], count($imageUrls));
                }

                return $imageResult;
            });
        } catch (RemoteImageFetchException $e) {
            $this->discardProductImageFiles($writtenPaths);

            // The in-memory model still carries the attributes the rolled-back
            // write assigned, so reload it before anything reads it again.
            $product->refresh();

            return $this->logAndRespond($request, $this->imageFailureBody($e, 'updated'), 422, $sku);
        }

        $product->load(['brand:id,name,slug', 'categories:id,name,slug']);

        $data = [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => $product->price,
            'quantity' => $product->quantity,
            'has_variants' => $product->has_variants,
            'status' => $product->is_active,
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];

        if ($product->has_variants) {
            $product->load('variants.values');
            $data['variants'] = $product->variants->map(function ($v) {
                return [
                    'id' => $v->id,
                    'sku' => $v->sku,
                    'price' => $v->price,
                    'cost' => $v->cost,
                    'quantity' => $v->quantity,
                    'weight' => $v->weight,
                    'options' => $v->values->map(fn($val) => $val->value)->implode(' / '),
                ];
            });
        }

        if (!empty($imageResult['uploaded'])) {
            $data['images'] = $imageResult['uploaded'];
        }

        return $this->logAndRespond($request, $data, 200, $product->sku);
    }

    public function updateQuantity(Request $request, string $sku): JsonResponse
    {
        $product = Product::where('sku', $sku)->first();

        if (!$product) {
            return $this->logAndRespond($request, ['error' => 'Product not found'], 404, $sku);
        }

        $validated = $request->validate([
            'quantity' => 'required|integer|min:0',
        ]);

        $product->update(['quantity' => $validated['quantity']]);

        return $this->logAndRespond($request, [
            'id' => $product->id,
            'sku' => $product->sku,
            'quantity' => $product->quantity,
            'updated_at' => $product->updated_at?->toIso8601String(),
        ], 200, $sku);
    }

    public function uploadImages(Request $request, string $sku): JsonResponse
    {
        $product = Product::where('sku', $sku)->with('brand:id,slug')->first();

        if (!$product) {
            return $this->logAndRespond($request, ['error' => 'Product not found'], 404, $sku);
        }

        $hasFiles = $request->hasFile('images');
        $hasUrls = $request->filled('image_urls') || (!$hasFiles && $request->filled('images'));

        if (!$hasFiles && !$hasUrls) {
            return $this->logAndRespond($request, ['error' => 'Provide images (file upload) or image_urls (array of URLs)'], 422, $sku);
        }

        if ($hasFiles) {
            $request->validate([
                'images' => 'required|array',
                'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
            ]);
        }

        if ($hasUrls) {
            $urlField = $request->filled('image_urls') ? 'image_urls' : 'images';
            $request->validate([
                $urlField => 'required|array|max:20',
                $urlField . '.*' => 'required|url|max:2048',
            ]);
        }

        $brandSlug = $product->brand?->slug ?? '';
        $storagePath = $brandSlug ? 'products/' . $brandSlug : 'products/_unbranded';
        $urls = $hasUrls ? ($request->input('image_urls') ?? $request->input('images')) : [];
        $writtenPaths = [];

        try {
            $uploaded = DB::transaction(function () use ($request, $product, $storagePath, $hasFiles, $urls, &$writtenPaths) {
                $uploaded = [];
                $failed = [];
                $maxSort = ProductImage::where('product_id', $product->id)->max('sort_order') ?? -1;

                // Handle file uploads
                if ($hasFiles) {
                    foreach ($request->file('images') as $image) {
                        $path = $image->store($storagePath, 'public');
                        $writtenPaths[] = $path;
                        $maxSort++;

                        $record = ProductImage::create([
                            'product_id' => $product->id,
                            'path' => $path,
                            'sort_order' => $maxSort,
                        ]);

                        $uploaded[] = [
                            'id' => $record->id,
                            'path' => $path,
                            'sort_order' => $record->sort_order,
                        ];
                    }
                }

                // Handle URL downloads
                $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

                foreach ($urls as $url) {
                    $result = $this->fetchRemoteImage($url, self::IMAGE_ALLOWED_TYPES);

                    if (!$result['ok']) {
                        $failed[] = ['url' => $url, 'reason' => $result['reason']];
                        continue;
                    }

                    $ext = $extensions[$result['mime']] ?? 'jpg';
                    $path = $storagePath . '/' . Str::random(40) . '.' . $ext;

                    Storage::disk('public')->put($path, $result['body']);
                    $writtenPaths[] = $path;
                    $maxSort++;

                    $record = ProductImage::create([
                        'product_id' => $product->id,
                        'path' => $path,
                        'sort_order' => $maxSort,
                    ]);

                    $uploaded[] = [
                        'id' => $record->id,
                        'path' => $path,
                        'sort_order' => $record->sort_order,
                    ];
                }

                if (!empty($failed)) {
                    throw new RemoteImageFetchException($failed, count($urls));
                }

                return $uploaded;
            });
        } catch (RemoteImageFetchException $e) {
            $this->discardProductImageFiles($writtenPaths);

            $failedCount = count($e->failures);

            return $this->logAndRespond($request, [
                'error' => 'images_unavailable',
                'message' => "No images attached: {$failedCount} of {$e->attempted} image URL(s) could not be fetched. "
                    . 'Every image in this request was rolled back, so the product still has exactly the images it had before. '
                    . 'Fix the image URLs listed below and push again.',
                'images_failed' => $e->failures,
            ], 422, $sku);
        }

        return $this->logAndRespond($request, [
            'message' => count($uploaded) . ' image(s) uploaded',
            'images' => $uploaded,
        ], 200, $sku);
    }

    public function destroy(Request $request, string $sku): JsonResponse
    {
        $product = Product::where('sku', $sku)->first();

        if (!$product) {
            return $this->logAndRespond($request, ['error' => 'Product not found'], 404, $sku);
        }

        // Hard delete, so the SKU is free again for the next ERP push. Every
        // product-owned table (variants, variant types and their values,
        // images, specials, category and tag links, reviews, stock
        // notifications, seo suggestions) hangs off an ON DELETE CASCADE
        // foreign key and is removed by the database. Image FILES stay on
        // disk: catalog media is shared between products.
        DB::transaction(function () use ($product) {
            $product->delete();
        });

        return $this->logAndRespond($request, ['status' => 'deleted', 'sku' => $sku], 200, $sku);
    }

    /**
     * Sync variants from ERP flat format.
     *
     * Input: [{sku, name, option_name, price, cost, quantity, weight, status}]
     *
     * Groups by option_name to auto-build variant types, creates variants,
     * attaches values, and syncs product quantity.
     */
    private function syncVariantsFromErp(Product $product, array $variants): void
    {
        // Delete existing variants and types
        $product->variants()->delete();
        $product->variantTypes()->delete();

        // Group by option_name to build variant types
        // e.g. "Color" => ["Red", "Blue"], "Size" => ["S", "M"]
        $typeGroups = [];
        foreach ($variants as $v) {
            $optionName = $v['option_name'];
            $value = $v['name'];
            if (!isset($typeGroups[$optionName])) {
                $typeGroups[$optionName] = [];
            }
            if (!in_array($value, $typeGroups[$optionName])) {
                $typeGroups[$optionName][] = $value;
            }
        }

        // Create variant types and values, build lookup map
        $valueIdMap = []; // "option_name:value" => variant_type_value.id
        $sortOrder = 0;
        foreach ($typeGroups as $typeName => $values) {
            $variantType = $product->variantTypes()->create([
                'name' => $typeName,
                'sort_order' => $sortOrder++,
            ]);

            foreach ($values as $valueIndex => $valueText) {
                $typeValue = $variantType->values()->create([
                    'value' => $valueText,
                    'sort_order' => $valueIndex,
                ]);
                $valueIdMap["{$typeName}:{$valueText}"] = $typeValue->id;
            }
        }

        // Create each variant and attach its type values
        foreach ($variants as $v) {
            $variant = $product->variants()->create([
                'sku' => $v['sku'],
                'price' => $v['price'],
                // product_variants.cost is NOT NULL DEFAULT 0, so an omitted
                // cost has to land on the default rather than an explicit null.
                'cost' => $v['cost'] ?? 0,
                'quantity' => $v['quantity'] ?? 0,
                'weight' => $v['weight'] ?? null,
                'is_active' => $v['status'] ?? true,
            ]);

            $valueKey = "{$v['option_name']}:{$v['name']}";
            if (isset($valueIdMap[$valueKey])) {
                $variant->values()->attach($valueIdMap[$valueKey]);
            }
        }

        // Sync product quantity and flag
        $product->update([
            'has_variants' => true,
            'quantity' => $product->variants()->sum('quantity'),
        ]);
    }

    /**
     * Download images from URLs and attach them to a product.
     *
     * Every file written is appended to $writtenPaths so the caller can undo
     * the disk side of a rolled-back push. Nothing else may be added to that
     * list: it is the only evidence of which files are this request's to
     * delete.
     *
     * @param  array<int, string>  $urls
     * @param  array<int, string>  $writtenPaths
     */
    private function downloadAndAttachImages(Product $product, array $urls, array &$writtenPaths = []): array
    {
        $uploaded = [];
        $failed = [];

        if (empty($urls)) {
            return compact('uploaded', 'failed');
        }

        $product->loadMissing('brand:id,slug');
        $brandSlug = $product->brand?->slug ?? '';
        $storagePath = $brandSlug ? 'products/' . $brandSlug : 'products/_unbranded';
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

        $maxSort = ProductImage::where('product_id', $product->id)->max('sort_order') ?? -1;

        foreach ($urls as $url) {
            $result = $this->fetchRemoteImage($url, self::IMAGE_ALLOWED_TYPES);

            if (!$result['ok']) {
                $failed[] = ['url' => $url, 'reason' => $result['reason']];
                continue;
            }

            $ext = $extensions[$result['mime']] ?? 'jpg';
            $path = $storagePath . '/' . Str::random(40) . '.' . $ext;

            Storage::disk('public')->put($path, $result['body']);
            $writtenPaths[] = $path;
            $maxSort++;

            $record = ProductImage::create([
                'product_id' => $product->id,
                'path' => $path,
                'sort_order' => $maxSort,
            ]);

            // Set as main image if first
            if ($maxSort === 0 && !$product->image) {
                $product->update(['image' => $path]);
            }

            $uploaded[] = [
                'id' => $record->id,
                'path' => $path,
                'sort_order' => $record->sort_order,
            ];
        }

        return compact('uploaded', 'failed');
    }

    /**
     * Body of the 422 a failed image fetch produces.
     *
     * `images_failed` keeps the key and the {url, reason} shape the ERP
     * already reads, so an existing client keeps understanding the payload
     * and only has to notice that the status is no longer a success.
     */
    private function imageFailureBody(RemoteImageFetchException $e, string $verb): array
    {
        $failedCount = count($e->failures);

        return [
            'error' => 'images_unavailable',
            'message' => "Product not {$verb}: {$failedCount} of {$e->attempted} image(s) could not be fetched. "
                . 'The whole push was rolled back, so nothing in the store changed. '
                . 'Fix the image URLs listed below and push again.',
            'images_failed' => $e->failures,
        ];
    }

    /**
     * Delete the files a rolled-back push wrote, and only those.
     */
    private function discardProductImageFiles(array $paths): void
    {
        $this->discardWrittenFiles($paths, function (string $path) {
            return ProductImage::where('path', $path)->exists()
                || Product::where('image', $path)->exists();
        });
    }

    /**
     * Find a brand by name (case-insensitive) or create it.
     */
    private function resolveOrCreateBrand(string $name, int $storeId): int
    {
        $name = trim($name);
        if ($name === '') return 0;

        $brand = Brand::where('store_id', $storeId)
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->first();

        if ($brand) {
            return $brand->id;
        }

        $brand = Brand::create([
            'store_id'  => $storeId,
            'name'      => $name,
            'slug'      => Str::slug($name),
            'is_active' => true,
        ]);

        return $brand->id;
    }

    /**
     * Find categories by name (case-insensitive) or create them.
     * Returns array of category IDs.
     */
    private function resolveOrCreateCategories(array $names, int $storeId): array
    {
        $ids = [];

        foreach ($names as $name) {
            $name = trim($name);
            if ($name === '') continue;

            $category = Category::where('store_id', $storeId)
                ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                ->first();

            if ($category) {
                $ids[] = $category->id;
            } else {
                $category = Category::create([
                    'store_id'  => $storeId,
                    'name'      => $name,
                    'slug'      => Str::slug($name),
                    'is_active' => true,
                ]);
                $ids[] = $category->id;
            }
        }

        return $ids;
    }
}
