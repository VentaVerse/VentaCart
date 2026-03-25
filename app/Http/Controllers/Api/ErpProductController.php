<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ErpProductController extends Controller
{
    use LogsApiCalls;

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
            ->with(['brand:id,name,slug', 'categories:id,name,slug', 'images', 'variants.values', 'variants.groupPrices', 'variantTypes.values'])
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
                    'group_prices' => $variant->groupPrices->map(function ($gp) {
                        return [
                            'customer_group_id' => $gp->customer_group_id,
                            'price' => $gp->price,
                        ];
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
            'cost' => 'nullable|numeric|min:0',
            'status' => 'nullable|boolean',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'images' => 'nullable|array|max:20',
            'images.*' => 'required|url|max:2048',
        ]);

        $storeId = (int) config('app.store_id', 1);

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

        $slug = Str::slug($validated['name']);
        $originalSlug = $slug;
        $counter = 1;
        while (Product::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
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
            'cost' => $validated['cost'] ?? 0,
            'is_active' => $validated['status'] ?? true,
            'meta_title' => $validated['meta_title'] ?? null,
            'meta_description' => $validated['meta_description'] ?? null,
        ]);

        if (!empty($categoryIds)) {
            $product->categories()->sync($categoryIds);
        }

        // Download and attach images from URLs
        $imageResult = $this->downloadAndAttachImages($product, $validated['images'] ?? []);

        $product->load(['brand:id,name,slug', 'categories:id,name,slug']);

        $data = [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => $product->price,
            'status' => $product->is_active,
            'images' => $imageResult['uploaded'],
            'created_at' => $product->created_at?->toIso8601String(),
        ];

        if (!empty($imageResult['failed'])) {
            $data['images_failed'] = $imageResult['failed'];
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
            'cost' => 'nullable|numeric|min:0',
            'status' => 'sometimes|boolean',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'images' => 'nullable|array|max:20',
            'images.*' => 'required|url|max:2048',
        ]);

        $storeId = (int) config('app.store_id', 1);

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
            'cost' => 'cost',
            'meta_title' => 'meta_title',
            'meta_description' => 'meta_description',
        ];

        foreach ($fieldMap as $input => $column) {
            if ($request->has($input) || ($input === 'brand_id' && !empty($validated['brand_name']))) {
                $fillable[$column] = $validated[$input] ?? null;
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

        $product->update($fillable);

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
        $imageResult = $this->downloadAndAttachImages($product, $validated['images'] ?? []);

        $product->load(['brand:id,name,slug', 'categories:id,name,slug']);

        $data = [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => $product->price,
            'quantity' => $product->quantity,
            'status' => $product->is_active,
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];

        if (!empty($imageResult['uploaded'])) {
            $data['images'] = $imageResult['uploaded'];
        }
        if (!empty($imageResult['failed'])) {
            $data['images_failed'] = $imageResult['failed'];
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

        $brandSlug = $product->brand?->slug ?? 'unsorted';
        $storagePath = 'products/' . $brandSlug;
        $uploaded = [];
        $failed = [];

        $maxSort = ProductImage::where('product_id', $product->id)->max('sort_order') ?? -1;

        // Handle file uploads
        if ($hasFiles) {
            foreach ($request->file('images') as $image) {
                $path = $image->store($storagePath, 'public');
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
        if ($hasUrls) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            $maxSize = 5 * 1024 * 1024; // 5MB

            $urls = $request->input('image_urls') ?? $request->input('images');
            foreach ($urls as $url) {
                try {
                    $response = \Illuminate\Support\Facades\Http::timeout(15)->get($url);

                    if (!$response->successful()) {
                        $failed[] = ['url' => $url, 'reason' => 'HTTP ' . $response->status()];
                        continue;
                    }

                    $contentType = $response->header('Content-Type');
                    $mimeType = explode(';', $contentType)[0] ?? '';

                    if (!in_array($mimeType, $allowedTypes)) {
                        $failed[] = ['url' => $url, 'reason' => 'Invalid type: ' . $mimeType];
                        continue;
                    }

                    $body = $response->body();

                    if (strlen($body) > $maxSize) {
                        $failed[] = ['url' => $url, 'reason' => 'Exceeds 5MB limit'];
                        continue;
                    }

                    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                    $ext = $extensions[$mimeType] ?? 'jpg';
                    $filename = Str::random(40) . '.' . $ext;

                    \Illuminate\Support\Facades\Storage::disk('public')->put($storagePath . '/' . $filename, $body);
                    $path = $storagePath . '/' . $filename;
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
                } catch (\Exception $e) {
                    $failed[] = ['url' => $url, 'reason' => 'Download failed: ' . $e->getMessage()];
                }
            }
        }

        $response = [
            'message' => count($uploaded) . ' image(s) uploaded',
            'images' => $uploaded,
        ];

        if (!empty($failed)) {
            $response['failed'] = $failed;
        }

        return $this->logAndRespond($request, $response, 200, $sku);
    }

    /**
     * Download images from URLs and attach them to a product.
     */
    private function downloadAndAttachImages(Product $product, array $urls): array
    {
        $uploaded = [];
        $failed = [];

        if (empty($urls)) {
            return compact('uploaded', 'failed');
        }

        $product->loadMissing('brand:id,slug');
        $brandSlug = $product->brand?->slug ?? 'unsorted';
        $storagePath = 'products/' . $brandSlug;
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize = 5 * 1024 * 1024;
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

        $maxSort = ProductImage::where('product_id', $product->id)->max('sort_order') ?? -1;

        foreach ($urls as $url) {
            try {
                $response = \Illuminate\Support\Facades\Http::timeout(15)->get($url);

                if (!$response->successful()) {
                    $failed[] = ['url' => $url, 'reason' => 'HTTP ' . $response->status()];
                    continue;
                }

                $contentType = explode(';', $response->header('Content-Type') ?? '')[0];

                if (!in_array($contentType, $allowedTypes)) {
                    $failed[] = ['url' => $url, 'reason' => 'Invalid type: ' . $contentType];
                    continue;
                }

                $body = $response->body();

                if (strlen($body) > $maxSize) {
                    $failed[] = ['url' => $url, 'reason' => 'Exceeds 5MB limit'];
                    continue;
                }

                $ext = $extensions[$contentType] ?? 'jpg';
                $filename = Str::random(40) . '.' . $ext;

                \Illuminate\Support\Facades\Storage::disk('public')->put($storagePath . '/' . $filename, $body);
                $path = $storagePath . '/' . $filename;
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
            } catch (\Exception $e) {
                $failed[] = ['url' => $url, 'reason' => 'Download failed: ' . $e->getMessage()];
            }
        }

        return compact('uploaded', 'failed');
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
