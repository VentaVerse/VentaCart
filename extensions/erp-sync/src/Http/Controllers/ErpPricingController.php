<?php

namespace Extensions\ErpSync\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use Extensions\ErpSync\Http\PriceRows;
use Extensions\ErpSync\Http\Requests\PutDiscountsRequest;
use Extensions\ErpSync\Http\Requests\PutSpecialsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * A product's special prices and discounts, read and replaced by the ERP.
 *
 * A PUT replaces the whole list in one transaction, so a sync that manages a
 * store's promotions also replaces rows edited by hand in the admin. Prices
 * belong to the product, never to one variant: a variant SKU is answered
 * with the product SKU to send instead.
 */
class ErpPricingController extends Controller
{
    use \App\Http\Controllers\Api\LogsApiCalls;

    public function specials(Request $request, string $sku): JsonResponse
    {
        $product = Product::where('sku', $sku)->first();
        if (! $product) {
            return $this->notFound($request, $sku);
        }

        return $this->logAndRespond($request, ['specials' => PriceRows::specials($product)], 200, $sku);
    }

    public function replaceSpecials(PutSpecialsRequest $request, string $sku): JsonResponse
    {
        $product = Product::where('sku', $sku)->first();
        if (! $product) {
            return $this->notFound($request, $sku);
        }

        $rows = $request->validated('specials');

        DB::transaction(function () use ($product, $rows) {
            $product->specials()->delete();

            // The model reads each offset and stores the moment on the
            // store's clock.
            foreach ($rows as $row) {
                $product->specials()->create([
                    'price_type' => $row['type'],
                    'value' => $row['value'],
                    'date_start' => $row['starts_at'] ?? null,
                    'date_end' => $row['ends_at'],
                ]);
            }
        });

        $product->unsetRelation('specials');

        return $this->logAndRespond($request, ['specials' => PriceRows::specials($product)], 200, $sku);
    }

    public function discounts(Request $request, string $sku): JsonResponse
    {
        $product = Product::where('sku', $sku)->first();
        if (! $product) {
            return $this->notFound($request, $sku);
        }

        return $this->logAndRespond($request, ['discounts' => PriceRows::discounts($product)], 200, $sku);
    }

    public function replaceDiscounts(PutDiscountsRequest $request, string $sku): JsonResponse
    {
        $product = Product::where('sku', $sku)->first();
        if (! $product) {
            return $this->notFound($request, $sku);
        }

        $rows = $request->validated('discounts');

        DB::transaction(function () use ($product, $rows) {
            $product->discounts()->delete();

            foreach ($rows as $row) {
                $product->discounts()->create([
                    'customer_group_id' => $row['customer_group_id'],
                    'quantity' => (int) $row['min_quantity'],
                    'price_type' => $row['type'],
                    'value' => $row['value'],
                ]);
            }
        });

        $product->unsetRelation('discounts');

        return $this->logAndRespond($request, ['discounts' => PriceRows::discounts($product)], 200, $sku);
    }

    /**
     * The 404 for a SKU that is not a product's. A variant's SKU is answered
     * with its product's SKU, since that is the one to send.
     */
    private function notFound(Request $request, string $sku): JsonResponse
    {
        $parentSku = ProductVariant::where('sku', $sku)->first()?->product?->sku;

        $error = $parentSku !== null
            ? "{$sku} is a variant; prices belong to product {$parentSku}"
            : 'Product not found';

        return $this->logAndRespond($request, ['error' => $error], 404, $sku);
    }
}
