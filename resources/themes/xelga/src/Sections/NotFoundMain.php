<?php

namespace Themes\Xelga\Sections;

use Illuminate\Support\Facades\Route;
use Themes\Xelga\Rendering\RenderContext;
use Themes\Xelga\Rendering\SectionData;
use Themes\Xelga\Settings\LinkValue;

/**
 * The "page not found" page: a message, a search of the shop through core's
 * search page, and buttons back into the store.
 */
final class NotFoundMain implements SectionData
{
    public function resolve(array $settings, array $blocks, RenderContext $context): array
    {
        $links = [];
        foreach ($blocks as $block) {
            if ($block['type'] === 'links') {
                $links[$block['id']] = LinkValue::resolve($block['settings']['shop_link'] ?? null);
            }
        }

        return [
            'searchUrl' => Route::has('search.results') ? route('search.results') : null,
            'homeUrl' => route('home'),
            'contactUrl' => Route::has('contact') ? route('contact') : null,
            'shopLinks' => $links,
        ];
    }
}
