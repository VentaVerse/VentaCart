<?php

namespace Extensions\Banners;

use Extensions\Banners\Models\BannerGroup;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class BannerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'banners');

        // Inject banner groups into the homepage banner section
        View::composer('home', function ($view) {
            if (!Schema::hasTable('banner_groups')) {
                return;
            }

            $groups = BannerGroup::where('is_active', true)
                ->with('activeSlides')
                ->get()
                ->keyBy('slug');

            // Build slides per group
            $bannerGroups = [];
            foreach ($groups as $slug => $group) {
                $bannerGroups[$slug] = [
                    'autoplay' => $group->autoplay,
                    'speed' => $group->speed,
                    'height' => $group->height,
                    'height_mobile' => $group->height_mobile,
                    'full_width' => $group->full_width,
                    'slides' => $group->activeSlides->map(function ($banner) use ($group) {
                        return [
                            'image' => $banner->bg_image_url,
                            'image_mobile' => $banner->bg_image_mobile_url,
                            'url' => $banner->link,
                            'new_tab' => $banner->link_new_tab,
                            'alt' => $banner->name ?? '',
                            'bg_color' => $banner->bg_color,
                            'bg_size' => $banner->bg_size,
                            'height' => $group->height,
                            'height_mobile' => $group->height_mobile,
                            'full_width' => $group->full_width,
                            'layers' => $banner->layers ?? [],
                        ];
                    })->toArray(),
                ];
            }

            $view->with('bannerGroups', $bannerGroups);

            // Backward compat: provide bannerSlides from the homepage-hero group
            $heroGroup = $groups->get('homepage-hero');
            if ($heroGroup) {
                $view->with('bannerSlides', $bannerGroups['homepage-hero']['slides'] ?? []);
            }
        });

        // Sidebar link
        View::composer('layouts.admin', function ($view) {
            $view->with('bannersInstalled', true);
        });
    }
}
