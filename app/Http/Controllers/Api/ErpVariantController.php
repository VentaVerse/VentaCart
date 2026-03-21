<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ErpVariantController extends Controller
{
    use LogsApiCalls;

    public function show(Request $request, string $sku): JsonResponse
    {
        $variant = ProductVariant::where('sku', $sku)
            ->with(['product:id,name,sku,slug', 'values'])
            ->first();

        if (!$variant) {
            return $this->logAndRespond($request, ['error' => 'Variant not found'], 404, $sku);
        }

        $data = [
            'id' => $variant->id,
            'sku' => $variant->sku,
            'price' => $variant->price,
            'cost' => $variant->cost,
            'quantity' => $variant->quantity,
            'weight' => $variant->weight,
            'is_active' => $variant->is_active,
            'product' => [
                'id' => $variant->product->id,
                'name' => $variant->product->name,
                'sku' => $variant->product->sku,
                'slug' => $variant->product->slug,
            ],
            'option_values' => $variant->values->map(function ($val) {
                return ['id' => $val->id, 'value' => $val->value];
            }),
        ];

        return $this->logAndRespond($request, $data, 200, $sku);
    }

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
            'status' => 'sometimes|boolean',
        ]);

        $fillable = [];

        if ($request->has('quantity')) {
            $fillable['quantity'] = $validated['quantity'];
        }
        if ($request->has('price')) {
            $fillable['price'] = $validated['price'];
        }
        if ($request->has('cost')) {
            $fillable['cost'] = $validated['cost'] ?? null;
        }
        if ($request->has('status')) {
            $fillable['is_active'] = $validated['status'];
        }

        $variant->update($fillable);

        $variant->load(['product:id,name,sku', 'values']);

        $data = [
            'id' => $variant->id,
            'sku' => $variant->sku,
            'price' => $variant->price,
            'cost' => $variant->cost,
            'quantity' => $variant->quantity,
            'is_active' => $variant->is_active,
            'product_sku' => $variant->product->sku,
            'updated_at' => $variant->updated_at?->toIso8601String(),
        ];

        return $this->logAndRespond($request, $data, 200, $sku);
    }
}
