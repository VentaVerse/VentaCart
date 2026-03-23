<?php

namespace Extensions\Banners;

use Extensions\Banners\Models\Banner;
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

        // Inject slides into the homepage banner section
        View::composer('home', function ($view) {
            if (!Schema::hasTable('banners')) {
                return;
            }

            $slides = Banner::active()->ordered()->get()->map(function ($banner) {
                return [
                    'image' => $banner->bg_image_url,
                    'image_mobile' => $banner->bg_image_mobile_url,
                    'url' => $banner->link,
                    'new_tab' => $banner->link_new_tab,
                    'alt' => $banner->name ?? '',
                    'bg_color' => $banner->bg_color,
                    'bg_size' => $banner->bg_size,
                    'height' => $banner->height,
                    'height_mobile' => $banner->height_mobile,
                    'full_width' => $banner->full_width,
                    'layers' => $banner->layers ?? [],
                ];
            })->toArray();

            $view->with('bannerSlides', $slides);
        });

        // Sidebar link
        View::composer('layouts.admin', function ($view) {
            $view->with('bannersInstalled', true);
        });
    }
}
