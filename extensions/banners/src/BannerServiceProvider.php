<?php

namespace Extensions\Banners;

use Extensions\Banners\BannerPayload;

use Extensions\Banners\Models\BannerGroup;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class BannerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        /**
         * The banner group as a placeable widget.
         *
         * Registered HERE, by the module that owns it, so core never learns
         * what a banner group is. It also means a disabled banners extension
         * simply stops offering the widget: existing placements render nothing
         * on the storefront and are reported in the layout editor, rather than
         * a shopper meeting a broken slot because an admin flipped a switch.
         */
        \App\Support\Widgets::register('banners.group', [
            'title' => 'Banner group',
            'module' => 'banners',
            'view' => 'widgets.banners.group',
            'schema' => [
                // The SLUG, matching what the view already reads and what the
                // existing homepage sections already store. Inventing a
                // group_id here would have broken every section carried over.
                'banner_group' => [
                    'type' => 'select',
                    'label' => 'Which banner',
                    // Resolved when the editor asks, not at boot: a merchant
                    // creating a banner must see it in the list immediately.
                    'options' => fn () => \Extensions\Banners\Models\BannerGroup::where('is_active', true)
                        ->orderBy('name')->pluck('name', 'slug')->all(),
                    'create_route' => 'admin.banners.create',
                    'create_label' => 'New banner group',
                ],
            ],
            // A banner group is a full-bleed slider. It has no narrow form.
            'regions' => ['header_top', 'content_top', 'content_bottom', 'footer_top'],
            'resolve' => fn (array $config) => [
                'group' => BannerPayload::group($config['banner_group'] ?? null),
            ],
        ]);
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'banners');

        // One-time: patch existing banner sections that don't have a banner_group slug
        $this->patchLegacyBannerSections();

        // Inject banner groups into the homepage banner section
        // The View::composer('home') that used to share $bannerGroups is gone.
        // It bound banners to one view, queried on every home render whether or
        // not a banner was placed, and was the reason a banner group could not
        // appear anywhere else. The widget resolves its own group now.

        // Sidebar link
        View::composer('layouts.admin', function ($view) {
            $view->with('bannersInstalled', true);
        });
    }

    /**
     * Patch legacy HomepageSection banner configs that don't have a banner_group slug.
     * Assigns the first active BannerGroup (typically 'homepage-hero') to any
     * banner section missing the key. Runs once per deploy, skips if already patched.
     */
    protected function patchLegacyBannerSections(): void
    {
        try {
            if (!Schema::hasTable('homepage_sections') || !Schema::hasTable('banner_groups')) {
                return;
            }

            $sections = \App\Models\HomepageSection::where('type', 'banner')->get();
            $defaultSlug = BannerGroup::where('is_active', true)->value('slug');

            foreach ($sections as $section) {
                $config = $section->config ?? [];
                if (!empty($config['banner_group'])) {
                    continue;
                }

                $config['banner_group'] = $defaultSlug ?? 'homepage-hero';
                unset($config['width'], $config['autoplay'], $config['speed'], $config['slides']);
                $section->update(['config' => $config]);
            }
        } catch (\Throwable) {
            // Silent fail during early bootstrap (tables may not exist yet)
        }
    }
}
