<?php

namespace Themes\Xelga\Http\Pickers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Support\NameSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Themes\Xelga\Settings\LinkValue;

/**
 * The searches behind the editor's pickers. Read only, through core's
 * models, and each answer carries only what a picker row shows: id, name,
 * a thumbnail, a price as the store writes it, and whether the record is
 * switched on.
 *
 * The editor runs in the admin, where core's customer group visibility does
 * not narrow products, so a merchant can choose a product kept for one
 * group; the storefront then shows it only to that group. Prices here are
 * the catalog price, for recognising a product, not what a shopper pays.
 */
final class RecordSearch
{
    public const LIMIT = 20;

    /** Kinds of record a link can point at, in the order a link search lists them. */
    private const LINK_KINDS = [
        'category' => 'categories',
        'product' => 'products',
        'brand' => 'brands',
        'page' => 'pages',
        'blog_post' => 'blog-posts',
    ];

    /** Rows of each kind a link search shows, so one kind cannot crowd out the rest. */
    private const LINK_SHARE = 6;

    /**
     * @param  list<int>|null  $ids  names for these ids instead of a search
     * @return array{results: list<array>, more: bool}
     */
    public function search(string $kind, string $q, ?array $ids, int $limit = self::LIMIT): array
    {
        return match ($kind) {
            'products' => $this->products($q, $ids, $limit),
            'categories' => $this->categories($q, $ids, $limit),
            'brands' => $this->simple(Brand::query(), ['id', 'name', 'image', 'is_active'], 'name', $q, $ids, $limit, fn (Brand $b) => [
                'id' => (int) $b->id, 'name' => (string) $b->name, 'thumbnail' => self::thumb($b->image), 'active' => (bool) $b->is_active,
            ]),
            'pages' => $this->simple(Page::query(), ['id', 'title', 'is_active'], 'title', $q, $ids, $limit, fn (Page $p) => [
                'id' => (int) $p->id, 'name' => (string) $p->title, 'thumbnail' => null, 'active' => (bool) $p->is_active,
            ]),
            'blog-posts' => $this->blogPosts($q, $ids, $limit),
            'blog-categories' => $this->blogCategories($q, $ids, $limit),
            default => throw new \InvalidArgumentException("Unknown picker kind {$kind}."),
        };
    }

    /** Whether the store has the blog, which the two blog pickers need. */
    public static function hasBlog(): bool
    {
        return class_exists(\Extensions\Blog\Models\BlogPost::class) && Schema::hasTable('blog_posts');
    }

    /**
     * One search across everything a link can point at, or the names of
     * links already chosen (each [type, id]).
     *
     * @param  list<array{0: string, 1: int}>|null  $typed
     * @return array{results: list<array>, more: bool}
     */
    public function links(string $q, ?array $typed): array
    {
        $kinds = array_filter(self::LINK_KINDS, fn ($kind, $type) => in_array($type, LinkValue::RECORD, true) && ($type !== 'blog_post' || self::hasBlog()), ARRAY_FILTER_USE_BOTH);

        $results = [];
        $more = false;
        foreach ($kinds as $type => $kind) {
            $ids = $typed === null ? null : array_values(array_map(fn ($pair) => $pair[1], array_filter($typed, fn ($pair) => $pair[0] === $type)));
            if ($ids === []) {
                continue;
            }
            $found = $this->search($kind, $q, $ids, $ids === null ? self::LINK_SHARE : count($ids));
            $more = $more || $found['more'];
            foreach ($found['results'] as $row) {
                $results[] = ['type' => $type] + $row;
            }
        }

        return ['results' => array_slice($results, 0, $typed === null ? self::LIMIT : PHP_INT_MAX), 'more' => $more || count($results) > self::LIMIT];
    }

    /** @return array{results: list<array>, more: bool} */
    private function products(string $q, ?array $ids, int $limit): array
    {
        $query = Product::query()->select(['id', 'name', 'image', 'price', 'is_active']);

        return $this->run($query, 'name', $q, $ids, $limit, fn (Product $p) => [
            'id' => (int) $p->id,
            'name' => (string) $p->name,
            'thumbnail' => self::thumb($p->image),
            'price' => store_money($p->price),
            'active' => (bool) $p->is_active,
        ], wholeFields: ['sku']);
    }

