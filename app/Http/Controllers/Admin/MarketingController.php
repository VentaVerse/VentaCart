<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SeoSuggestion;
use App\Models\StockNotification;

class MarketingController extends Controller
{
    public function index()
    {
        $waitingCount = StockNotification::where('status', 'waiting')->count();
        $notifiedCount = StockNotification::where('status', 'notified')->count();

        $pendingSuggestions = SeoSuggestion::where('status', 'pending')->count();
        $totalProducts = Product::where('is_active', true)->count();
        $withMeta = Product::where('is_active', true)
            ->whereNotNull('meta_title')
            ->where('meta_title', '!=', '')
            ->count();
        $healthScore = $totalProducts > 0 ? round(($withMeta / $totalProducts) * 100) : 0;

        return view('admin.marketing.index', compact(
            'waitingCount', 'notifiedCount', 'pendingSuggestions', 'healthScore'
        ));
    }
}
