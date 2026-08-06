<?php

namespace App\Http\Controllers\Admin\Extensions;

use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\BlogPost;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\SeoSuggestion;
use App\Models\Setting;
use App\Services\GoogleSearchConsoleService;
use App\Services\SeoGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class SeoExtensionController extends Controller
{
    /**
     * SEO Extension dashboard with health scores and recent activity.
     */
    public function dashboard()
    {
        $healthScores = [];

        foreach ($this->entityConfigs() as $key => $cfg) {
            $model = $cfg['model'];
            $activeCol = $cfg['activeScope'][0];
            $total = $model::where($activeCol, true)->count();
            $withMeta = $model::where($activeCol, true)
                ->whereNotNull('meta_title')
                ->where('meta_title', '!=', '')
                ->count();
            $healthScores[$key] = [
                'total' => $total,
                'withMeta' => $withMeta,
                'score' => $total > 0 ? round(($withMeta / $total) * 100, 1) : 0,
            ];
        }

        $pendingCount = SeoSuggestion::pending()->count();
        $recentSuggestions = SeoSuggestion::with('entity')
            ->orderByDesc('generated_at')
            ->limit(10)
            ->get();

        $gsc = new GoogleSearchConsoleService();
        $topQueries = $gsc->isConfigured() ? $gsc->getSearchPerformance(28, 5) : [];

        // Cron visibility for the dashboard's Automation status card — the
        // schedule itself is edited in Settings > Automations, this is read-only.
        $seoAutomation = Automation::where('key', 'seo_generate')->first();

        return view('admin.extensions.seo.dashboard', compact(
            'healthScores', 'pendingCount', 'recentSuggestions', 'topQueries', 'seoAutomation'
        ));
    }

    /**
     * List entities of a given type with SEO status filtering.
     */
    public function entities(Request $request, string $type)
    {
        $config = $this->resolveType($type);

        $query = $config['model']::query()
            ->where($config['activeScope'][0], $config['activeScope'][1]);

        // Search filter
        if ($search = $request->get('search')) {
            $nameCol = $config['entityType'] === 'blog_post' ? 'title' : 'name';
            $query->where($nameCol, 'like', "%{$search}%");
        }

        // SEO status filter
        if ($status = $request->get('seo_status')) {
            match ($status) {
                'complete' => $query->whereNotNull('meta_title')
                    ->where('meta_title', '!=', '')
                    ->whereNotNull('meta_description')
                    ->where('meta_description', '!=', ''),
                'partial' => $query->where(function ($q) {
                    $q->where(function ($q2) {
                        $q2->whereNotNull('meta_title')->where('meta_title', '!=', '');
                    })->orWhere(function ($q2) {
                        $q2->whereNotNull('meta_description')->where('meta_description', '!=', '');
                    });
                })->where(function ($q) {
                    $q->whereNull('meta_title')->orWhere('meta_title', '')
                      ->orWhereNull('meta_description')->orWhere('meta_description', '');
                }),
                'missing' => $query->where(function ($q) {
                    $q->whereNull('meta_title')->orWhere('meta_title', '');
                })->where(function ($q) {
                    $q->whereNull('meta_description')->orWhere('meta_description', '');
                }),
                'pending' => $query->whereHas('seoSuggestions', function ($q) use ($config) {
                    $q->where('entity_type', $config['entityType'])->where('status', 'pending');
                }),
                default => null,
            };
        }

        $entities = $query->orderBy('id', 'desc')->paginate(20)->appends($request->query());

        // Load pending suggestions for displayed entities
        $entityIds = $entities->pluck('id')->toArray();
        $modelClass = $config['model'];
        $pendingSuggestions = SeoSuggestion::where('entity_type', $config['entityType'])
            ->whereIn('entity_id', $entityIds)
            ->where('status', 'pending')
            ->get()
            ->keyBy('entity_id');

        $editRoute = $config['editRoute'];
        $entityType = $config['entityType'];

        $homepageMeta = [];
        if ($type === 'pages') {
            $homepageMeta = Setting::where('group', 'general')
                ->whereIn('key', ['default_meta_title', 'default_meta_description'])
                ->pluck('value', 'key')
                ->toArray();
        }

        return view('admin.extensions.seo.entities', compact(
            'entities', 'type', 'pendingSuggestions', 'editRoute', 'entityType', 'search', 'status', 'homepageMeta'
        ));
    }

    /**
     * Generate SEO suggestions for all entities of a type.
     */
    public function generate(Request $request)
    {
        $request->validate([
            'entity_type' => 'required|in:product,category,brand,blog_post,page,all',
        ]);

        $service = new SeoGeneratorService();
        $types = $request->entity_type === 'all'
            ? ['product', 'category', 'brand', 'blog_post', 'page']
            : [$request->entity_type];

        $totalSuggestions = 0;
        $lastError = null;

        foreach ($types as $type) {
            $result = $service->generateForType($type);
            if (isset($result['error'])) {
                $lastError = $result['error'];
            }
            $totalSuggestions += $result['suggestions'] ?? 0;
        }

        if ($lastError && $totalSuggestions === 0) {
            return back()->with('error', $lastError);
        }

        if ($totalSuggestions === 0) {
            return back()->with('success', 'All entities have up-to-date SEO.');
        }

        return back()->with('success', "Generated {$totalSuggestions} suggestion(s).");
    }

    /**
     * Optimize a single entity via AJAX (returns suggestion data for preview).
     */
    public function optimize(Request $request)
    {
        $request->validate([
            'entity_type' => 'required|in:product,category,brand,blog_post,page,homepage',
            'entity_id' => 'required|integer',
        ]);

        // Homepage is a special case — uses settings table
        if ($request->entity_type === 'homepage') {
            return $this->optimizeHomepage();
        }

        $service = new SeoGeneratorService();
        $suggestion = $service->optimizeSingle($request->entity_type, $request->entity_id);

        if (!$suggestion) {
            return response()->json(['error' => 'Failed to generate suggestion. Check API key configuration.'], 422);
        }

        return response()->json([
            'ok' => true,
            'suggestion' => [
                'id' => $suggestion->id,
                'suggested_meta_title' => $suggestion->suggested_meta_title,
                'suggested_meta_description' => $suggestion->suggested_meta_description,
                'suggested_og_title' => $suggestion->suggested_og_title,
                'suggested_og_description' => $suggestion->suggested_og_description,
                'current_meta_title' => $suggestion->current_meta_title,
                'current_meta_description' => $suggestion->current_meta_description,
                'current_og_title' => $suggestion->current_og_title,
                'current_og_description' => $suggestion->current_og_description,
                'suggested_slug' => $suggestion->suggested_slug,
                'current_slug' => $suggestion->current_slug,
            ],
        ]);
    }

    /**
     * Optimize homepage meta via Claude (special case — no entity model).
     */
    private function optimizeHomepage()
    {
        $service = new SeoGeneratorService();
        if (!$service->isConfigured()) {
            return response()->json(['error' => 'Claude API key not configured.'], 422);
        }

        $currentTitle = Setting::where('group', 'general')->where('key', 'default_meta_title')->value('value') ?? '';
        $currentDesc = Setting::where('group', 'general')->where('key', 'default_meta_description')->value('value') ?? '';
        $siteName = Setting::where('group', 'identity')->where('key', 'site_name')->value('value') ?? 'Store';

        // Build strategy context
        $seoSettings = Setting::where('group', 'seo')->pluck('value', 'key');
        $storeContext = $seoSettings['store_context'] ?? '';
        $targetMarket = $seoSettings['target_market'] ?? '';
        $brandVoice = $seoSettings['brand_voice'] ?? '';
        $priorityKeywords = $seoSettings['priority_keywords'] ?? '';

        $prompt = "You are an SEO specialist. Generate an optimized homepage meta title and meta description for this store.\n\n";
        $prompt .= "Store name: {$siteName}\n";
        if ($storeContext) $prompt .= "Store description: {$storeContext}\n";
        if ($targetMarket) $prompt .= "Target market: {$targetMarket}\n";
        if ($brandVoice) $prompt .= "Brand voice: {$brandVoice}\n";
        if ($priorityKeywords) $prompt .= "Priority keywords: {$priorityKeywords}\n";
        if ($currentTitle) $prompt .= "Current meta title: {$currentTitle}\n";
        if ($currentDesc) $prompt .= "Current meta description: {$currentDesc}\n";
        $prompt .= "\nProvide:\n- meta_title: max 60 chars, compelling, include store name\n- meta_description: max 155 chars, include call-to-action\n";
        $prompt .= "\nRespond ONLY with valid JSON: {\"meta_title\": \"...\", \"meta_description\": \"...\"}";

        try {
            $apiKey = Crypt::decryptString($seoSettings['claude_api_key'] ?? '');
            $model = $seoSettings['claude_model'] ?? 'claude-sonnet-4-6';

            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => 256,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            if (!$response->successful()) {
                return response()->json(['error' => 'Claude API error: ' . $response->status()], 422);
            }

            $content = $response->json('content.0.text', '');
            if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
                $seo = json_decode($matches[0], true);
                if ($seo) {
                    return response()->json([
                        'ok' => true,
                        'suggestion' => [
                            'id' => null,
                            'current_meta_title' => $currentTitle,
                            'suggested_meta_title' => $seo['meta_title'] ?? null,
                            'current_meta_description' => $currentDesc,
                            'suggested_meta_description' => $seo['meta_description'] ?? null,
                            'current_og_title' => null,
                            'suggested_og_title' => null,
                            'current_og_description' => null,
                            'suggested_og_description' => null,
                            'suggested_slug' => null,
                            'current_slug' => null,
                            'is_homepage' => true,
                        ],
                    ]);
                }
            }

            return response()->json(['error' => 'Could not parse Claude response.'], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed: ' . $e->getMessage()], 422);
        }
    }

    /**
     * Accept a suggestion and apply its values to the entity.
     */
    public function accept(SeoSuggestion $suggestion)
    {
        $entity = $suggestion->entity;

        if ($entity) {
            $updateData = [
                'meta_title' => $suggestion->suggested_meta_title ?? $entity->meta_title,
                'meta_description' => $suggestion->suggested_meta_description ?? $entity->meta_description,
                'og_title' => $suggestion->suggested_og_title ?? $entity->og_title,
                'og_description' => $suggestion->suggested_og_description ?? $entity->og_description,
            ];

            // Only apply slug if entity doesn't have one and suggestion provides one
            if (empty($entity->slug) && !empty($suggestion->suggested_slug)) {
                $updateData['slug'] = $suggestion->suggested_slug;
            }

            $entity->update($updateData);
        }

        $suggestion->update(['status' => 'accepted']);

        if (request()->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Suggestion accepted and applied.');
    }

    /**
     * Reject a suggestion.
     */
    public function reject(SeoSuggestion $suggestion)
    {
        $suggestion->update(['status' => 'rejected']);

        if (request()->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Suggestion rejected.');
    }

    /**
     * Bulk accept an array of suggestion IDs.
     */
    public function bulkAccept(Request $request)
    {
        $entityType = $request->input('entity_type');
        $ids = $request->input('ids', []);

        // If entity_type is provided, accept ALL pending for that type (or all types)
        if ($entityType) {
            $query = SeoSuggestion::where('status', 'pending');
            if ($entityType !== 'all') {
                $query->where('entity_type', $entityType);
            }
            $suggestions = $query->get();
        } elseif (!empty($ids)) {
            $suggestions = SeoSuggestion::whereIn('id', $ids)->where('status', 'pending')->get();
        } else {
            return back()->with('error', 'No suggestions selected.');
        }

        $accepted = 0;
        foreach ($suggestions as $suggestion) {
            $entity = $suggestion->entity;

            if ($entity) {
                $updateData = [
                    'meta_title' => $suggestion->suggested_meta_title ?? $entity->meta_title,
                    'meta_description' => $suggestion->suggested_meta_description ?? $entity->meta_description,
                    'og_title' => $suggestion->suggested_og_title ?? $entity->og_title,
                    'og_description' => $suggestion->suggested_og_description ?? $entity->og_description,
                ];
                $entity->update($updateData);

                if ($suggestion->product_id && $suggestion->entity_type === Product::class) {
                    Product::where('id', $suggestion->product_id)->update($updateData);
                }
            }

            $suggestion->update(['status' => 'accepted']);
            $accepted++;
        }

        return back()->with('success', "{$accepted} suggestion(s) accepted and applied.");
    }

    /**
     * Search Console data view.
     */
    public function searchConsole(Request $request)
    {
        $gsc = new GoogleSearchConsoleService();
        $gscConfigured = $gsc->isConfigured();
        $forceRefresh = $request->has('refresh');

        $cacheKey = 'seo_gsc_data';
        $cacheTtl = 3600; // 1 hour

        if ($gscConfigured && ($forceRefresh || !\Illuminate\Support\Facades\Cache::has($cacheKey))) {
            $queries = $gsc->getSearchPerformance(28, 50);
            $pages = $gsc->getPagePerformance(28, 50);
            \Illuminate\Support\Facades\Cache::put($cacheKey, [
                'queries' => $queries,
                'pages' => $pages,
                'fetched_at' => now()->toIso8601String(),
            ], $cacheTtl);
        }

        $cached = \Illuminate\Support\Facades\Cache::get($cacheKey, []);
        $queries = $cached['queries'] ?? [];
        $pages = $cached['pages'] ?? [];
        $fetchedAt = isset($cached['fetched_at']) ? \Carbon\Carbon::parse($cached['fetched_at']) : null;

        return view('admin.extensions.seo.search-console', compact(
            'gscConfigured', 'queries', 'pages', 'fetchedAt'
        ));
    }

    /**
     * Slugs management tab.
     */
    public function slugs(Request $request)
    {
        $filter = $request->get('type', 'products');
        $search = $request->get('search', '');

        $config = $this->entityConfigs()[$filter] ?? $this->entityConfigs()['products'];
        $modelClass = $config['model'];
        $nameCol = $config['entityType'] === 'blog_post' ? 'title' : ($config['entityType'] === 'page' ? 'title' : 'name');

        $query = $modelClass::where($config['activeScope'][0], $config['activeScope'][1]);

        if ($search) {
            $query->where($nameCol, 'like', "%{$search}%");
        }

        $entities = $query->select('id', $nameCol . ' as display_name', 'slug')
            ->orderBy($nameCol)
            ->paginate(30)
            ->appends($request->query());

        $entityType = $config['entityType'];

        return view('admin.extensions.seo.slugs', compact('entities', 'filter', 'search', 'entityType'));
    }

    /**
     * Update a single entity's slug.
     */
    public function updateSlug(Request $request)
    {
        $request->validate([
            'entity_type' => 'required|in:product,category,brand,blog_post,page',
            'entity_id' => 'required|integer',
            'slug' => 'required|string|max:255|regex:/^[a-z0-9][a-z0-9-]*$/',
        ]);

        $modelClass = $this->getModelClassFromType($request->entity_type);
        $entity = $modelClass::findOrFail($request->entity_id);

        // Check uniqueness within the same table
        $existing = $modelClass::where('slug', $request->slug)->where('id', '!=', $entity->id)->exists();
        if ($existing) {
            return response()->json(['error' => 'Slug already in use.'], 422);
        }

        $entity->update(['slug' => $request->slug]);

        return response()->json(['ok' => true]);
    }

    private function getModelClassFromType(string $type): string
    {
        return match ($type) {
            'product' => Product::class,
            'category' => Category::class,
            'brand' => Brand::class,
            'blog_post' => BlogPost::class,
            'page' => Page::class,
            default => abort(404),
        };
    }

    /**
     * Review tab — all pending suggestions grouped by entity type.
     */
    public function review(Request $request)
    {
        $filter = $request->get('type', 'all');

        $query = SeoSuggestion::where('status', 'pending')
            ->with('entity')
            ->orderByDesc('generated_at');

        if ($filter !== 'all') {
            $query->where('entity_type', $filter);
        }

        $suggestions = $query->paginate(30)->appends($request->query());

        $counts = SeoSuggestion::where('status', 'pending')
            ->selectRaw('entity_type, COUNT(*) as count')
            ->groupBy('entity_type')
            ->pluck('count', 'entity_type')
            ->toArray();

        $totalPending = array_sum($counts);

        return view('admin.extensions.seo.review', compact('suggestions', 'counts', 'totalPending', 'filter'));
    }

    /**
     * SEO Strategy view.
     */
    public function strategy()
    {
        $seoSettings = Setting::where('group', 'seo')->pluck('value', 'key');

        return view('admin.extensions.seo.strategy', compact('seoSettings'));
    }

    /**
     * Save SEO Strategy settings.
     */
    public function saveStrategy(Request $request)
    {
        $request->validate([
            'store_context' => 'nullable|string|max:2000',
            'target_market' => 'nullable|string|max:500',
            'brand_voice' => 'nullable|string|max:500',
            'priority_keywords' => 'nullable|string|max:1000',
            'avoid_keywords' => 'nullable|string|max:500',
        ]);

        $fields = ['store_context', 'target_market', 'brand_voice', 'priority_keywords', 'avoid_keywords'];

        foreach ($fields as $key) {
            Setting::updateOrCreate(
                ['group' => 'seo', 'key' => $key],
                ['value' => $request->input($key, '')]
            );
        }

        return back()->with('success', 'SEO strategy saved. Claude will use this context for all future optimizations.');
    }

    /**
     * SEO extension settings view.
     */
    public function settings()
    {
        $seoSettings = Setting::where('group', 'seo')->pluck('value', 'key');

        $gscCredentialsExist = \Illuminate\Support\Facades\Storage::disk('local')
            ->exists('credentials/gsc-credentials.json');

        $maskedApiKey = '';
        if (!empty($seoSettings['claude_api_key'])) {
            try {
                $decrypted = Crypt::decryptString($seoSettings['claude_api_key']);
                $maskedApiKey = substr($decrypted, 0, 8) . '...' . substr($decrypted, -4);
            } catch (\Throwable) {
                $maskedApiKey = '(invalid/corrupted)';
            }
        }

        return view('admin.extensions.seo.settings', compact(
            'seoSettings', 'gscCredentialsExist', 'maskedApiKey'
        ));
    }

    /**
     * Save SEO extension settings.
     */
    public function saveSettings(Request $request)
    {
        // Homepage meta apply (from AI optimize)
        if ($request->input('_homepage_apply')) {
            if ($request->filled('default_meta_title')) {
                Setting::updateOrCreate(['group' => 'general', 'key' => 'default_meta_title'], ['value' => $request->input('default_meta_title')]);
            }
            if ($request->filled('default_meta_description')) {
                Setting::updateOrCreate(['group' => 'general', 'key' => 'default_meta_description'], ['value' => $request->input('default_meta_description')]);
            }
            if ($request->expectsJson()) {
                return response()->json(['ok' => true]);
            }
            return back()->with('success', 'Homepage SEO updated.');
        }

        $settings = [
            'claude_model' => $request->input('claude_model', 'claude-sonnet-4-6'),
            'gsc_site_url' => $request->input('gsc_site_url', ''),
            'auto_accept' => $request->boolean('auto_accept') ? '1' : '0',
            // Optimization rules
            'rule_skip_pending' => $request->boolean('rule_skip_pending') ? '1' : '0',
            'rule_skip_recently_accepted' => $request->boolean('rule_skip_recently_accepted') ? '1' : '0',
            'rule_skip_complete' => $request->boolean('rule_skip_complete') ? '1' : '0',
            'rule_process_empty' => $request->boolean('rule_process_empty') ? '1' : '0',
            'rule_process_rejected' => $request->boolean('rule_process_rejected') ? '1' : '0',
            'rule_refresh_days' => max(0, (int) $request->input('rule_refresh_days', 90)),
            // Scope
            'rule_scope_products' => $request->boolean('rule_scope_products') ? '1' : '0',
            'rule_scope_categories' => $request->boolean('rule_scope_categories') ? '1' : '0',
            'rule_scope_brands' => $request->boolean('rule_scope_brands') ? '1' : '0',
            'rule_scope_blog_posts' => $request->boolean('rule_scope_blog_posts') ? '1' : '0',
            'rule_scope_pages' => $request->boolean('rule_scope_pages') ? '1' : '0',
            // Batch
            'rule_batch_limit' => max(0, (int) $request->input('rule_batch_limit', 0)),
        ];

        // Only update API key if a new one was provided
        if ($request->filled('claude_api_key')) {
            $settings['claude_api_key'] = Crypt::encryptString($request->input('claude_api_key'));
        }

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(
                ['group' => 'seo', 'key' => $key],
                ['value' => (string) $value]
            );
        }

        return back()->with('success', 'SEO settings saved successfully.');
    }

    /**
     * Upload Google Search Console credentials JSON file.
     */
    public function uploadGsc(Request $request)
    {
        $request->validate([
            'gsc_credentials' => 'required|file|mimes:json|max:50',
        ]);

        $request->file('gsc_credentials')->storeAs(
            'credentials',
            'gsc-credentials.json',
            'local'
        );

        return back()->with('success', 'GSC credentials uploaded successfully.');
    }

    /**
     * Test Claude API connection.
     */
    public function testClaude()
    {
        $service = new SeoGeneratorService();

        if (!$service->isConfigured()) {
            return back()->with('error', 'Claude API key not configured.');
        }

        try {
            $encrypted = Setting::where('group', 'seo')->where('key', 'claude_api_key')->value('value');
            $apiKey = Crypt::decryptString($encrypted);
            $model = Setting::where('group', 'seo')->where('key', 'claude_model')->value('value') ?: 'claude-sonnet-4-6';

            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(15)->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => 50,
                'messages' => [['role' => 'user', 'content' => 'Reply with just "ok"']],
            ]);

            if ($response->successful()) {
                return back()->with('success', 'Claude API connection successful! Model: ' . $model);
            }

            return back()->with('error', 'Claude API error: ' . $response->status() . ' — ' . ($response->json('error.message') ?? $response->body()));
        } catch (\Throwable $e) {
            return back()->with('error', 'Claude API test failed: ' . $e->getMessage());
        }
    }

    /**
     * Test Google Search Console connection.
     */
    public function testGsc()
    {
        $gsc = new GoogleSearchConsoleService();

        if (!$gsc->isConfigured()) {
            return back()->with('error', 'GSC not configured. Upload credentials and set site URL first.');
        }

        try {
            $queries = $gsc->getSearchPerformance(7, 5);

            if (empty($queries)) {
                return back()->with('success', 'GSC connection successful! No query data found for the last 7 days (this may be normal for new sites).');
            }

            $topQuery = $queries[0]['query'] ?? 'N/A';
            return back()->with('success', 'GSC connection successful! Found ' . count($queries) . ' queries. Top query: "' . $topQuery . '"');
        } catch (\Throwable $e) {
            return back()->with('error', 'GSC test failed: ' . $e->getMessage());
        }
    }

    /**
     * Entity type configurations.
     */
    private function entityConfigs(): array
    {
        return [
            'products' => ['model' => Product::class, 'activeScope' => ['is_active', true], 'entityType' => 'product', 'editRoute' => 'admin.products.edit'],
            'categories' => ['model' => Category::class, 'activeScope' => ['is_active', true], 'entityType' => 'category', 'editRoute' => 'admin.categories.edit'],
            'brands' => ['model' => Brand::class, 'activeScope' => ['is_active', true], 'entityType' => 'brand', 'editRoute' => 'admin.brands.edit'],
            'blog' => ['model' => BlogPost::class, 'activeScope' => ['is_published', true], 'entityType' => 'blog_post', 'editRoute' => 'admin.blog.edit'],
            'pages' => ['model' => Page::class, 'activeScope' => ['is_active', true], 'entityType' => 'page', 'editRoute' => 'admin.pages.edit'],
        ];
    }

    /**
     * Resolve URL type parameter to config.
     */
    private function resolveType(string $type): array
    {
        $configs = $this->entityConfigs();

        if (!isset($configs[$type])) {
            abort(404);
        }

        return $configs[$type];
    }
}
