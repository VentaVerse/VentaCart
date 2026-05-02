<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Product;
use App\Models\Tag;
use App\Services\ThemeService;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function index()
    {
        $brands = Brand::where('is_active', true)
            ->withCount(['products' => fn($q) => $q->where('is_active', true)])
            ->having('products_count', '>', 0)
            ->orderBy('name')
            ->get();

        view()->share('canonical', route('brand.index'));

        return view('brand.index', compact('brands'));
    }

    public function show(Request $request, Brand $brand, ThemeService $theme)
    {
        if (!$brand->is_active) {
            abort(404);
        }

        $settings = $theme->getGroup('category_page');
        $perPage = $settings['products_per_page'] ?? 12;

        $query = Product::where('is_active', true)
            ->where('brand_id', $brand->id)
            ->with(['images', 'specials', 'brand'])
            ->withAvg(['reviews as reviews_avg_rating' => fn($q) => $q->where('status', 'approved')], 'rating')
            ->withCount(['reviews as reviews_count' => fn($q) => $q->where('status', 'approved')]);

        // Filters
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->input('min_price'));
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->input('max_price'));
        }
        if ($request->filled('in_stock')) {
            $query->where(function ($q) {
                $q->where('quantity', '>', 0)->orWhere('track_stock', false);
            });
        }
        if ($request->filled('on_sale')) {
            $query->whereHas('specials', function ($q) {
                $q->where('display_mode', 'sale')
                    ->where(function ($q2) {
                        $q2->whereNull('date_start')->orWhere('date_start', '<=', now());
                    })
                    ->where(function ($q2) {
                        $q2->whereNull('date_end')->orWhere('date_end', '>=', now());
                    });
            });
        }
        if ($request->filled('tag')) {
            $tags = (array) $request->input('tag');
            $query->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $tags));
        }

        // Sorting
        $sort = $request->input('sort', 'date_added');
        switch ($sort) {
            case 'date_added':
                $query->latest('date_available');
                break;
            case 'price_asc':
                $query->orderBy('price', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('price', 'desc');
                break;
            case 'name_asc':
                $query->orderBy('name', 'asc');
                break;
            case 'name_desc':
                $query->orderBy('name', 'desc');
                break;
            case 'newest':
                $query->latest();
                break;
            default:
                $query->latest('date_available');
        }

        $products = $query->paginate($perPage)->withQueryString();

        // Filter options
        $allBrandProducts = Product::where('is_active', true)->where('brand_id', $brand->id);
        $productIds = (clone $allBrandProducts)->pluck('id');
        $priceRange = Product::whereIn('id', $productIds)
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')->first();
        $availableBrands = collect(); // Not needed on brand page but required by sidebar partial
        $availableTags = Tag::whereHas('products', function ($q) use ($brand) {
            $q->where('is_active', true)->where('brand_id', $brand->id);
        })->orderBy('name')->get();

        $ogTitle = $brand->og_title ?: null;
        $ogDescription = $brand->og_description ?: null;

        $page = (int) request()->query('page', 1);
        view()->share('canonical', route('brand.show', $brand->slug) . ($page > 1 ? '?page=' . $page : ''));

        return view('brand.show', compact(
            'brand', 'products', 'sort', 'settings',
            'priceRange', 'availableBrands', 'availableTags',
            'ogTitle', 'ogDescription'
        ));
    }
}
