<?php

namespace Themes\Xelga\Http;

use App\Models\NavMenu;
use App\Models\User;
use App\Support\StoreIdentity;
use Illuminate\Support\Facades\Route;
use Themes\Xelga\Catalog\Catalog;
use Themes\Xelga\Documents\Document;
use Themes\Xelga\Documents\DocumentPresenter;
use Themes\Xelga\Documents\Handles;
use Themes\Xelga\Documents\TemplateManager;
use Themes\Xelga\Preview\PreviewMode;

/**
 * Everything the editor needs when it opens, as one JSON object printed in
 * the shell: the catalog, the store's documents, the pages it can edit,
 * the signed-in admin, the CSRF token, every endpoint and the preview
 * frame's address.
 */
final class BootPayload
{
    /**
     * The pages the editor's page picker lists, in its order. A page with
     * `subjects` can have special layouts, assigned to that kind of record;
     * `route` is where its preview opens (a sample record is filled in for
     * pages about one record).
     */
    public const PAGES = [
        ['type' => 'home', 'label' => 'Home page', 'route' => 'home'],
        ['type' => 'product', 'label' => 'Product', 'route' => 'product.show', 'subjects' => 'product'],
        ['type' => 'category', 'label' => 'Category', 'route' => 'category.show', 'subjects' => 'category'],
        ['type' => 'brand', 'label' => 'Brand', 'route' => 'brand.show', 'subjects' => 'brand'],
        ['type' => 'brand-list', 'label' => 'All brands', 'route' => 'brand.index'],
        ['type' => 'search', 'label' => 'Search results', 'route' => 'search.results'],
        ['type' => 'cart', 'label' => 'Cart', 'route' => 'cart.index'],
        ['type' => 'blog', 'label' => 'Blog', 'route' => 'blog.index'],
        ['type' => 'blog-post', 'label' => 'Blog post', 'route' => 'blog.show', 'subjects' => 'blog_post'],
        ['type' => 'page', 'label' => 'Page', 'route' => 'page.show', 'subjects' => 'page'],
        ['type' => 'contact', 'label' => 'Contact', 'route' => 'contact'],
        ['type' => 'not-found', 'label' => 'Page not found', 'route' => null],
    ];

    /**
     * Pages with a fixed layout, only styled by theme settings: the editor
     * opens them read-only with the setting groups that change them.
     */
    public const FIXED_PAGES = [
        [
            'type' => 'checkout', 'label' => 'Checkout', 'route' => 'checkout.index',
            'groups' => ['colors', 'typography', 'buttons', 'layout', 'cart-checkout'],
            'note' => 'Checkout keeps a fixed layout, so paying always works the same way and no change here can break an order.',
        ],
        [
            'type' => 'auth', 'label' => 'Sign in', 'route' => 'customer.login',
            'groups' => ['colors', 'typography', 'buttons', 'layout'],
            'note' => 'Sign in, create an account and reset password keep a fixed layout, so passwords are always handled the same safe way.',
        ],
        [
            'type' => 'account', 'label' => 'Account', 'route' => 'account.dashboard',
            'groups' => ['colors', 'typography', 'buttons', 'layout'],
            'note' => 'The account pages (orders, order details, addresses, wishlist) keep a fixed layout, so customers always find their orders in the same place.',
        ],
    ];

    /** A path no store page uses, so the preview of "Page not found" draws the store's 404 page. */
    public const NOT_FOUND_PATH = 'xelga-preview-missing-page';

