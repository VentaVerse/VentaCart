<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SeoReport;
use App\Models\SeoSuggestion;
use App\Services\GoogleSearchConsoleService;
use App\Services\SeoGeneratorService;
use Illuminate\Http\Request;

class SeoController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'suggestions');
        $status = $request->get('status', 'pending');

        $suggestionsQuery = SeoSuggestion::with('product')
            ->where('status', $status)
            ->orderByDesc('generated_at');

        $suggestions = $suggestionsQuery->paginate(20)->appends(['tab' => 'suggestions', 'status' => $status]);

        $reports = SeoReport::orderByDesc('generated_at')->paginate(15)->appends(['tab' => 'reports']);

        // Stats
        $pendingCount = SeoSuggestion::pending()->count();
        $acceptedCount = SeoSuggestion::accepted()->count();
        $rejectedCount = SeoSuggestion::rejected()->count();

        // Health score
        $totalProducts = Product::where('is_active', true)->count();
        $withMeta = Product::where('is_active', true)
            ->whereNotNull('meta_title')
            ->where('meta_title', '!=', '')
            ->count();
        $healthScore = $totalProducts > 0 ? round(($withMeta / $totalProducts) * 100, 1) : 0;

        // Google Search Console data
        $gsc = new GoogleSearchConsoleService();
        $gscConfigured = $gsc->isConfigured();
        $gscData = $gscConfigured ? [
            'queries' => $gsc->getSearchPerformance(28, 50),
            'pages' => $gsc->getPagePerformance(28, 50),
        ] : null;

        return view('admin.seo.index', compact(
            'tab', 'status', 'suggestions', 'reports',
            'pendingCount', 'acceptedCount', 'rejectedCount',
            'healthScore', 'totalProducts', 'withMeta',
            'gscConfigured', 'gscData'
        ));
    }

    public function accept(SeoSuggestion $suggestion)
    {
        $product = $suggestion->product;

        if ($product) {
            $product->update([
                'meta_title' => $suggestion->suggested_meta_title ?? $product->meta_title,
                'meta_description' => $suggestion->suggested_meta_description ?? $product->meta_description,
                'meta_keywords' => $suggestion->suggested_meta_keywords ?? $product->meta_keywords,
            ]);
        }

        $suggestion->update(['status' => 'accepted']);

        return back()->with('success', 'Suggestion accepted and applied to product.');
    }

    public function reject(SeoSuggestion $suggestion)
    {
        $suggestion->update(['status' => 'rejected']);

        return back()->with('success', 'Suggestion rejected.');
    }

    public function bulkAccept(Request $request)
    {
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return back()->with('error', 'No suggestions selected.');
        }

        $suggestions = SeoSuggestion::whereIn('id', $ids)->where('status', 'pending')->with('product')->get();

        $accepted = 0;
        foreach ($suggestions as $suggestion) {
            $product = $suggestion->product;
            if ($product) {
                $product->update([
                    'meta_title' => $suggestion->suggested_meta_title ?? $product->meta_title,
                    'meta_description' => $suggestion->suggested_meta_description ?? $product->meta_description,
                    'meta_keywords' => $suggestion->suggested_meta_keywords ?? $product->meta_keywords,
                ]);
            }
            $suggestion->update(['status' => 'accepted']);
            $accepted++;
        }

        return back()->with('success', "{$accepted} suggestion(s) accepted and applied.");
    }

    public function generateNow()
    {
        $service = new SeoGeneratorService();
        $result = $service->generate();

        if (isset($result['error'])) {
            return back()->with('error', $result['error']);
        }

        if (isset($result['message'])) {
            return back()->with('success', $result['message']);
        }

        return back()->with('success', "Generated {$result['suggestions']} suggestion(s). Health score: {$result['health_score']}%");
    }
}
