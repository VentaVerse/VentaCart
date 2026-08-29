<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\Category;
use App\Models\NavMenu;
use App\Models\NavMenuItem;
use Illuminate\Support\Facades\Cache;

/**
 * A composed menu as the storefront draws it: plain arrays, nested, with
 * every URL resolved and every dropdown's contents attached, so a theme
 * renders shape and never asks the database what a category is.
 *
 * Cached per menu; ThemeService::clearCache() drops it whenever a menu,
 * category, page or brand the tree reads is saved.
 */
final class NavTree
{
    public const CACHE_PREFIX = 'nav_tree_';

    /** @return list<array> */
    public static function for(string $menuKey = NavMenu::MAIN): array
    {
        return Cache::rememberForever(self::CACHE_PREFIX . $menuKey, function () use ($menuKey) {
            $menu = NavMenu::where('key', $menuKey)->first();
            if (! $menu) {
                return [];
            }

            $items = NavMenuItem::where('menu_id', $menu->id)->where('is_enabled', true)
                ->with(['category', 'page'])->orderBy('sort_order')->orderBy('id')->get();

            $byParent = $items->groupBy(fn ($i) => $i->parent_id ? (string) $i->parent_id : '');

            $build = function (?int $parentId) use (&$build, $byParent): array {
                $out = [];
                foreach ($byParent->get($parentId ? (string) $parentId : '', collect()) as $item) {
                    $out[] = self::node($item, $build((int) $item->id));
                }

                return $out;
            };

            return $build(null);
        });
    }

    public static function forget(): void
    {
        foreach (NavMenu::pluck('key') as $key) {
            Cache::forget(self::CACHE_PREFIX . $key);
        }
        Cache::forget(self::CACHE_PREFIX . NavMenu::MAIN);
    }

    /** @param list<array> $children explicit child items, already built */
    private static function node(NavMenuItem $item, array $children): array
    {
        $config = $item->config ?? [];
        $accent = is_string($config['accent'] ?? null) && preg_match('/^#[0-9a-fA-F]{6}$/', $config['accent']) ? strtoupper($config['accent']) : null;

        $node = [
            'id' => $item->id,
            'type' => $item->type,
            'label' => $item->label,
            'url' => $item->resolved_url,
            'new_tab' => (bool) $item->new_tab,
            'accent' => $accent,
            'children' => $children,
            'dropdown' => null,
        ];

        switch ($item->type) {
            case 'category':
                if ($item->category && ($config['dropdown'] ?? 'subcategories') !== 'none') {
                    $columns = self::subcategories($item->category->id, (int) ($config['depth'] ?? 2));
                    $mega = ($config['mega'] ?? 'no') === 'yes';
                    $node['dropdown'] = [
                        'kind' => $mega ? 'mega' : 'list',
                        'all_label' => 'All ' . $item->label,
                        'columns' => $columns,
                        'banner' => $mega ? self::banner($config) : null,
                        'brands' => $mega && ($config['brands'] ?? 'no') === 'yes' ? self::brands() : [],
                    ];
                }
                break;
            case 'categories':
                $node['dropdown'] = ['kind' => 'list', 'all_label' => null, 'columns' => self::subcategories(null, 2), 'banner' => null, 'brands' => []];
                break;
            case 'brands':
                $node['dropdown'] = ['kind' => 'brands', 'all_label' => 'All brands', 'columns' => [], 'banner' => null, 'brands' => self::brands()];
                break;
            case 'html':
                $node['dropdown'] = ['kind' => 'html', 'html' => (string) ($config['html'] ?? ''), 'columns' => [], 'banner' => null, 'brands' => []];
                break;
        }

        return $node;
    }

    /**
     * Subcategories under one category (or the top level when null), each
     * with its own children one level down. Arrays: the theme reads names,
     * slugs, urls and images and nothing else.
     *
     * @return list<array{name: string, url: string, image: ?string, children: list<array>}>
     */
    private static function subcategories(?int $parentId, int $depth): array
    {
        $level = Category::query()
            ->where('is_active', true)
            ->when($parentId === null, fn ($q) => $q->whereNull('parent_id'), fn ($q) => $q->where('parent_id', $parentId))
            ->orderBy('sort_order')->orderBy('name')->get();

        return $level->map(fn ($c) => [
            'name' => $c->name,
            'url' => \Illuminate\Support\Facades\Route::has('category.show') ? route('category.show', $c->slug) : '/category/' . $c->slug,
            'image' => $c->image ?: null,
            'children' => $depth > 1 ? self::subcategories($c->id, $depth - 1) : [],
        ])->all();
    }

    /** @return list<array{name: string, url: string, image: ?string}> */
    private static function brands(): array
    {
        return Brand::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn ($b) => [
                'name' => $b->name,
                'url' => \Illuminate\Support\Facades\Route::has('brand.show') ? route('brand.show', $b->slug) : '/brand/' . $b->slug,
                'image' => $b->image ?: null,
            ])->all();
    }

    private static function banner(array $config): ?array
    {
        $image = trim((string) ($config['banner'] ?? ''));
        if ($image === '' || str_contains($image, '..')) {
            return null;
        }
        $url = trim((string) ($config['banner_url'] ?? ''));

        return [
            'image' => $image,
            'url' => $url !== '' && (str_starts_with($url, '/') || str_starts_with($url, 'http')) ? $url : null,
        ];
    }
}
