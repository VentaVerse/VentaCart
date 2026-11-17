<?php

namespace Themes\Xelga\Settings;

use App\Models\Brand;
use App\Models\Category;
use App\Models\NavMenu;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Themes\Xelga\Catalog\Catalog;

/**
 * What the sanitizer checks values against: the store's color schemes, its
 * menus, the files on its public disk and the records pickers point at. The
 * linter uses a context that knows no store, so shipped defaults are checked
 * for shape alone.
 */
final class SanitizeContext
{
    /** The record each picker setting stores the id of. */
    public const PICKER_RECORDS = [
        'product-picker' => 'product',
        'category-picker' => 'category',
        'brand-picker' => 'brand',
        'page-picker' => 'page',
        'blog-post-picker' => 'blog_post',
    ];

    /** @var array<string, array<int, bool>> ids already looked up, per record */
    private array $known = [];

    /**
     * @param  list<string>  $schemeIds
     * @param  list<string>|null  $menuKeys  null accepts any well-formed key
     */
    public function __construct(
        public readonly array $schemeIds,
        public readonly ?array $menuKeys,
        public readonly bool $checkFiles,
    ) {
    }

    /** @param list<string> $schemeIds */
    public static function forStore(array $schemeIds): self
    {
        $menus = Schema::hasTable('nav_menus') ? NavMenu::query()->pluck('key')->map(fn ($k) => (string) $k)->all() : [];

        return new self($schemeIds, $menus, true);
    }

    /** @param list<string> $schemeIds */
    public static function forLint(array $schemeIds): self
    {
        return new self($schemeIds, null, false);
    }

    public function fileExists(string $path): bool
    {
        return ! $this->checkFiles || Storage::disk('public')->exists($path);
    }

    /**
     * The given ids that name an existing record, in the given order. Read
     * in the admin, where core shows every product, so a product reserved
     * for one customer group can still be chosen; the storefront then shows
     * it only to that group. Without a store (the linter) every id passes.
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    public function existing(string $record, array $ids): array
    {
        if (! $this->checkFiles || $ids === []) {
            return $ids;
        }

        $missing = array_values(array_filter($ids, fn (int $id) => ! isset($this->known[$record][$id])));
        if ($missing !== []) {
            $found = array_flip($this->lookup($record, $missing));
            foreach ($missing as $id) {
                $this->known[$record][$id] = isset($found[$id]);
            }
        }

        return array_values(array_filter($ids, fn (int $id) => $this->known[$record][$id]));
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function lookup(string $record, array $ids): array
    {
        $query = match ($record) {
            'product' => Product::query(),
            'category' => Category::query(),
            'brand' => Brand::query(),
            'page' => Page::query(),
            'blog_post' => class_exists(\Extensions\Blog\Models\BlogPost::class) && Schema::hasTable('blog_posts')
                ? \Extensions\Blog\Models\BlogPost::query()
                : null,
            default => null,
        };

        return $query === null ? [] : $query->whereKey($ids)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** @var list<string>|null */
    private ?array $blogCategories = null;

    /** The blog's category ids, as strings; none while the blog extension is absent. @return list<string> */
    private function blogCategoryIds(): array
    {
        if ($this->blogCategories !== null) {
            return $this->blogCategories;
        }
        if (! $this->checkFiles || ! class_exists(\Extensions\Blog\Models\BlogCategory::class) || ! Schema::hasTable('blog_categories')) {
            return $this->blogCategories = [];
        }

        return $this->blogCategories = \Extensions\Blog\Models\BlogCategory::query()->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    /** @return list<string> */
    public function options(string $source): array
    {
        return match ($source) {
            'font_pairs' => array_column(Catalog::fontPairs(), 'value'),
            'menus' => $this->menuKeys ?? [],
            'blog_categories' => $this->blogCategoryIds(),
            default => [],
        };
    }
}
