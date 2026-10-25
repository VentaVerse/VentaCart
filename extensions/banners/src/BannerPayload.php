<?php

namespace Extensions\Banners;

use App\Support\SafeInput;
use Extensions\Banners\Models\BannerGroup;

/**
 * A banner group flattened for the storefront.
 *
 * This shape used to be built by a View::composer bound to the 'home' view,
 * which meant banners could only ever appear on the home page: any other view
 * simply never received $bannerGroups. Now the widget resolves it, so a banner
 * group renders wherever a merchant places it, and the home page stops paying
 * for the query when it has no banner on it.
 *
 * Every field passes through SafeInput and BannerData on the way out, so a
 * theme receives links, colours, paths and layer values in their checked
 * shapes even for a slide saved before those checks existed.
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
            'autoplay' => (bool) $group->autoplay,
            'speed' => SafeInput::int($group->speed, 1000, 30000, 5000),
            'height' => (int) $group->height,
            'height_mobile' => (int) $group->height_mobile,
            'full_width' => (bool) $group->full_width,
            'slides' => $group->activeSlides->map(fn ($banner) => [
                'image' => $banner->bg_image_url,
                'image_mobile' => $banner->bg_image_mobile_url,
                'url' => SafeInput::link($banner->link),
                'new_tab' => (bool) $banner->link_new_tab,
                'alt' => SafeInput::text($banner->name ?? '', 255),
                'bg_color' => SafeInput::color($banner->bg_color) ?: null,
                'bg_size' => BannerData::bgSize($banner->bg_size),
                'height' => (int) $group->height,
                'height_mobile' => (int) $group->height_mobile,
                'full_width' => (bool) $group->full_width,
                'layers' => BannerData::layers($banner->layers ?? []),
            ])->toArray(),
        ];
    }
}
