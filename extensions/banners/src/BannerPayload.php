<?php

namespace Extensions\Banners;

use Extensions\Banners\Models\BannerGroup;

/**
 * A banner group flattened for the storefront.
 *
 * This shape used to be built by a View::composer bound to the 'home' view,
 * which meant banners could only ever appear on the home page: any other view
 * simply never received $bannerGroups. Now the widget resolves it, so a banner
 * group renders wherever a merchant places it, and the home page stops paying
 * for the query when it has no banner on it.
 */
class BannerPayload
{
    public static function group(?string $slug): ?array
    {
        if (! $slug) {
            return null;
        }

        $group = BannerGroup::where('is_active', true)
            ->where('slug', $slug)
            ->with('activeSlides')
            ->first();

        return $group ? self::flatten($group) : null;
    }

    private static function flatten(BannerGroup $group): array
    {
        return [
            'autoplay' => $group->autoplay,
            'speed' => $group->speed,
            'height' => $group->height,
            'height_mobile' => $group->height_mobile,
            'full_width' => $group->full_width,
            'slides' => $group->activeSlides->map(fn ($banner) => [
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
            ])->toArray(),
        ];
    }
}
