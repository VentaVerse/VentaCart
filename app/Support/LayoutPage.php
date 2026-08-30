<?php

namespace App\Support;

use App\Models\Category;
use App\Models\LayoutPlacement;
use App\Models\LayoutRow;
use App\Models\Page;
use Illuminate\Support\Facades\Route;

/**
 * The pages a layout can be arranged for.
 *
 * A page key is a page type (`category`), every page (`*`), a level of
 * categories (`category:level2`), one category (`category:12`), one content
 * page (`page:3`) or the quick view. The storefront resolves the most
 * specific key that has rows in a region, on top of what every page has.
 */
class LayoutPage
{
    public const LEVELS = 3;

    public const QUICKVIEW = 'quickview';

    public static function valid(?string $key): bool
    {
        if ($key === null || $key === '') {
            return false;
        }
        if ($key === LayoutPlacement::EVERYWHERE || $key === self::QUICKVIEW || StorefrontPageType::exists($key)) {
            return true;
        }
        if (preg_match('/^category:level([1-' . self::LEVELS . '])$/', $key)) {
            return true;
        }
        if (preg_match('/^category:(\d+)$/', $key, $m)) {
            return Category::whereKey((int) $m[1])->exists();
        }
        if (preg_match('/^page:(\d+)$/', $key, $m)) {
            return Page::whereKey((int) $m[1])->exists();
        }

        return false;
    }

    /** The page type a key belongs to: what page settings and widgets read. */
    public static function type(string $key): string
    {
        return str_contains($key, ':') ? strstr($key, ':', true) : $key;
    }

    public static function label(string $key): string
    {
        if ($key === LayoutPlacement::EVERYWHERE) {
            return 'Global';
        }
        if ($key === self::QUICKVIEW) {
            return 'Quick view';
        }
        if (preg_match('/^category:level(\d)$/', $key, $m)) {
            return "Level {$m[1]} categories";
        }
        if (preg_match('/^category:(\d+)$/', $key, $m)) {
            return 'Category: ' . (Category::find((int) $m[1])?->name ?? "#{$m[1]}");
        }
        if (preg_match('/^page:(\d+)$/', $key, $m)) {
            return 'Page: ' . (Page::find((int) $m[1])?->title ?? "#{$m[1]}");
        }

        return match ($key) {
            'category' => 'All categories',
            'product' => 'All products',
            'page' => 'All content pages',
            default => StorefrontPageType::label($key) ?? ucfirst($key),
        };
    }

    /**
     * From the most specific key down to every page: `category:12`,
     * `category:level2`, `category`, `*`.
     */
    public static function chain(?string $key): array
    {
        if ($key === null || $key === '' || $key === LayoutPlacement::EVERYWHERE) {
            return [LayoutPlacement::EVERYWHERE];
        }
        $chain = [$key];
        if (preg_match('/^category:(\d+)$/', $key, $m)) {
            $level = self::levelOf((int) $m[1]);
            if ($level !== null) {
                $chain[] = "category:level{$level}";
            }
            $chain[] = 'category';
        } elseif (preg_match('/^category:level\d$/', $key)) {
            $chain[] = 'category';
        } elseif (preg_match('/^page:\d+$/', $key)) {
            $chain[] = 'page';
        }
        $chain[] = LayoutPlacement::EVERYWHERE;

        return array_values(array_unique($chain));
    }

    /** 1 for a root category, 2 for its children, capped at LEVELS. */
    public static function levelOf(int $categoryId): ?int
    {
        $category = Category::find($categoryId);
        if (! $category) {
            return null;
        }
        $level = 1;
        $seen = [$category->id];
        while ($category->parent_id && ! in_array($category->parent_id, $seen, true) && $level < self::LEVELS) {
            $category = Category::find($category->parent_id);
            if (! $category) {
                break;
            }
            $seen[] = $category->id;
            $level++;
        }

        return $level;
    }

    /** The most specific key the current request is on. */
    public static function currentKey(): ?string
    {
        $route = Route::currentRouteName();
        if ($route === 'product.quickview') {
            return self::QUICKVIEW;
        }
        $type = StorefrontPageType::forRoute($route);
        if ($type === null) {
            return null;
        }
        $subject = request()->route($type);
        if ($type === 'category' && $subject instanceof Category) {
            return "category:{$subject->id}";
        }
        if ($type === 'page') {
            // The content page route carries a slug, not a model.
            $slug = $subject instanceof Page ? null : (request()->route('slug') ?? request()->route('page'));
            $page = $subject instanceof Page ? $subject : (is_string($slug) ? Page::where('slug', $slug)->first() : null);
            if ($page) {
                return "page:{$page->id}";
            }
        }

        return $type;
    }

    /**
     * Of the keys the chain names, the one whose rows this region shows:
     * the most specific that has any. Every page's rows show as well.
     */
    public static function pick(array $chain, array $present): ?string
    {
        foreach ($chain as $key) {
            if ($key !== LayoutPlacement::EVERYWHERE && in_array($key, $present, true)) {
                return $key;
            }
        }

        return null;
    }

    /** The fixed keys an editor always offers, in BurnEngine's order. */
    public static function fixed(): array
    {
        $keys = [LayoutPlacement::EVERYWHERE, 'home', 'category'];
        for ($i = 1; $i <= self::LEVELS; $i++) {
            $keys[] = "category:level{$i}";
        }
        $keys[] = 'product';
        foreach (array_keys(StorefrontPageType::TYPES) as $type) {
            if (! in_array($type, $keys, true)) {
                $keys[] = $type;
            }
        }
        $keys[] = self::QUICKVIEW;

        return $keys;
    }

    /** Keys with rows of their own for the theme, beyond the fixed ones. */
    public static function modified(?string $theme = null): array
    {
        $theme = LayoutPlacement::scopeTheme($theme);
        $keys = LayoutRow::where('theme', $theme)->distinct()->pluck('page_type')
            ->merge(LayoutPlacement::where('theme', $theme)->distinct()->pluck('page_type'))
            ->unique()->values()->all();

        return array_values(array_filter($keys, fn ($k) => self::valid($k)));
    }
}
