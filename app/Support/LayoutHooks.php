<?php

namespace App\Support;

use App\Models\LayoutPlacement;
use App\Models\LayoutRow;
use Illuminate\Support\Collection;

/**
 * Where a theme's own editor plugs into the storefront render.
 *
 * Core draws regions; an editor that edits on the page needs to
 * draw them from an unsaved draft, wrap every row and placement in
 * something it can point at, and show an empty region as a place to drop
 * into. These hooks are those four seams, registered per request by the
 * editor and untouched on every other request.
 */
final class LayoutHooks
{
    /** @var (\Closure(?string, string): ?Collection)|null */
    private static ?\Closure $units = null;

    /** @var (\Closure(string, ?LayoutRow, string, int, ?string): string)|null */
    private static ?\Closure $row = null;

    /** @var (\Closure(string, LayoutPlacement, string): string)|null */
    private static ?\Closure $placement = null;

    /** @var (\Closure(string, ?string): string)|null */
    private static ?\Closure $empty = null;

    public static function units(callable $fn): void { self::$units = \Closure::fromCallable($fn); }
    public static function row(callable $fn): void { self::$row = \Closure::fromCallable($fn); }
    public static function placement(callable $fn): void { self::$placement = \Closure::fromCallable($fn); }
    public static function emptyRegion(callable $fn): void { self::$empty = \Closure::fromCallable($fn); }

    public static function active(): bool
    {
        return self::$units !== null || self::$row !== null || self::$placement !== null || self::$empty !== null;
    }

    /** The units an editor wants drawn instead of the saved ones, or null. */
    public static function resolveUnits(?string $pageType, string $region): ?Collection
    {
        return self::$units ? (self::$units)($pageType, $region) : null;
    }

    public static function wrapRow(string $html, ?LayoutRow $row, string $region, int $index, ?string $pageType): string
    {
        return self::$row ? (self::$row)($html, $row, $region, $index, $pageType) : $html;
    }

    public static function wrapPlacement(string $html, LayoutPlacement $placement, string $region): string
    {
        return self::$placement ? (self::$placement)($html, $placement, $region) : $html;
    }

    public static function renderEmpty(string $region, ?string $pageType): string
    {
        return self::$empty ? (self::$empty)($region, $pageType) : '';
    }

    public static function reset(): void
    {
        self::$units = self::$row = self::$placement = self::$empty = null;
    }
}
