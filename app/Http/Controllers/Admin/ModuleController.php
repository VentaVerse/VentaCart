<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Product;
use App\Models\SeoSuggestion;
use App\Models\StockNotification;

class ModuleController extends Controller
{
    public function index()
    {
        $modules = collect([
            [
                'name' => 'Stock Alerts',
                'description' => 'Back-in-stock notifications & waitlist management',
                'route' => route('admin.stock-notifications.index'),
                'status' => 'active',
                'stats' => StockNotification::where('status', 'waiting')->count() . ' waiting',
            ],
            [
                'name' => 'SEO',
                'description' => 'AI-powered optimization, sitemap & structured data',
                'route' => route('admin.extensions.seo.dashboard'),
                'status' => 'active',
                'stats' => SeoSuggestion::where('status', 'pending')->count() . ' pending suggestions',
            ],
            [
                'name' => 'Abandoned Carts',
                'description' => 'Track and recover abandoned shopping carts',
                'route' => route('admin.abandoned-carts.index'),
                'status' => 'active',
                'stats' => Cart::where('updated_at', '<', now()->subHours(1))
                    ->whereHas('items')
                    ->count() . ' abandoned',
            ],
        ]);

        return view('admin.modules.index', compact('modules'));
    }
}
