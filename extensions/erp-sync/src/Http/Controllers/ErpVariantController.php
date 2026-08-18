<?php

namespace Extensions\ErpSync\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ErpVariantController extends Controller
{
    use \App\Http\Controllers\Api\LogsApiCalls;

    /**
     * GET /api/v1/products/{sku}/variants
     * List all variants for a product.
     */
    public function index(Request $request, string $sku): JsonResponse
    {
        $product = Product::where('sku', $sku)
            ->with(['variantTypes.values', 'variants.values'])
            ->first();

        if (!$product) {
            return $this->logAndRespond($request, ['error' => 'Product not found'], 404, $sku);
        }

        $data = [
            'product_id' => $product->id,
            'product_sku' => $product->sku,
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
                return $this->formatVariant($variant);
            }),
        ];

        return $this->logAndRespond($request, $data, 200, $sku);
    }

    /**
     * GET /api/v1/variants/{sku}
     * Show a single variant by its SKU.
     */
    public function show(Request $request, string $sku): JsonResponse
    {
        $variant = ProductVariant::where('sku', $sku)
            ->with(['product:id,name,sku,slug', 'values'])
            ->first();

        if (!$variant) {
            return $this->logAndRespond($request, ['error' => 'Variant not found'], 404, $sku);
        }

        $data = $this->formatVariant($variant);
        $data['product'] = [
            'id' => $variant->product->id,
            'name' => $variant->product->name,
            'sku' => $variant->product->sku,
            'slug' => $variant->product->slug,
        ];

        return $this->logAndRespond($request, $data, 200, $sku);
    }

    /**
     * PUT /api/v1/variants/{sku}
     * Update a variant's price, quantity, cost, status.
     */
    public function update(Request $request, string $sku): JsonResponse
    {
        $variant = ProductVariant::where('sku', $sku)->first();

        if (!$variant) {
            return $this->logAndRespond($request, ['error' => 'Variant not found'], 404, $sku);
        }

        $validated = $request->validate([
            'quantity' => 'sometimes|integer|min:0',
            'price' => 'sometimes|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'weight' => 'nullable|numeric|min:0',
            'status' => 'sometimes|boolean',
        ]);

        $fillable = [];
        if ($request->has('quantity')) $fillable['quantity'] = $validated['quantity'];
        if ($request->has('price')) $fillable['price'] = $validated['price'];
        if ($request->has('cost')) $fillable['cost'] = $validated['cost'] ?? null;
        if ($request->has('weight')) $fillable['weight'] = $validated['weight'] ?? null;
        if ($request->has('status')) $fillable['is_active'] = $validated['status'];

        $variant->update($fillable);

        // Sync parent product quantity
        $product = $variant->product;
        if ($product->has_variants) {
            $product->update(['quantity' => $product->variants()->sum('quantity')]);
        }

        $variant->load(['product:id,name,sku', 'values']);

        $data = $this->formatVariant($variant);
        $data['product_sku'] = $variant->product->sku;

        return $this->logAndRespond($request, $data, 200, $sku);
    }

    /**
     * POST /api/v1/products/{sku}/variants
     * Create variants on an existing product.
     * Expects variant_types + variants arrays.
     */
    public function store(Request $request, string $sku): JsonResponse
    {
        $product = Product::where('sku', $sku)->first();

        if (!$product) {
            return $this->logAndRespond($request, ['error' => 'Product not found'], 404, $sku);
        }

        $validated = $request->validate([
            'variant_types' => 'required|array|min:1|max:2',
            'variant_types.*.name' => 'required|string|max:100',
            'variant_types.*.values' => 'required|array|min:1',
            'variant_types.*.values.*' => 'required|string|max:100',
            'variants' => 'required|array|min:1',
            'variants.*.sku' => 'required|string|max:100',
            'variants.*.price' => 'required|numeric|min:0',
            'variants.*.quantity' => 'nullable|integer|min:0',
            'variants.*.cost' => 'nullable|numeric|min:0',
            'variants.*.weight' => 'nullable|numeric|min:0',
            'variants.*.status' => 'nullable|boolean',
            'variants.*.value_keys' => 'required|array',
            'variants.*.value_keys.*' => 'required|string',
        ]);

        // Delete existing variant types and variants
        $product->variants()->delete();
        $product->variantTypes()->delete();

        // Create variant types
        $typeValueMap = [];
        foreach ($validated['variant_types'] as $typeIndex => $typeData) {
            $variantType = $product->variantTypes()->create([
                'name' => $typeData['name'],
                'sort_order' => $typeIndex,
            ]);

            foreach ($typeData['values'] as $valueIndex => $valueText) {
                $typeValue = $variantType->values()->create([
                    'value' => $valueText,
                    'sort_order' => $valueIndex,
                ]);
                $typeValueMap["{$typeIndex}_{$valueIndex}"] = $typeValue->id;
            }
        }

        // Create variants
        $created = [];
        foreach ($validated['variants'] as $variantData) {
            $variant = $product->variants()->create([
                'sku' => $variantData['sku'],
                'price' => $variantData['price'],
                'quantity' => $variantData['quantity'] ?? 0,
                'cost' => $variantData['cost'] ?? null,
                'weight' => $variantData['weight'] ?? null,
                'is_active' => $variantData['status'] ?? true,
            ]);

            // Attach variant type values
            $valueIds = [];
            foreach ($variantData['value_keys'] as $key) {
                if (isset($typeValueMap[$key])) {
                    $valueIds[] = $typeValueMap[$key];
                }
            }
            $variant->values()->attach($valueIds);

            $created[] = [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'price' => $variant->price,
                'quantity' => $variant->quantity,
            ];
        }

        $product->update([
            'has_variants' => true,
            'quantity' => $product->variants()->sum('quantity'),
        ]);

        $data = [
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'variants_created' => count($created),
            'variants' => $created,
        ];

        return $this->logAndRespond($request, $data, 201, $sku);
    }

    private function formatVariant(ProductVariant $variant): array
    {
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
            'updated_at' => $variant->updated_at?->toIso8601String(),
        ];
    }
}
