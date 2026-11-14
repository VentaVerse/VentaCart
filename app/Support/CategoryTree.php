<?php

namespace App\Support;

use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The category tree, at any depth. Categories link to their parent only, so
 * a branch can go as deep as the merchant needs; this walks it in one query.
 *
 * Every walk keeps track of where it has been, so a loop in the data (a
 * category saved under its own descendant by an older version or a direct
 * database edit) ends the walk instead of hanging the page.
 */
final class CategoryTree
{
    /** Shown between the names of a category's path: "Guitars > Electric Guitars". */
    public const SEPARATOR = ' > ';

    /**
     * Every category as its full path, in tree order (parents before their
     * children, siblings by sort order then name).
     *
     * @return list<array{id: int, name: string, depth: int, slug: ?string, is_active: bool}>
     */
    public static function options(bool $activeOnly = false): array
    {
        $all = Category::query()
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'parent_id', 'name', 'slug', 'is_active']);

        $known = $all->pluck('id')->flip();
        // A category whose parent is missing (or filtered out) is drawn at the top.
        $byParent = $all->groupBy(fn ($c) => $c->parent_id && $known->has($c->parent_id) ? (int) $c->parent_id : 0);

        $out = [];
        $seen = [];
        $walk = function (int $parentId, string $prefix, int $depth) use (&$walk, &$out, &$seen, $byParent) {
            foreach ($byParent->get($parentId, collect()) as $c) {
                if (isset($seen[$c->id])) {
                    continue;
                }
                $seen[$c->id] = true;
                $name = $prefix . $c->name;
                $out[] = ['id' => (int) $c->id, 'name' => $name, 'depth' => $depth, 'slug' => $c->slug, 'is_active' => (bool) $c->is_active];
                $walk((int) $c->id, $name . self::SEPARATOR, $depth + 1);
            }
        };
        $walk(0, '', 0);

        return $out;
    }

    /**
     * A category and every category below it, at any depth.
     *
     * @return list<int>
     */
    public static function branch(int $id, bool $activeOnly = false): array
    {
        $byParent = self::byParent($activeOnly);
        $ids = [$id];
        $seen = [$id => true];
        for ($i = 0; $i < count($ids); $i++) {
            foreach ($byParent->get($ids[$i], collect()) as $childId) {
                if (! isset($seen[$childId])) {
                    $seen[$childId] = true;
                    $ids[] = (int) $childId;
                }
            }
        }

        return $ids;
    }

    /**
     * Where a category may move to: every category except itself and the
     * ones below it, since a category under its own descendant is a loop.
     *
     * @return list<array{id: int, name: string, depth: int, slug: ?string, is_active: bool}>
     */
    public static function parentOptions(?int $categoryId = null): array
    {
        $exclude = $categoryId ? array_flip(self::branch($categoryId)) : [];

        return array_values(array_filter(self::options(), fn ($o) => ! isset($exclude[$o['id']])));
    }

    /** Forget every cached branch, after any category is added, moved, switched or removed. */
    public static function forget(): void
    {
        foreach (Category::pluck('id') as $id) {
            Cache::forget("cat_descendants_{$id}");
        }
        Cache::forget('nav_subcategories');
    }

    /** @return Collection<int, Collection<int, int>> parent id => child ids */
    private static function byParent(bool $activeOnly): Collection
    {
        return Category::query()
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->whereNotNull('parent_id')
            ->get(['id', 'parent_id'])
            ->groupBy(fn ($c) => (int) $c->parent_id)
            ->map(fn ($group) => $group->pluck('id')->map(fn ($id) => (int) $id));
    }
}