    /**
     * The shell's payload. The preview window is not open yet: the editor
     * opens it with a POST (EditorController::openPreview), so `preview`
     * carries no nonce here.
     */
    public static function build(User $user): array
    {
        // The first page's documents ride along, so the editor opens without
        // a request; every other page is loaded when the merchant opens it.
        $handles = [Handles::SETTINGS, ...Handles::GROUPS, 'home'];
        $rows = Document::query()->whereIn('handle', $handles)->get()->keyBy('handle');

        $documents = [];
        foreach ($handles as $handle) {
            $documents[$handle] = DocumentPresenter::state($handle, $rows->get($handle));
        }
        $summary = app(TemplateManager::class)->summary();

        $home = self::home();
        $origin = rtrim(request()->getSchemeAndHttpHost(), '/');
        $handleToken = '__HANDLE__';
        $revisionToken = '__REVISION__';
        $kindToken = '__KIND__';

        return [
            'protocol' => 1,
            'theme' => ['name' => 'Xelga', 'slug' => 'xelga', 'version' => Catalog::themeVersion()],
            'store' => ['name' => StoreIdentity::name(), 'url' => $home, 'origin' => $origin],
            'user' => [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'can' => [
                    'publish' => $user->hasPermission('themes.edit'),
                    'media_view' => $user->hasPermission('media.view'),
                    'media_upload' => $user->hasPermission('media.create'),
                    'menus_edit' => $user->hasPermission('menus.edit'),
                ],
            ],
            'csrf' => csrf_token(),
            'catalog' => [
                'sections' => Catalog::sections(),
                'theme_settings' => Catalog::themeSettingGroups(),
                'font_pairs' => Catalog::fontPairs(),
            ],
            'options' => [
                'menus' => NavMenu::query()->orderBy('name')->get(['key', 'name'])->map(fn ($m) => ['value' => (string) $m->key, 'label' => (string) $m->name])->values()->all(),
                'font_pairs' => array_map(fn (array $p) => ['value' => $p['value'], 'label' => $p['label']], Catalog::fontPairs()),
                'blog_categories' => self::blogCategories(),
            ],
            'documents' => $documents,
            // The starting stores for "Start from a store", and whether the
            // store has products yet, for the empty state of product sections.
            'store_templates' => \Themes\Xelga\StoreTemplates\StoreTemplates::summaries(),
            'products_exist' => \App\Models\Product::query()->exists(),
            'pages' => self::pages(),
            'templates' => array_map(fn (array $t) => $t + self::previewFor($t), $summary['templates']),
            'drafts' => (object) $summary['drafts'],
            'preview' => [
                'url' => $home,
                'param' => PreviewMode::QUERY,
                'template_param' => \Themes\Xelga\Rendering\TemplateResolver::PREVIEW_QUERY,
                'section_param' => \Themes\Xelga\Preview\SectionOnly::QUERY,
                'nonce' => '',
                'until' => 0,
                'origin' => $origin,
            ],
            'routes' => [
                'documents_show' => route('admin.xelga.documents.show', ['handle' => $handleToken]),
                'documents_draft' => route('admin.xelga.documents.draft', ['handle' => $handleToken]),
                'documents_discard' => route('admin.xelga.documents.discard'),
                'documents_duplicate' => route('admin.xelga.documents.duplicate', ['handle' => $handleToken]),
                'publish' => route('admin.xelga.publish'),
                'revisions_index' => route('admin.xelga.revisions.index', ['handle' => $handleToken]),
                'revisions_restore' => route('admin.xelga.revisions.restore', ['handle' => $handleToken, 'revision' => 0]),
                'presence' => route('admin.xelga.presence'),
                'pick' => route('admin.xelga.pick', ['kind' => $kindToken]),
                'templates_index' => route('admin.xelga.templates.index'),
                'templates_store' => route('admin.xelga.templates.store'),
                'templates_update' => self::customRoute('admin.xelga.templates.update', $handleToken),
                'templates_destroy' => self::customRoute('admin.xelga.templates.destroy', $handleToken),
                'assignments_update' => route('admin.xelga.assignments.update'),
                'store_templates_apply' => route('admin.xelga.store-templates.apply', ['slug' => '__SLUG__']),
                'store_templates_undo' => route('admin.xelga.store-templates.undo'),
                'preview_open' => route('admin.xelga.preview.open'),
                'preview_close' => route('admin.xelga.preview.close'),
                'media_browse' => Route::has('admin.media.browse') ? route('admin.media.browse') : null,
                'media_upload' => Route::has('admin.media.upload') ? route('admin.media.upload') : null,
                'menus_edit' => Route::has('admin.menus') ? route('admin.menus') : null,
                'exit' => Route::has('admin.theme.index') ? route('admin.theme.index') : url('/'),
            ],
            'route_tokens' => ['handle' => $handleToken, 'revision' => '0', 'kind' => $kindToken, 'slug' => '__SLUG__'],
            'media' => ['root' => 'media', 'extensions' => ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif']],
            'limits' => ['sections' => 40, 'document_bytes' => 262144, 'heartbeat_seconds' => 30, 'autosave_ms' => 1500],
        ];
    }

    /**
     * What the editor needs about a window it just opened.
     *
     * @param  array{nonce: string, until: int}  $preview
     * @return array{nonce: string, until: int, url: string}
     */
    public static function preview(array $preview): array
    {
        $home = self::home();
        $sep = str_contains($home, '?') ? '&' : '?';

        return [
            'nonce' => $preview['nonce'],
            'until' => $preview['until'],
            'url' => $home . $sep . PreviewMode::QUERY . '=' . rawurlencode($preview['nonce']),
        ];
    }

    /**
     * Every page the picker lists, with where its preview opens. Pages
     * about one record open on a sample: the first active one core has.
     *
     * @return list<array>
     */
    public static function pages(): array
    {
        $out = [];
        foreach (self::PAGES as $page) {
            $subject = isset($page['subjects']) ? self::sample($page['subjects']) : null;
            $out[] = [
                'type' => $page['type'],
                'handle' => $page['type'],
                'label' => $page['label'],
                'kind' => 'template',
                'subject_type' => $page['subjects'] ?? null,
                'subject' => $subject,
                'url' => self::pageUrl($page['type'], $page['route'], $subject),
            ];
        }
        foreach (self::FIXED_PAGES as $page) {
            $out[] = [
                'type' => $page['type'],
                'handle' => null,
                'label' => $page['label'],
                'kind' => 'fixed',
                'subject_type' => null,
                'subject' => null,
                'url' => Route::has($page['route']) ? route($page['route']) : null,
                'groups' => $page['groups'],
                'note' => $page['note'],
            ];
        }

        return $out;
    }

    /**
     * Where a special layout's preview opens: on a record that uses it, else
     * on the page type's sample record, with the layout named so the preview
     * draws it either way.
     *
     * @param  array{handle: string, page_type: string, subject_type: ?string, assigned: list<int>}  $template
     * @return array{url: ?string, subject: ?array}
     */
    public static function previewFor(array $template): array
    {
        $page = collect(self::PAGES)->firstWhere('type', $template['page_type']);
        $type = (string) ($template['subject_type'] ?? '');
        if ($page === null || $type === '') {
            return ['url' => null, 'subject' => null];
        }
        $subject = self::sample($type, array_slice((array) ($template['assigned'] ?? []), 0, 20)) ?? self::sample($type);
        $url = self::pageUrl($page['type'], $page['route'], $subject);
        if ($url !== null) {
            $url .= (str_contains($url, '?') ? '&' : '?') . \Themes\Xelga\Rendering\TemplateResolver::PREVIEW_QUERY . '=' . rawurlencode($template['handle']);
        }

        return ['url' => $url, 'subject' => $subject];
    }

    /**
     * The first record of a kind a guest can open, through core's models:
     * switched on (published, for blog posts), and for products not kept
     * for a customer group, since the preview shows what a guest sees.
     * With $among, only those ids are considered.
     *
     * @param  list<int>|null  $among
     * @return array{type: string, id: int, name: string, slug: string}|null
     */
    public static function sample(string $subjectType, ?array $among = null): ?array
    {
        if ($among === []) {
            return null;
        }
        $model = TemplateManager::subjectModel($subjectType);
        if ($model === null) {
            return null;
        }

        $query = $model::query();
        if ($among !== null) {
            $query->whereKey($among);
        }
        $nameColumn = 'name';
        switch ($subjectType) {
            case 'product':
                $query->where('is_active', true)->whereDoesntHave('customerGroups')->orderBy('sort_order')->orderBy('id');
                break;
            case 'category':
            case 'brand':
                $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
                break;
            case 'page':
                $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
                $nameColumn = 'title';
                break;
            case 'blog_post':
                $query->published()->orderByDesc('published_at')->orderByDesc('id');
                $nameColumn = 'title';
                break;
        }

        try {
            $row = $query->first(['id', 'slug', $nameColumn]);
        } catch (\Throwable) {
            return null;
        }

        return $row === null ? null : [
            'type' => $subjectType,
            'id' => (int) $row->id,
            'name' => (string) $row->{$nameColumn},
            'slug' => (string) $row->slug,
        ];
    }

    /** The storefront address of a page, or null when it cannot be opened on this store. */
    private static function pageUrl(string $type, ?string $routeName, ?array $subject): ?string
    {
        if ($type === 'not-found') {
            return url('/' . self::NOT_FOUND_PATH);
        }
        if ($routeName === null || ! Route::has($routeName)) {
            return null;
        }

        return match ($type) {
            'product' => $subject ? route($routeName, ['product' => $subject['slug']]) : null,
            'category' => $subject ? route($routeName, ['category' => $subject['slug']]) : null,
            'brand' => $subject ? route($routeName, ['brand' => $subject['slug']]) : null,
            'blog-post' => $subject ? route($routeName, ['post' => $subject['slug']]) : null,
            'page' => $subject ? route($routeName, ['slug' => $subject['slug']]) : null,
            'search' => route($routeName, ['q' => self::sampleQuery()]),
            default => route($routeName),
        };
    }

    /** A search that finds something: the first word of the sample product's name. */
    private static function sampleQuery(): string
    {
        $name = (string) (self::sample('product')['name'] ?? '');
        preg_match('/\p{L}{3,}/u', $name, $m);

        return mb_strtolower($m[0] ?? 'a');
    }

    /** A route with a custom template handle in it, with the editor's token where the handle goes. */
    private static function customRoute(string $name, string $token): string
    {
        $placeholder = 'page.handle-token';

        return str_replace($placeholder, $token, route($name, ['handle' => $placeholder]));
    }

    private static function home(): string
    {
        return Route::has('home') ? route('home') : url('/');
    }

    /**
     * The blog's categories for the blog posts section's picker; none while
     * the blog extension is not installed.
     *
     * @return list<array{value: string, label: string}>
     */
    private static function blogCategories(): array
    {
        $model = \Extensions\Blog\Models\BlogCategory::class;
        if (! class_exists($model) || ! \Illuminate\Support\Facades\Schema::hasTable('blog_categories')) {
            return [];
        }

        return $model::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])
            ->map(fn ($c) => ['value' => (string) $c->id, 'label' => (string) $c->name])
            ->values()->all();
    }
}
