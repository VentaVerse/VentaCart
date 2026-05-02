<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Page;

class PageController extends Controller
{
    public function show(string $slug)
    {
        $page = Page::where('slug', $slug)->active()->firstOrFail();

        view()->share('canonical', route('page.show', $page->slug));

        return view('page.show', compact('page'));
    }
}
