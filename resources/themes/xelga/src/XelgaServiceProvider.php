<?php

namespace Themes\Xelga;

use App\Support\AdminPermissions;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Themes\Xelga\Console\LintCatalog;
use Themes\Xelga\Documents\Assignment;
use Themes\Xelga\Preview\PreviewMode;
use Themes\Xelga\Preview\SectionOnly;
use Themes\Xelga\Rendering\DocumentRepository;
use Themes\Xelga\Rendering\SectionRenderer;
use Themes\Xelga\Rendering\Storefront;
use Themes\Xelga\Rendering\TemplateResolver;

/**
 * Xelga's own code: its editor routes, its views, the preview middleware
 * and its listeners. Booted by the extension kernel whenever the theme is
 * installed, live or not, so a merchant can prepare it before switching.
 * It never writes data while booting.
 */
class XelgaServiceProvider extends ServiceProvider
{
    public const SLUG = 'xelga';

    /** Requests per minute per admin, by route group (see the routes file). */
    public const LIMITS = [
        'xelga-editor' => 60,
        'xelga-read' => 240,
        'xelga-draft' => 120,
        'xelga-publish' => 10,
        'xelga-revisions' => 60,
        'xelga-presence' => 4,
        'xelga-pick' => 120,
        'xelga-preview' => 20,
        'xelga-section' => 120,
        'xelga-templates' => 20,
        'xelga-assign' => 60,
        'xelga-store-templates' => 10,
    ];

    public function register(): void
    {
        // One of each per request: the documents a page reads are loaded
        // once, and the sections drawn are listed for the preview.
        $this->app->scoped(DocumentRepository::class);
        $this->app->scoped(SectionRenderer::class);
        $this->app->scoped(TemplateResolver::class);
        $this->app->scoped(Storefront::class);
    }

    public function boot(): void
    {
        $root = dirname(__DIR__);

        $this->loadViewsFrom($root . '/views', 'xelga');
        $this->loadRoutesFrom($root . '/routes/admin.php');
        $this->loadRoutesFrom($root . '/routes/web.php');

        // The editor changes how the whole storefront looks, so it is held
        // to the store's theme permission. Core maps no theme's routes; an
        // unmapped one would be super admin only.
        AdminPermissions::map(['admin.xelga.*' => 'themes.edit']);

        foreach (self::LIMITS as $name => $perMinute) {
            RateLimiter::for($name, fn (Request $request) => Limit::perMinute($perMinute)->by($name . ':' . ($request->user()?->getAuthIdentifier() ?: $request->ip())));
        }

        // Through the HTTP kernel, which owns the groups and syncs them to
        // the router on every request. SectionOnly comes after PreviewMode,
        // so it runs inside it and only once the preview is decided.
        $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
        $kernel->appendMiddlewareToGroup('web', PreviewMode::class);
        $kernel->appendMiddlewareToGroup('web', SectionOnly::class);

        $this->forgetAssignmentsOfDeletedSubjects();
        $this->drawNotFoundPages();

        if ($this->app->runningInConsole()) {
            $this->commands([LintCatalog::class]);
        }
    }

    /**
     * A missing page answers with the theme's own "page not found" page
     * while Xelga is the theme shoppers see. Core's error page stays for
     * JSON callers, the admin, missing files (anything under build/,
     * storage/ or vendor/, or with a file extension) and stores where
     * Xelga is not live.
     */
    private function drawNotFoundPages(): void
    {
        try {
            $handler = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class);
            if (method_exists($handler, 'renderable')) {
                $handler->renderable(fn (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, Request $request) => \Themes\Xelga\Rendering\NotFoundPage::render($e, $request));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** A custom template assigned to a product, category, brand or page goes with it. */
    private function forgetAssignmentsOfDeletedSubjects(): void
    {
        $subjects = [
            'product' => \App\Models\Product::class,
            'category' => \App\Models\Category::class,
            'brand' => \App\Models\Brand::class,
            'page' => \App\Models\Page::class,
        ];

        foreach ($subjects as $type => $model) {
            $model::deleted(function ($subject) use ($type) {
                try {
                    Assignment::where('subject_type', $type)->where('subject_id', $subject->getKey())->delete();
                } catch (\Throwable $e) {
                    report($e);
                }
            });
        }
    }
}
