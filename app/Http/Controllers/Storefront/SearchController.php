<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\ProductListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function results(Request $request)
    {
        $query = trim($request->input('q', ''));
        $sort = ProductListing::searchSort($request->input('sort'));

        $products = Product::where('is_active', true);

        if ($query) {
            $products->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('sku', 'LIKE', "%{$query}%")
                  ->orWhere('description', 'LIKE', "%{$query}%");
            });
        }

        ProductListing::applyOnSale($products, $request->query(ProductListing::ON_SALE));
        ProductListing::applySort($products, $sort);

        $products = $products->with(['images', 'specials', 'brand', \App\Support\ProductAttributes::CARD_RELATION])
            ->when(\App\Support\Reviews::enabled(), fn ($rq) => $rq

                ->withAvg(['reviews as reviews_avg_rating' => fn($q) => $q->where('status', 'approved')], 'rating')

                ->withCount(['reviews as reviews_count' => fn($q) => $q->where('status', 'approved')]))
            ->paginate(ProductListing::perPage())->withQueryString();

        return view('search.results', compact('products', 'query', 'sort'));
    }

    public function autocomplete(Request $request): JsonResponse
    {
        $query = trim($request->input('q', ''));

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $products = Product::where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('sku', 'LIKE', "%{$query}%");
            })
            ->with(['images', 'specials'])
            ->limit(6)
            ->get()
            ->map(function ($product) {
                $image = $product->images->sortBy('sort_order')->first();
                return [
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'price' => \App\Support\Pricing::display($product)->price,
                    'image' => $image ? asset('storage/' . $image->path) : null,
                    'url' => route('product.show', $product->slug),
                ];
            });

        return response()->json($products);
    }
}
