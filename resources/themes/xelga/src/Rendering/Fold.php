<?php

namespace Themes\Xelga\Rendering;

use Illuminate\Support\HtmlString;

/**
 * How a page's photos load, in the order the page draws them.
 *
 * The first photos of the page's own content are the ones a shopper sees
 * before scrolling, and the largest of them is usually what the browser
 * times as the page's largest paint: they load at once, and the first two
 * ask for high priority. Every photo after them is lazy, so a long page
 * does not spend a phone's bandwidth on photos far below the screen.
 * Header and footer photos (the logo, a menu promo) do not take a place.
 */
final class Fold
{
    /** Photos loaded at once: a phone's first screen holds about this many. */
    public const EAGER = 4;

    /** Of those, how many ask for high priority. */
    public const HIGH = 2;

    private static int $claimed = 0;

    /**
     * The loading attributes for the next photo of the page's content. A
     * photo the shopper cannot see at first (a later slide, a hidden
     * second photo) passes $visible false and is lazy without taking a place.
     */
    public static function attrs(RenderContext $context, bool $visible = true): HtmlString
    {
        if (! $visible || $context->group !== 'template' || self::$claimed >= self::EAGER) {
            return new HtmlString('loading="lazy" decoding="async"');
        }
        self::$claimed++;

        // A high-priority photo is likely the largest paint, so it is decoded
        // with the page rather than after it.
        return new HtmlString(self::$claimed <= self::HIGH ? 'fetchpriority="high"' : 'loading="eager" decoding="async"');
    }

    /** Whether the next photo would ask for high priority (for a preload). */
    public static function nextIsHigh(RenderContext $context): bool
    {
        return $context->group === 'template' && self::$claimed < self::HIGH;
    }

    /**
     * A page whose main photo fills the first screen (a product's gallery, a
     * post's photo, the first slide of a banner) ends the eager photos there:
     * what follows sits below it.
     */
    public static function close(): void
    {
        self::$claimed = self::EAGER;
    }

    public static function flush(): void
    {
        self::$claimed = 0;
    }
}
