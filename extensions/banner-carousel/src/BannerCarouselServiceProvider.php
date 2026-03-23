<?php

namespace Extensions\BannerCarousel;

use Extensions\BannerCarousel\Models\Banner;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class BannerCarouselServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'banner-carousel');

        // Inject slides into the homepage banner section
        View::composer('home', function ($view) {
            if (!Schema::hasTable('banners')) {
                return;
            }

            $slides = Banner::active()->ordered()->get()->map(function ($banner) {
                return [
                    'image' => $banner->image_url,
                    'image_mobile' => $banner->mobile_image_url,
                    'url' => $banner->button_url,
                    'new_tab' => $banner->button_new_tab,
                    'alt' => $banner->title ?? '',
                    'title' => $banner->title,
                    'subtitle' => $banner->subtitle,
                    'button_text' => $banner->button_text,
                    'text_color' => $banner->text_color,
                    'overlay_color' => $banner->overlay_color,
                ];
            })->toArray();

            $view->with('bannerSlides', $slides);
        });

        // Add sidebar link
        View::composer('layouts.admin', function ($view) {
            $view->with('bannerCarouselInstalled', true);
        });
    }
}
