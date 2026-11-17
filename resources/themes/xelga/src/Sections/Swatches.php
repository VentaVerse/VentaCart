<?php

namespace Themes\Xelga\Sections;

use Themes\Xelga\Rendering\RenderContext;

/**
 * Whether product cards carry color dots. Shoppers get them only when the
 * theme setting is on, so a store without them pays no extra query; the
 * editor's preview always has them, so the setting can be switched live.
 */
final class Swatches
{
    public static function wanted(RenderContext $context): bool
    {
        return $context->preview || ThemeValues::get('show_swatches') === true;
    }
}