    /**
     * Categories carry the names of their parents, so two "Sale" categories
     * can be told apart, and are listed in tree order.
     *
     * @return array{results: list<array>, more: bool}
     */
    private function categories(string $q, ?array $ids, int $limit): array
    {
        $query = Category::query()->select(['id', 'parent_id', 'name', 'image', 'is_active']);
        if ($ids !== null) {
            $rows = $query->whereKey($ids)->get();
        } else {
            NameSearch::apply($query, $q, [], ['name']);
            // Enough rows to order a tree page by page without loading the whole catalog.
            $rows = $query->orderBy('name')->orderBy('id')->limit($q === '' ? 400 : $limit * 5)->get();
        }

        $paths = $this->categoryPaths($rows);
        $mapped = $rows->map(fn (Category $c) => [
            'id' => (int) $c->id,
            'name' => (string) $c->name,
            'path' => $paths[$c->id] ?? null,
            'thumbnail' => self::thumb($c->image),
            'active' => (bool) $c->is_active,
        ]);

        if ($ids !== null) {
            return ['results' => self::inOrder($mapped, $ids), 'more' => false];
        }

        $sorted = $mapped->sortBy(fn ($row) => mb_strtolower(($row['path'] !== null ? $row['path'] . ' / ' : '') . $row['name']))->values();

        return ['results' => $sorted->take($limit)->all(), 'more' => $sorted->count() > $limit];
    }

    /**
     * "Women / Dresses" for each category whose parents are known, read a
     * level at a time (a catalog is a few levels deep).
     *
     * @return array<int, string>
     */
    private function categoryPaths(Collection $rows): array
    {
        $names = [];
        $parents = [];
        foreach ($rows as $row) {
            $names[$row->id] = (string) $row->name;
            $parents[$row->id] = $row->parent_id ? (int) $row->parent_id : null;
        }

        for ($depth = 0; $depth < 8; $depth++) {
            $missing = array_values(array_diff(array_unique(array_filter($parents)), array_keys($names)));
            if ($missing === []) {
                break;
            }
            foreach (Category::query()->whereKey($missing)->get(['id', 'parent_id', 'name']) as $row) {
                $names[$row->id] = (string) $row->name;
                $parents[$row->id] = $row->parent_id ? (int) $row->parent_id : null;
            }
        }

        $paths = [];
        foreach ($rows as $row) {
            $trail = [];
            $seen = [];
            $parent = $parents[$row->id];
            while ($parent !== null && isset($names[$parent]) && ! isset($seen[$parent])) {
                $seen[$parent] = true;
                array_unshift($trail, $names[$parent]);
                $parent = $parents[$parent] ?? null;
            }
            if ($trail !== []) {
                $paths[$row->id] = implode(' / ', $trail);
            }
        }

        return $paths;
    }

    /** @return array{results: list<array>, more: bool} */
    private function blogPosts(string $q, ?array $ids, int $limit): array
    {
        if (! self::hasBlog()) {
            return ['results' => [], 'more' => false];
        }

        $query = \Extensions\Blog\Models\BlogPost::query()->select(['id', 'title', 'featured_image', 'is_published', 'published_at']);

        return $this->run($query, 'title', $q, $ids, $limit, fn (Model $post) => [
            'id' => (int) $post->id,
            'name' => (string) $post->title,
            'thumbnail' => self::thumb($post->featured_image),
            'active' => (bool) $post->is_published && $post->published_at !== null && $post->published_at <= now(),
        ]);
    }

    /** @return array{results: list<array>, more: bool} */
    private function blogCategories(string $q, ?array $ids, int $limit): array
    {
        if (! class_exists(\Extensions\Blog\Models\BlogCategory::class) || ! Schema::hasTable('blog_categories')) {
            return ['results' => [], 'more' => false];
        }

        return $this->simple(\Extensions\Blog\Models\BlogCategory::query(), ['id', 'name'], 'name', $q, $ids, $limit, fn (Model $c) => [
            'id' => (int) $c->id, 'name' => (string) $c->name, 'thumbnail' => null, 'active' => true,
        ]);
    }

    /** @return array{results: list<array>, more: bool} */
    private function simple(Builder $query, array $columns, string $nameColumn, string $q, ?array $ids, int $limit, callable $map): array
    {
        return $this->run($query->select($columns), $nameColumn, $q, $ids, $limit, $map);
    }

    /**
     * Names for given ids (kept in the order asked), or a search by name:
     * every word typed must appear in the name.
     *
     * @param  list<string>  $wholeFields  also matched against the whole search, such as a SKU
     * @return array{results: list<array>, more: bool}
     */
    private function run(Builder $query, string $nameColumn, string $q, ?array $ids, int $limit, callable $map, array $wholeFields = []): array
    {
        if ($ids !== null) {
            return ['results' => self::inOrder($query->whereKey($ids)->get()->map($map), $ids), 'more' => false];
        }

        NameSearch::apply($query, $q, $wholeFields, [$nameColumn]);
        $rows = $query->orderBy($nameColumn)->orderBy('id')->limit($limit + 1)->get();

        return ['results' => $rows->take($limit)->map($map)->values()->all(), 'more' => $rows->count() > $limit];
    }

    /** @param list<int> $ids */
    private static function inOrder(Collection $rows, array $ids): array
    {
        $byId = $rows->keyBy('id');

        return array_values(array_filter(array_map(fn (int $id) => $byId->get($id), $ids)));
    }

    private static function thumb(mixed $path): ?string
    {
        return is_string($path) && $path !== '' ? thumb_url($path) : null;
    }
}
