<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;

/**
 * The home page holds no knowledge of what is on it.
 *
 * This controller used to switch on section type and load products for a
 * 'products' section and brands for a 'brands' section, which is why adding a
 * section type was a core edit. Each widget resolves its own data now, so the
 * page is regions and nothing else.
 */
class HomeController extends Controller
{
    public function index()
    {
        view()->share('canonical', route('home'));

        return view('home');
    }
}
