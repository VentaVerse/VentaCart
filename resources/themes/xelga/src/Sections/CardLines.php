<?php

namespace Themes\Xelga\Sections;

use App\Support\ProductCardLines;
use Themes\Xelga\Rendering\RenderContext;

/**
 * Whether product cards carry the lines extensions add (core's
 * ProductCardLines). Shoppers get them while the theme setting is on, so a
 * store that hides them never asks an extension for them; the editor's
 * preview always has them, so the setting can be switched live.
 */
final class CardLines
{
    public const SETTING = 'show_extension_details';

    public static function wanted(?RenderContext $context = null): bool
    {
        if (! ProductCardLines::has()) {
            return false;
        }

        return ($context?->preview ?? false) || ThemeValues::get(self::SETTING) === true;
    }
}
