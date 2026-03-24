<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:categories,id',
            'quantity' => 'nullable|integer|min:0',
            'weight' => 'nullable|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'status' => 'nullable|boolean',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
        ]);

        $slug = Str::slug($validated['name']);
        $originalSlug = $slug;
        $counter = 1;
        while (Product::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        $product = Product::create([
            'store_id' => (int) config('app.store_id', 1),
            'name' => $validated['name'],
            'sku' => $validated['sku'],
            'slug' => $slug,
            'price' => $validated['price'],
            'description' => $validated['description'] ?? null,
            'brand_id' => $validated['brand_id'] ?? null,
            'quantity' => $validated['quantity'] ?? 0,
            'weight' => $validated['weight'] ?? 0,
            'cost' => $validated['cost'] ?? 0,
            'is_active' => $validated['status'] ?? true,
            'meta_title' => $validated['meta_title'] ?? null,
            'meta_description' => $validated['meta_description'] ?? null,
        ]);

        if (!empty($validated['category_ids'])) {
            $product->categories()->sync($validated['category_ids']);
        }

        $product->load(['brand:id,name,slug', 'categories:id,name,slug']);

        $data = [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => $product->price,
            'status' => $product->is_active,
            'created_at' => $product->created_at?->toIso8601String(),
        ];

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
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:categories,id',
            'quantity' => 'sometimes|integer|min:0',
            'weight' => 'nullable|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'status' => 'sometimes|boolean',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
        ]);

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
            if ($request->has($input)) {
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

        if ($request->has('category_ids')) {
            $product->categories()->sync($validated['category_ids'] ?? []);
        }

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

        $request->validate([
            'images' => 'required|array',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $brandSlug = $product->brand?->slug ?? 'unsorted';
        $storagePath = 'products/' . $brandSlug;
        $uploaded = [];

        $maxSort = ProductImage::where('product_id', $product->id)->max('sort_order') ?? -1;

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

        return $this->logAndRespond($request, [
            'message' => count($uploaded) . ' image(s) uploaded',
            'images' => $uploaded,
        ], 200, $sku);
    }
}
