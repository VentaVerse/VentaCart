<?php

namespace App\Http\Controllers\Admin\Extensions;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Brand;
use App\Models\Category;
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

        return view('admin.extensions.seo.dashboard', compact(
            'healthScores', 'pendingCount', 'recentSuggestions', 'topQueries'
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
                    ->where('meta_description', '!=', '')
                    ->whereNotNull('og_title')
                    ->where('og_title', '!=', ''),
                'partial' => $query->where(function ($q) {
                    $q->where(function ($q2) {
                        $q2->whereNotNull('meta_title')->where('meta_title', '!=', '');
                    })->where(function ($q2) {
                        $q2->whereNull('og_title')->orWhere('og_title', '');
                    });
                })->orWhere(function ($q) use ($config) {
                    $q->where($config['activeScope'][0], $config['activeScope'][1])
                      ->where(function ($q2) {
                          $q2->whereNull('meta_title')->orWhere('meta_title', '');
                      })->where(function ($q2) {
                          $q2->whereNotNull('meta_description')->where('meta_description', '!=', '');
                      });
                }),
                'missing' => $query->where(function ($q) {
                    $q->whereNull('meta_title')->orWhere('meta_title', '');
                }),
                default => null,
            };
        }

        $entities = $query->orderBy('id', 'desc')->paginate(20)->appends($request->query());

        // Load pending suggestions for displayed entities
        $entityIds = $entities->pluck('id')->toArray();
        $modelClass = $config['model'];
        $pendingSuggestions = SeoSuggestion::where('entity_type', $modelClass)
            ->whereIn('entity_id', $entityIds)
            ->where('status', 'pending')
            ->get()
            ->keyBy('entity_id');

        $editRoute = $config['editRoute'];
        $entityType = $config['entityType'];

        return view('admin.extensions.seo.entities', compact(
            'entities', 'type', 'pendingSuggestions', 'editRoute', 'entityType', 'search', 'status'
        ));
    }

    /**
     * Generate SEO suggestions for all entities of a type.
     */
    public function generate(Request $request)
    {
        $request->validate([
            'entity_type' => 'required|in:product,category,brand,blog_post',
        ]);

        $service = new SeoGeneratorService();
        $result = $service->generateForType($request->entity_type);

        if (isset($result['error'])) {
            return back()->with('error', $result['error']);
        }

        if (isset($result['message'])) {
            return back()->with('success', $result['message']);
        }

        return back()->with('success', "Generated {$result['suggestions']} suggestion(s) with {$result['errors']} error(s).");
    }

    /**
     * Optimize a single entity via AJAX (returns suggestion data for preview).
     */
    public function optimize(Request $request)
    {
        $request->validate([
            'entity_type' => 'required|in:product,category,brand,blog_post',
            'entity_id' => 'required|integer',
        ]);

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
            ],
        ]);
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
            $entity->update($updateData);

            // Backward compat: also update via product_id for products
            if ($suggestion->product_id && $suggestion->entity_type === Product::class) {
                Product::where('id', $suggestion->product_id)->update($updateData);
            }
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
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return back()->with('error', 'No suggestions selected.');
        }

        $suggestions = SeoSuggestion::whereIn('id', $ids)
            ->where('status', 'pending')
            ->get();

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
    public function searchConsole()
    {
        $gsc = new GoogleSearchConsoleService();
        $gscConfigured = $gsc->isConfigured();
        $queries = $gscConfigured ? $gsc->getSearchPerformance(28, 50) : [];
        $pages = $gscConfigured ? $gsc->getPagePerformance(28, 50) : [];

        return view('admin.extensions.seo.search-console', compact(
            'gscConfigured', 'queries', 'pages'
        ));
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
        $request->validate([
            'claude_api_key' => 'nullable|string',
            'claude_model' => 'nullable|string|max:100',
            'gsc_site_url' => 'nullable|url|max:255',
            'auto_accept' => 'nullable|boolean',
        ]);

        $settings = [
            'claude_model' => $request->input('claude_model', 'claude-sonnet-4-5-20250514'),
            'gsc_site_url' => $request->input('gsc_site_url', ''),
            'auto_accept' => $request->boolean('auto_accept') ? '1' : '0',
        ];

        // Only update API key if a new one was provided
        if ($request->filled('claude_api_key')) {
            $settings['claude_api_key'] = Crypt::encryptString($request->input('claude_api_key'));
        }

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(
                ['group' => 'seo', 'key' => $key],
                ['value' => $value]
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
     * Entity type configurations.
     */
    private function entityConfigs(): array
    {
        return [
            'products' => ['model' => Product::class, 'activeScope' => ['is_active', true], 'entityType' => 'product', 'editRoute' => 'admin.products.edit'],
            'categories' => ['model' => Category::class, 'activeScope' => ['is_active', true], 'entityType' => 'category', 'editRoute' => 'admin.categories.edit'],
            'brands' => ['model' => Brand::class, 'activeScope' => ['is_active', true], 'entityType' => 'brand', 'editRoute' => 'admin.brands.edit'],
            'blog' => ['model' => BlogPost::class, 'activeScope' => ['is_published', true], 'entityType' => 'blog_post', 'editRoute' => 'admin.blog.edit'],
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
