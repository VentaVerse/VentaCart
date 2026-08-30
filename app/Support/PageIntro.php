<?php

namespace App\Support;

/**
 * What a page says about itself for the intro area: its title and its
 * trail. A page view sets it; the theme's intro widgets draw it.
 */
class PageIntro
{
    private static ?string $title = null;

    /** @var list<array{label: string, url: ?string}> */
    private static array $crumbs = [];

    /** @param list<array{label: string, url: ?string}> $crumbs */
    public static function set(?string $title, array $crumbs = []): void
    {
        self::$title = $title;
        self::$crumbs = array_values(array_filter($crumbs, fn ($c) => is_array($c) && ($c['label'] ?? '') !== ''));
    }

    public static function title(): ?string
    {
        return self::$title;
    }

    /** @return list<array{label: string, url: ?string}> */
    public static function crumbs(): array
    {
        return self::$crumbs;
    }

    public static function flush(): void
    {
        self::$title = null;
        self::$crumbs = [];
    }
}
