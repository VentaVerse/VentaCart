<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;

class SitemapController extends Controller
{
    public function index()
    {
        $categories = Category::where('is_active', true)
            ->whereNull('parent_id')
            ->with(['children' => fn($q) => $q->where('is_active', true)->orderBy('name')])
            ->orderBy('name')
            ->get();

        $brands = Brand::where('is_active', true)
            ->orderBy('name')
            ->get();

        $pages = Page::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        $blogPosts = class_exists(\Extensions\Blog\Models\BlogPost::class)
            ? \Extensions\Blog\Models\BlogPost::where('is_published', true)
                ->latest('published_at')
                ->limit(50)
                ->get()
            : collect();

        $productCount = Product::where('is_active', true)->count();

        return view('sitemap', compact('categories', 'brands', 'pages', 'blogPosts', 'productCount'));
    }
}
