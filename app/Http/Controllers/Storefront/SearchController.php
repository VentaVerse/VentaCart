<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function results(Request $request)
    {
        $query = trim($request->input('q', ''));
        $sort = $request->input('sort', 'relevance');

        $products = Product::where('is_active', true);

        if ($query) {
            $products->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('sku', 'LIKE', "%{$query}%")
                  ->orWhere('description', 'LIKE', "%{$query}%");
            });
        }

        switch ($sort) {
            case 'date_added':
                $products->latest('date_available');
                break;
            case 'price_asc':
                $products->orderBy('price', 'asc');
                break;
            case 'price_desc':
                $products->orderBy('price', 'desc');
                break;
            case 'name_asc':
                $products->orderBy('name', 'asc');
                break;
            case 'name_desc':
                $products->orderBy('name', 'desc');
                break;
            case 'newest':
                $products->latest();
                break;
            default:
                $products->latest();
        }

        $products = $products->with(['images', 'specials', 'brand'])
            ->withAvg(['reviews as reviews_avg_rating' => fn($q) => $q->where('status', 'approved')], 'rating')
            ->withCount(['reviews as reviews_count' => fn($q) => $q->where('status', 'approved')])
            ->paginate(12)->withQueryString();

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
                  ->orWhere('sku', 'LIKE', "%{$query}%")
                  ->orWhere('model', 'LIKE', "%{$query}%");
            })
            ->with('images')
            ->limit(6)
            ->get()
            ->map(function ($product) {
                $image = $product->images->sortBy('sort_order')->first();
                return [
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'price' => number_format($product->price, 2),
                    'image' => $image ? asset('storage/' . $image->path) : null,
                    'url' => route('product.show', $product->slug),
                ];
            });

        return response()->json($products);
    }
}
