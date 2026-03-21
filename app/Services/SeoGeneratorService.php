<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SeoSuggestion;
use App\Models\SeoReport;
use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SeoGeneratorService
{
    private string $apiKey;
    private int $batchSize;
    private bool $autoAccept;

    public function __construct()
    {
        $encrypted = Setting::where('group', 'seo')->where('key', 'claude_api_key')->value('value') ?? '';
        try {
            $this->apiKey = $encrypted ? Crypt::decryptString($encrypted) : '';
        } catch (\Throwable) {
            $this->apiKey = '';
        }
        $this->batchSize = (int) (Setting::where('group', 'seo')->where('key', 'batch_size')->value('value') ?? 50);
        $this->autoAccept = (bool) (Setting::where('group', 'seo')->where('key', 'auto_accept')->value('value') ?? false);
    }

    public function generate(): array
    {
        if (!$this->apiKey) {
            return ['error' => 'Claude API key not configured'];
        }

        // Find products needing SEO (no meta title, or no recent suggestion in 30 days)
        $products = Product::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('meta_title')
                  ->orWhere('meta_title', '')
                  ->orWhereDoesntHave('seoSuggestions', function ($sq) {
                      $sq->where('generated_at', '>', now()->subDays(30));
                  });
            })
            ->with(['brand', 'categories'])
            ->limit($this->batchSize)
            ->get();

        if ($products->isEmpty()) {
            return ['message' => 'All products have up-to-date SEO', 'count' => 0];
        }

        $suggestions = [];
        $errors = 0;

        // Process in chunks of 10 for API efficiency
        foreach ($products->chunk(10) as $chunk) {
            $result = $this->callClaudeApi($chunk);
            if ($result === null) {
                $errors += $chunk->count();
                continue;
            }

            foreach ($result as $productId => $seo) {
                $product = $chunk->firstWhere('id', $productId);
                if (!$product) {
                    continue;
                }

                $status = $this->autoAccept ? 'accepted' : 'pending';

                $suggestion = SeoSuggestion::create([
                    'product_id' => $productId,
                    'current_meta_title' => $product->meta_title,
                    'suggested_meta_title' => $seo['meta_title'] ?? null,
                    'current_meta_description' => $product->meta_description,
                    'suggested_meta_description' => $seo['meta_description'] ?? null,
                    'current_meta_keywords' => $product->meta_keywords,
                    'suggested_meta_keywords' => $seo['meta_keywords'] ?? null,
                    'status' => $status,
                    'generated_at' => now(),
                ]);

                if ($this->autoAccept) {
                    $product->update([
                        'meta_title' => $seo['meta_title'] ?? $product->meta_title,
                        'meta_description' => $seo['meta_description'] ?? $product->meta_description,
                        'meta_keywords' => $seo['meta_keywords'] ?? $product->meta_keywords,
                    ]);
                }

                $suggestions[] = $suggestion;
            }
        }

        // Gather GSC search insights for the report
        $searchInsights = [];
        $gsc = new GoogleSearchConsoleService();
        if ($gsc->isConfigured()) {
            $searchInsights = [
                'top_queries' => array_slice($gsc->getSearchPerformance(28, 20), 0, 20),
                'top_pages' => array_slice($gsc->getPagePerformance(28, 20), 0, 20),
            ];
        }

        // Generate report
        $totalProducts = Product::where('is_active', true)->count();
        $withMeta = Product::where('is_active', true)
            ->whereNotNull('meta_title')
            ->where('meta_title', '!=', '')
            ->count();
        $healthScore = $totalProducts > 0 ? round(($withMeta / $totalProducts) * 100, 2) : 0;

        // Top opportunities: products with no meta
        $topOps = Product::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('meta_title')->orWhere('meta_title', '');
            })
            ->orderByDesc('views')
            ->limit(10)
            ->get(['id', 'name', 'views'])
            ->map(function ($p) {
                return ['id' => $p->id, 'name' => $p->name, 'views' => $p->views];
            })
            ->toArray();

        $report = SeoReport::create([
            'store_id' => (int) config('app.store_id', 1),
            'total_products_analyzed' => $products->count(),
            'suggestions_generated' => count($suggestions),
            'auto_accepted' => $this->autoAccept ? count($suggestions) : 0,
            'pending_review' => $this->autoAccept ? 0 : count($suggestions),
            'health_score' => $healthScore,
            'top_opportunities' => $topOps,
            'search_insights' => $searchInsights ?: null,
            'generated_at' => now(),
        ]);

        return [
            'suggestions' => count($suggestions),
            'errors' => $errors,
            'health_score' => $healthScore,
            'report_id' => $report->id,
        ];
    }

    private function callClaudeApi($products): ?array
    {
        $productData = $products->map(function ($p) {
            return [
                'id' => $p->id,
                'name' => $p->name,
                'brand' => $p->brand?->name ?? '',
                'category' => $p->categories->pluck('name')->join(', '),
                'price' => $p->price,
                'description_excerpt' => substr(strip_tags($p->description ?? ''), 0, 200),
            ];
        })->values()->toArray();

        $prompt = "You are an SEO specialist for an e-commerce store in the Philippines selling music gear (guitar pedals, cables, accessories). Generate optimized meta tags for each product.\n\nFor each product, provide:\n- meta_title: max 60 chars, include product name + key selling point + store context\n- meta_description: max 155 chars, compelling, include call-to-action\n- meta_keywords: 5-8 relevant keywords, comma-separated\n\nProducts:\n" . json_encode($productData, JSON_PRETTY_PRINT);

        // Append GSC data if available
        $gsc = new GoogleSearchConsoleService();
        if ($gsc->isConfigured()) {
            $topQueries = array_slice($gsc->getSearchPerformance(28, 10), 0, 10);
            if (!empty($topQueries)) {
                $prompt .= "\n\nAdditionally, here are the top search queries driving traffic to this store (from Google Search Console):\n";
                foreach ($topQueries as $q) {
                    $prompt .= "- \"{$q['query']}\" ({$q['clicks']} clicks, {$q['impressions']} impressions, {$q['ctr']}% CTR, position {$q['position']})\n";
                }
                $prompt .= "\nConsider these real search terms when optimizing meta tags — target queries where the store already has impressions but low CTR.";
            }
        }

        $prompt .= "\n\nRespond ONLY with valid JSON in this format:\n{\"<product_id>\": {\"meta_title\": \"...\", \"meta_description\": \"...\", \"meta_keywords\": \"...\"}, ...}";

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-sonnet-4-5-20250514',
                'max_tokens' => 4096,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            if (!$response->successful()) {
                Log::error('SeoGenerator: Claude API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $content = $response->json('content.0.text', '');

            // Extract JSON from response (Claude sometimes wraps in markdown)
            if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
                $parsed = json_decode($matches[0], true);
                if ($parsed) {
                    // Convert string keys to integers
                    $result = [];
                    foreach ($parsed as $key => $val) {
                        $result[(int) $key] = $val;
                    }
                    return $result;
                }
            }

            Log::error('SeoGenerator: could not parse Claude response', [
                'content' => substr($content, 0, 500),
            ]);
            return null;
        } catch (\Throwable $e) {
            Log::error('SeoGenerator: request failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
