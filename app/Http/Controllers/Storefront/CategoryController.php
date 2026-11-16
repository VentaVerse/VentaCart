<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Product;
use App\Models\Tag;
use App\Services\ThemeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    public function show(Request $request, Category $category, ThemeService $theme)
    {
        if (!$category->is_active) {
            abort(404);
        }

        $settings = $theme->getGroup('category_page');
        $perPage = \App\Support\ProductListing::perPage();

        // Collect this category + all descendant category IDs
        $categoryIds = $category->selfAndDescendantIds();

        $query = Product::where('is_active', true)
            ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $categoryIds))
            ->with(['images', 'specials', 'brand', \App\Support\ProductAttributes::CARD_RELATION])
            ->when(\App\Support\Reviews::enabled(), fn ($rq) => $rq

                ->withAvg(['reviews as reviews_avg_rating' => fn($q) => $q->where('status', 'approved')], 'rating')

                ->withCount(['reviews as reviews_count' => fn($q) => $q->where('status', 'approved')]));

        // Filters
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->input('min_price'));
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->input('max_price'));
        }
        if ($request->filled('brand')) {
            $brands = (array) $request->input('brand');
            $query->whereIn('brand_id', $brands);
        }
        if ($request->filled('in_stock')) {
            $query->where(function ($q) {
                $q->where('quantity', '>', 0)->orWhere('track_stock', false);
            });
        }
        if ($request->filled('on_sale')) {
            $query->whereHas('specials', function ($q) {
                $q->where('display_mode', 'sale')->activeAt();
            });
        }
        if ($request->filled('tag')) {
            $tags = (array) $request->input('tag');
            $query->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $tags));
        }

        \App\Support\ProductAttributes::applyFilters($query, $request);

        // A sort in the URL wins; otherwise the store's default sort.
        $sort = \App\Support\ProductListing::sort($request->input('sort'));
        \App\Support\ProductListing::applySort($query, $sort);

        $products = $query->paginate($perPage)->withQueryString();

        // Filter options (across all descendant categories)
        $allProducts = Product::where('is_active', true)
            ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $categoryIds));
        $brandIds = (clone $allProducts)->pluck('brand_id')->unique()->filter();
        $availableBrands = Brand::where('is_active', true)->whereIn('id', $brandIds)->orderBy('name')->get();
        $productIds = (clone $allProducts)->pluck('id');
        $priceRange = Product::whereIn('id', $productIds)->selectRaw('MIN(price) as min_price, MAX(price) as max_price')->first();
        $availableTags = Tag::whereHas('products', function ($q) use ($categoryIds) {
            $q->where('is_active', true)->whereHas('categories', fn ($q2) => $q2->whereIn('categories.id', $categoryIds));
        })->orderBy('name')->get();

        $availableAttributes = \App\Support\ProductAttributes::filters(clone $allProducts);

        // Subcategories
        $subcategories = $category->children()->where('is_active', true)->orderBy('sort_order')->get();

        // Breadcrumb
        $breadcrumbs = $this->buildBreadcrumbs($category);

        $ogTitle = $category->og_title ?: null;
        $ogDescription = $category->og_description ?: null;

        $page = (int) request()->query('page', 1);
        view()->share('canonical', route('category.show', $category->slug) . ($page > 1 ? '?page=' . $page : ''));

        $data = compact(
            'category', 'products', 'availableBrands', 'priceRange',
            'availableTags', 'availableAttributes', 'subcategories', 'breadcrumbs', 'sort', 'settings',
            'ogTitle', 'ogDescription'
        );

        // The page's pieces may be widgets in a theme's category region; they
        // read the same bundle the page view gets.
        \App\Support\PageContext::set($data);

        return view('category.index', $data);
    }

    private function buildBreadcrumbs(Category $category): array
    {
        $crumbs = [];
        $current = $category;
        while ($current) {
            array_unshift($crumbs, $current);
            $current = $current->parent;
        }
        return $crumbs;
    }
}
