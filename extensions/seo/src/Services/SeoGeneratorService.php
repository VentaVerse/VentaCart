<?php

namespace Extensions\Seo\Services;

use App\Models\Brand;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Extensions\Seo\Models\SeoReport;
use Extensions\Seo\Models\SeoSuggestion;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SeoGeneratorService
{
    private string $apiKey;
    private int $batchSize;
    private bool $autoAccept;
    private string $model;

    private string $storeContext;

    public function __construct()
    {
        $seoSettings = Setting::where('group', 'seo')->pluck('value', 'key');

        $encrypted = $seoSettings['claude_api_key'] ?? '';
        try {
            $this->apiKey = $encrypted ? Crypt::decryptString($encrypted) : '';
        } catch (\Throwable) {
            $this->apiKey = '';
        }
        $this->batchSize = (int) ($seoSettings['batch_size'] ?? 50);
        $this->autoAccept = (bool) ($seoSettings['auto_accept'] ?? false);
        $this->model = $seoSettings['claude_model'] ?? 'claude-sonnet-4-6';
        $this->storeContext = $this->buildStoreContext($seoSettings);
    }

    private function buildStoreContext(mixed $settings): string
    {
        $parts = [];

        $storeDesc = $settings['store_context'] ?? '';
        if ($storeDesc) {
            $parts[] = "Store: {$storeDesc}";
        } else {
            $parts[] = 'Store: an online store selling music gear (guitar pedals, cables, accessories, amplifiers) in the Philippines';
        }

        $target = $settings['target_market'] ?? '';
        if ($target) {
            $parts[] = "Target market: {$target}";
        }

        $voice = $settings['brand_voice'] ?? '';
        if ($voice) {
            $parts[] = "Brand voice: {$voice}";
        }

        $priority = $settings['priority_keywords'] ?? '';
        if ($priority) {
            $parts[] = "Priority keywords to incorporate when relevant: {$priority}";
        }

        $avoid = $settings['avoid_keywords'] ?? '';
        if ($avoid) {
            $parts[] = "NEVER use these words/phrases: {$avoid}";
        }

        return implode("\n", $parts);
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Backward-compatible: generate SEO for products only.
     * Called by the seo:generate CLI command.
     */
    public function generate(): array
    {
        return $this->generateForType('product');
    }

    /**
     * Generate SEO suggestions for all entities of the given type.
     */
    public function generateForType(string $entityType): array
    {
        if (!$this->apiKey) {
            return ['error' => 'Claude API key not configured'];
        }

        $modelClass = $this->getModelClass($entityType);
        $entities = $this->getEntitiesNeedingSeo($entityType, $modelClass);

        if ($entities->isEmpty()) {
            return ['message' => "All {$entityType} entities have up-to-date SEO", 'suggestions' => 0, 'errors' => 0];
        }

        $suggestions = [];
        $errors = 0;

        // Process in chunks of 10 for API efficiency
        foreach ($entities->chunk(10) as $chunk) {
            $prompt = $this->buildBatchPrompt($entityType, $chunk);
            $result = $this->callClaudeApi($prompt);

            if ($result === null) {
                $errors += $chunk->count();
                continue;
            }

            foreach ($result as $entityId => $seo) {
                $entity = $chunk->firstWhere('id', $entityId);
                if (!$entity) {
                    continue;
                }

                $status = $this->autoAccept ? 'accepted' : 'pending';

                $suggestionData = [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'current_meta_title' => $entity->meta_title,
                    'suggested_meta_title' => $seo['meta_title'] ?? null,
                    'current_meta_description' => $entity->meta_description,
                    'suggested_meta_description' => $seo['meta_description'] ?? null,
                    'current_og_title' => $entity->og_title ?? null,
                    'suggested_og_title' => $seo['og_title'] ?? null,
                    'current_og_description' => $entity->og_description ?? null,
                    'suggested_og_description' => $seo['og_description'] ?? null,
                    'current_slug' => $entity->slug ?? null,
                    'suggested_slug' => empty($entity->slug) ? ($seo['slug'] ?? null) : null,
                    'status' => $status,
                    'generated_at' => now(),
                ];

                // Backward compat: also set product_id for products
                if ($entityType === 'product') {
                    $suggestionData['product_id'] = $entityId;
                }

                $suggestion = SeoSuggestion::create($suggestionData);

                if ($this->autoAccept) {
                    $updateData = [
                        'meta_title' => $seo['meta_title'] ?? $entity->meta_title,
                        'meta_description' => $seo['meta_description'] ?? $entity->meta_description,
                        'og_title' => $seo['og_title'] ?? $entity->og_title,
                        'og_description' => $seo['og_description'] ?? $entity->og_description,
                    ];
                    $entity->update($updateData);
                }

                $suggestions[] = $suggestion;
            }
        }

        // Generate report for product runs (backward compat)
        if ($entityType === 'product') {
            $this->generateProductReport($entities, $suggestions);
        }

        return [
            'suggestions' => count($suggestions),
            'errors' => $errors,
        ];
    }

    /**
     * Generate a single SEO suggestion for one entity (for "Optimize with AI" button).
     */
    public function optimizeSingle(string $entityType, int $entityId): ?SeoSuggestion
    {
        if (!$this->apiKey) {
            return null;
        }

        $modelClass = $this->getModelClass($entityType);
        $entity = $this->loadEntityWithRelationships($entityType, $modelClass, $entityId);

        if (!$entity) {
            return null;
        }

        $prompt = $this->buildSinglePrompt($entityType, $entity);
        $result = $this->callClaudeApi($prompt);

        if ($result === null) {
            return null;
        }

        // The single-entity response has the entity ID as key, or might be a flat object
        $seo = $result[$entityId] ?? reset($result) ?: null;
        if (!$seo || !is_array($seo)) {
            return null;
        }

        $suggestionData = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'current_meta_title' => $entity->meta_title,
            'suggested_meta_title' => $seo['meta_title'] ?? null,
            'current_meta_description' => $entity->meta_description,
            'suggested_meta_description' => $seo['meta_description'] ?? null,
            'current_og_title' => $entity->og_title ?? null,
            'suggested_og_title' => $seo['og_title'] ?? null,
            'current_og_description' => $entity->og_description ?? null,
            'suggested_og_description' => $seo['og_description'] ?? null,
            'current_slug' => $entity->slug ?? null,
            'suggested_slug' => empty($entity->slug) ? ($seo['slug'] ?? null) : null,
            'status' => 'pending',
            'generated_at' => now(),
        ];

        // Backward compat: also set product_id for products
        if ($entityType === 'product') {
            $suggestionData['product_id'] = $entityId;
        }

        return SeoSuggestion::create($suggestionData);
    }

    /**
     * Map entity type string to model class.
     */
    private function getModelClass(string $type): string
    {
        return match ($type) {
            'product' => Product::class,
            'category' => Category::class,
            'brand' => Brand::class,
            'blog_post' => BlogPost::class,
            'page' => Page::class,
            default => throw new \InvalidArgumentException("Unknown entity type: {$type}"),
        };
    }

    /**
     * Query entities that need SEO generation.
     */
    private function getEntitiesNeedingSeo(string $entityType, string $modelClass): Collection
    {
        $rules = Setting::where('group', 'seo')
            ->where('key', 'like', 'rule_%')
            ->pluck('value', 'key')
            ->toArray();

        $skipPending = ($rules['rule_skip_pending'] ?? '1') === '1';
        $skipRecentlyAccepted = ($rules['rule_skip_recently_accepted'] ?? '1') === '1';
        $skipComplete = ($rules['rule_skip_complete'] ?? '0') === '1';
        $processEmpty = ($rules['rule_process_empty'] ?? '1') === '1';
        $processRejected = ($rules['rule_process_rejected'] ?? '1') === '1';
        $refreshDays = (int) ($rules['rule_refresh_days'] ?? 90);
        $batchLimit = (int) ($rules['rule_batch_limit'] ?? 0);

        $query = $modelClass::query();

        // Active filter
        if ($entityType === 'blog_post') {
            $query->where('is_published', true);
        } else {
            $query->where('is_active', true);
        }

        // Build inclusion criteria
        $query->where(function (Builder $q) use ($entityType, $processEmpty, $processRejected, $refreshDays) {
            if ($processEmpty) {
                $q->orWhere(function (Builder $q2) {
                    $q2->where(function ($q3) {
                        $q3->whereNull('meta_title')->orWhere('meta_title', '');
                    });
                });
            }

            if ($processRejected) {
                $q->orWhereHas('seoSuggestions', function (Builder $sq) use ($entityType) {
                    $sq->where('entity_type', $entityType)->where('status', 'rejected');
                });
            }

            // No suggestion at all
            $q->orWhereDoesntHave('seoSuggestions', function (Builder $sq) use ($entityType) {
                $sq->where('entity_type', $entityType);
            });

            // Due for refresh
            if ($refreshDays > 0) {
                $q->orWhere(function (Builder $q2) use ($entityType, $refreshDays) {
                    $q2->whereHas('seoSuggestions', function (Builder $sq) use ($entityType, $refreshDays) {
                        $sq->where('entity_type', $entityType)
                           ->where('status', 'accepted')
                           ->where('generated_at', '<', now()->subDays($refreshDays));
                    })->whereDoesntHave('seoSuggestions', function (Builder $sq) use ($entityType, $refreshDays) {
                        $sq->where('entity_type', $entityType)
                           ->where('status', 'accepted')
                           ->where('generated_at', '>=', now()->subDays($refreshDays));
                    });
                });
            }
        });

        // Exclusions
        if ($skipPending) {
            $query->whereDoesntHave('seoSuggestions', function (Builder $sq) use ($entityType) {
                $sq->where('entity_type', $entityType)->where('status', 'pending');
            });
        }

        if ($skipRecentlyAccepted && $refreshDays > 0) {
            $query->whereDoesntHave('seoSuggestions', function (Builder $sq) use ($entityType, $refreshDays) {
                $sq->where('entity_type', $entityType)
                   ->where('status', 'accepted')
                   ->where('generated_at', '>', now()->subDays($refreshDays));
            });
        }

        if ($skipComplete) {
            $query->where(function (Builder $q) {
                $q->whereNull('meta_title')
                  ->orWhere('meta_title', '')
                  ->orWhereNull('meta_description')
                  ->orWhere('meta_description', '');
            });
        }

        // Eager-load relationships
        if ($entityType === 'product') {
            $query->with(['brand', 'categories']);
        } elseif ($entityType === 'blog_post') {
            $query->with(['category', 'tags']);
        }

        $limit = $batchLimit > 0 ? $batchLimit : 1000;
        return $query->limit($limit)->get();
    }

    /**
     * Load a single entity with rich relationships for optimizeSingle.
     */
    private function loadEntityWithRelationships(string $entityType, string $modelClass, int $entityId)
    {
        $query = $modelClass::where('id', $entityId);

        return match ($entityType) {
            'product' => $query->with(['brand', 'categories'])->first(),
            'category' => $query->withCount('products')->first(),
            'brand' => $query->withCount('products')->first(),
            'blog_post' => $query->with(['category', 'tags'])->first(),
            default => $query->first(),
        };
    }

    /**
     * Build a batch prompt for multiple entities.
     */
    private function buildBatchPrompt(string $entityType, Collection $chunk): string
    {
        $entityData = $this->formatEntitiesForPrompt($entityType, $chunk);

        $typeLabel = str_replace('_', ' ', $entityType);
        $prompt = "You are an SEO specialist for " . $this->storeContext . ". Generate optimized meta tags and Open Graph tags for each {$typeLabel}.\n\n";
        $prompt .= "For each {$typeLabel}, provide:\n";
        $prompt .= "- meta_title: max 60 chars, include name + key selling point + store context\n";
        $prompt .= "- meta_description: max 155 chars, compelling, include call-to-action\n";
        $prompt .= "- og_title: max 60 chars, optimized for social sharing (engaging, click-worthy)\n";
        $prompt .= "- og_description: max 200 chars, engaging description for social media preview cards\n";
        $prompt .= "- slug: ONLY if the item has \"needs_slug\": true. URL-friendly slug (lowercase, hyphens, no special chars, max 80 chars). Omit slug for items that don't need it.\n\n";
        $prompt .= ucfirst($typeLabel) . "s:\n" . json_encode($entityData, JSON_PRETTY_PRINT);

        // Append global GSC data for batch
        $prompt .= $this->appendGscGlobalData();

        $prompt .= "\n\nRespond ONLY with valid JSON in this format:\n";
        $prompt .= "{\"<{$typeLabel}_id>\": {\"meta_title\": \"...\", \"meta_description\": \"...\", \"og_title\": \"...\", \"og_description\": \"...\"}, ...}";

        return $prompt;
    }

    /**
     * Build a rich single-entity prompt for optimizeSingle.
     */
    private function buildSinglePrompt(string $entityType, $entity): string
    {
        $typeLabel = str_replace('_', ' ', $entityType);
        $prompt = "You are an SEO specialist for " . $this->storeContext . ". Generate optimized meta tags and Open Graph tags for the following {$typeLabel}.\n\n";
        $prompt .= "Provide:\n";
        $prompt .= "- meta_title: max 60 chars, include name + key selling point + store context\n";
        $prompt .= "- meta_description: max 155 chars, compelling, include call-to-action\n";
        $prompt .= "- og_title: max 60 chars, optimized for social sharing (engaging, click-worthy)\n";
        $prompt .= "- og_description: max 200 chars, engaging description for social media preview cards\n";
        if (empty($entity->slug)) {
            $prompt .= "- slug: URL-friendly slug (lowercase, hyphens, no special chars, max 80 chars)\n";
        }
        $prompt .= "\n";

        $prompt .= $this->buildSingleEntityContext($entityType, $entity);

        // Try to get GSC data for this entity's URL
        $prompt .= $this->appendGscEntityData($entity);

        $prompt .= "\n\nRespond ONLY with valid JSON in this format:\n";
        $prompt .= "{\"{$entity->id}\": {\"meta_title\": \"...\", \"meta_description\": \"...\", \"og_title\": \"...\", \"og_description\": \"...\"}}";

        return $prompt;
    }

    /**
     * Format entity data for the batch prompt.
     */
    private function formatEntitiesForPrompt(string $entityType, Collection $chunk): array
    {
        return match ($entityType) {
            'product' => $chunk->map(function ($p) {
                $data = [
                    'id' => $p->id,
                    'name' => $p->name,
                    'brand' => $p->brand?->name ?? '',
                    'category' => $p->categories->pluck('name')->join(', '),
                    'price' => $p->price,
                    'description_excerpt' => substr(strip_tags($p->description ?? ''), 0, 200),
                ];
                if (empty($p->slug)) $data['needs_slug'] = true;
                return $data;
            })->values()->toArray(),

            'category' => $chunk->map(function ($c) {
                $data = [
                    'id' => $c->id,
                    'name' => $c->name,
                    'description_excerpt' => substr(strip_tags($c->description ?? ''), 0, 200),
                    'parent' => $c->parent?->name ?? '',
                ];
                if (empty($c->slug)) $data['needs_slug'] = true;
                return $data;
            })->values()->toArray(),

            'brand' => $chunk->map(function ($b) {
                $data = [
                    'id' => $b->id,
                    'name' => $b->name,
                    'description_excerpt' => substr(strip_tags($b->description ?? ''), 0, 200),
                ];
                if (empty($b->slug)) $data['needs_slug'] = true;
                return $data;
            })->values()->toArray(),

            'blog_post' => $chunk->map(function ($bp) {
                $data = [
                    'id' => $bp->id,
                    'title' => $bp->title,
                    'excerpt' => substr(strip_tags($bp->excerpt ?? ''), 0, 200),
                    'category' => $bp->category?->name ?? '',
                ];
                if (empty($bp->slug)) $data['needs_slug'] = true;
                return $data;
            })->values()->toArray(),

            'page' => $chunk->map(function ($p) {
                $data = [
                    'id' => $p->id,
                    'title' => $p->title,
                    'body_excerpt' => substr(strip_tags($p->body ?? ''), 0, 200),
                ];
                if (empty($p->slug)) $data['needs_slug'] = true;
                return $data;
            })->values()->toArray(),

            default => [],
        };
    }

    /**
     * Build rich context for a single entity.
     */
    private function buildSingleEntityContext(string $entityType, $entity): string
    {
        return match ($entityType) {
            'product' => $this->buildProductContext($entity),
            'category' => $this->buildCategoryContext($entity),
            'brand' => $this->buildBrandContext($entity),
            'blog_post' => $this->buildBlogPostContext($entity),
            'page' => $this->buildPageContext($entity),
            default => '',
        };
    }

    private function buildProductContext($product): string
    {
        $ctx = "Product details:\n";
        $ctx .= "- ID: {$product->id}\n";
        $ctx .= "- Name: {$product->name}\n";
        $ctx .= "- Brand: " . ($product->brand?->name ?? 'N/A') . "\n";
        $ctx .= "- Categories: " . $product->categories->pluck('name')->join(', ') . "\n";
        $ctx .= "- Price: {$product->price}\n";
        if ($product->sku) {
            $ctx .= "- SKU: {$product->sku}\n";
        }
        $ctx .= "- Description: " . substr(strip_tags($product->description ?? ''), 0, 500) . "\n";
        return $ctx;
    }

    private function buildCategoryContext($category): string
    {
        $ctx = "Category details:\n";
        $ctx .= "- ID: {$category->id}\n";
        $ctx .= "- Name: {$category->name}\n";
        if ($category->parent) {
            $ctx .= "- Parent category: {$category->parent->name}\n";
        }
        $ctx .= "- Description: " . substr(strip_tags($category->description ?? ''), 0, 500) . "\n";
        $productCount = $category->products_count ?? $category->products()->count();
        $ctx .= "- Product count: {$productCount}\n";
        $topProducts = $category->products()->where('is_active', true)->limit(5)->pluck('name');
        if ($topProducts->isNotEmpty()) {
            $ctx .= "- Top products: " . $topProducts->join(', ') . "\n";
        }
        return $ctx;
    }

    private function buildBrandContext($brand): string
    {
        $ctx = "Brand details:\n";
        $ctx .= "- ID: {$brand->id}\n";
        $ctx .= "- Name: {$brand->name}\n";
        $ctx .= "- Description: " . substr(strip_tags($brand->description ?? ''), 0, 500) . "\n";
        $productCount = $brand->products_count ?? $brand->products()->count();
        $ctx .= "- Product count: {$productCount}\n";
        $topProducts = $brand->products()->where('is_active', true)->limit(5)->pluck('name');
        if ($topProducts->isNotEmpty()) {
            $ctx .= "- Top products: " . $topProducts->join(', ') . "\n";
        }
        return $ctx;
    }

    private function buildBlogPostContext($post): string
    {
        $ctx = "Blog post details:\n";
        $ctx .= "- ID: {$post->id}\n";
        $ctx .= "- Title: {$post->title}\n";
        $ctx .= "- Category: " . ($post->category?->name ?? 'N/A') . "\n";
        $tags = $post->tags->pluck('name');
        if ($tags->isNotEmpty()) {
            $ctx .= "- Tags: " . $tags->join(', ') . "\n";
        }
        $ctx .= "- Excerpt: " . substr(strip_tags($post->excerpt ?? ''), 0, 500) . "\n";
        return $ctx;
    }

    private function buildPageContext($page): string
    {
        $ctx = "Page details:\n";
        $ctx .= "- ID: {$page->id}\n";
        $ctx .= "- Title: {$page->title}\n";
        $ctx .= "- Body excerpt: " . substr(strip_tags($page->body ?? ''), 0, 500) . "\n";
        return $ctx;
    }

    /**
     * Append top global GSC queries to a prompt.
     */
    private function appendGscGlobalData(): string
    {
        $gsc = new GoogleSearchConsoleService();
        if (!$gsc->isConfigured()) {
            return '';
        }

        $topQueries = array_slice($gsc->getSearchPerformance(28, 10), 0, 10);
        if (empty($topQueries)) {
            return '';
        }

        $text = "\n\nAdditionally, here are the top search queries driving traffic to this store (from Google Search Console):\n";
        foreach ($topQueries as $q) {
            $text .= "- \"{$q['query']}\" ({$q['clicks']} clicks, {$q['impressions']} impressions, {$q['ctr']}% CTR, position {$q['position']})\n";
        }
        $text .= "\nConsider these real search terms when optimizing meta tags \u2014 target queries where the store already has impressions but low CTR.";

        return $text;
    }

    /**
     * Append GSC page-specific data for a single entity URL.
     */
    private function appendGscEntityData($entity): string
    {
        $gsc = new GoogleSearchConsoleService();
        if (!$gsc->isConfigured()) {
            return '';
        }

        $slug = $entity->slug ?? null;
        if (!$slug) {
            return '';
        }

        // Try to match entity URL in GSC page performance data
        $pages = $gsc->getPagePerformance(28, 100);
        $matchingPage = collect($pages)->first(function ($page) use ($slug) {
            return str_contains($page['page'], $slug);
        });

        if (!$matchingPage) {
            return '';
        }

        $text = "\n\nGoogle Search Console data for this page:\n";
        $text .= "- URL: {$matchingPage['page']}\n";
        $text .= "- Clicks: {$matchingPage['clicks']}, Impressions: {$matchingPage['impressions']}\n";
        $text .= "- CTR: {$matchingPage['ctr']}%, Average Position: {$matchingPage['position']}\n";
        $text .= "\nOptimize meta tags to improve CTR for this page's current search position.";

        return $text;
    }

    /**
     * Generate a product-specific SEO report (backward compat).
     */
    private function generateProductReport(Collection $entities, array $suggestions): void
    {
        $searchInsights = [];
        $gsc = new GoogleSearchConsoleService();
        if ($gsc->isConfigured()) {
            $searchInsights = [
                'top_queries' => array_slice($gsc->getSearchPerformance(28, 20), 0, 20),
                'top_pages' => array_slice($gsc->getPagePerformance(28, 20), 0, 20),
            ];
        }

        $totalProducts = Product::where('is_active', true)->count();
        $withMeta = Product::where('is_active', true)
            ->whereNotNull('meta_title')
            ->where('meta_title', '!=', '')
            ->count();
        $healthScore = $totalProducts > 0 ? round(($withMeta / $totalProducts) * 100, 2) : 0;

        $topOps = Product::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('meta_title')->orWhere('meta_title', '');
            })
            ->orderByDesc('views')
            ->limit(10)
            ->get(['id', 'name', 'views'])
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'views' => $p->views])
            ->toArray();

        SeoReport::create([
            'store_id' => (int) config('app.store_id', 1),
            'total_products_analyzed' => $entities->count(),
            'suggestions_generated' => count($suggestions),
            'auto_accepted' => $this->autoAccept ? count($suggestions) : 0,
            'pending_review' => $this->autoAccept ? 0 : count($suggestions),
            'health_score' => $healthScore,
            'top_opportunities' => $topOps,
            'search_insights' => $searchInsights ?: null,
            'generated_at' => now(),
        ]);
    }

    /**
     * Call the Claude API with a prompt and return the parsed JSON response.
     */
    private function callClaudeApi(string $prompt): ?array
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
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
