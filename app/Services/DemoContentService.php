<?php

namespace App\Services;

use App\Models\DemoContent;
use App\Models\Product;
use App\Models\ProductSpecial;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The products and posts a template brings so its arrangement has something
 * to show. A store being sold on a marketplace has to open on a shop, not on
 * six empty bands, and the merchant who already has a catalogue has to be
 * able to try every template without a single fake product appearing in it.
 *
 * Both follow from one rule: demo content installs only into a catalogue that
 * is entirely demo content already. The first real product a merchant adds
 * ends it permanently, and from then on a template changes the look alone.
 */
class DemoContentService
{
    /** @var list<callable(Product, array, string): void> */
    private static array $afterProduct = [];

    /**
     * Let a theme or extension add to each demo product as it is made (its
     * own extras from the product's entry in the template). The callback
     * receives the product, its definition and the template's slug.
     */
    public static function afterProduct(callable $callback): void
    {
        self::$afterProduct[] = $callback;
    }

    public static function forgetAfterProduct(): void
    {
        self::$afterProduct = [];
    }

    /**
     * Install a template's demo content, replacing the previous template's.
     *
     * @param  array{products?: array, posts?: array}  $preset
     */
    public function install(string $slug, array $preset): void
    {
        if ($this->merchantHasOwnCatalog()) {
            return;
        }

        DB::transaction(function () use ($slug, $preset) {
            $this->clear();

            foreach (array_values((array) ($preset['products'] ?? [])) as $i => $definition) {
                $this->makeProduct($slug, (array) $definition, $i);
            }

            foreach (array_values((array) ($preset['posts'] ?? [])) as $i => $definition) {
                $this->makePost($slug, (array) $definition, $i);
            }
        });
    }

    /**
     * Whether the store holds anything the merchant put there themselves.
     *
     * The global scope has to come off: it filters by the visiting customer's
     * group, and a product hidden from the current visitor is still very much
     * the merchant's.
     */
    public function merchantHasOwnCatalog(): bool
    {
        $demo = DemoContent::idsFor(DemoContent::PRODUCT);

        return Product::withoutGlobalScopes()
            ->when($demo !== [], fn ($q) => $q->whereNotIn('id', $demo))
            ->exists();
    }

    /**
     * Take away every record a template installed. A demo product that has
     * somehow reached an order is left alone and disowned instead: deleting it
     * would tear a hole in order history to tidy up a sample.
     */
    public function clear(): void
    {
        foreach (DemoContent::all() as $owned) {
            $kept = false;

            if ($owned->record_type === DemoContent::PRODUCT) {
                $product = Product::withoutGlobalScopes()->find($owned->record_id);

                if ($product) {
                    $kept = DB::table('order_products')->where('product_id', $product->id)->exists();

                    if (! $kept) {
                        $product->delete();
                    }
                }
            }

            if ($owned->record_type === DemoContent::POST && class_exists(\Extensions\Blog\Models\BlogPost::class)) {
                \Extensions\Blog\Models\BlogPost::query()->whereKey($owned->record_id)->delete();
            }

            if (in_array($owned->record_type, [DemoContent::CATEGORY, DemoContent::ATTRIBUTE], true)) {
                continue;
            }

            $owned->delete();
        }

        // Categories and attributes the demo made go once nothing of the
        // merchant's uses them; one the merchant has filled is disowned.
        foreach (DemoContent::where('record_type', DemoContent::CATEGORY)->get() as $owned) {
            $category = \App\Models\Category::find($owned->record_id);
            if ($category && ! $category->products()->exists() && ! \App\Models\Category::where('parent_id', $category->id)->exists()) {
                $category->delete();
            }
            $owned->delete();
        }
        foreach (DemoContent::where('record_type', DemoContent::ATTRIBUTE)->get() as $owned) {
            $attribute = \App\Models\Attribute::find($owned->record_id);
            if ($attribute && ! $attribute->values()->exists()) {
                $groupId = $attribute->attribute_group_id;
                $attribute->delete();
                \App\Models\AttributeGroup::whereKey($groupId)->whereDoesntHave('attributes')->delete();
            }
            $owned->delete();
        }
    }

    /**
     * @param  array{name?: string, price?: float|int|string, sale?: float|int|string, description?: string, image?: string, views?: int, quantity?: int}  $definition
     */
    private function makeProduct(string $slug, array $definition, int $index): void
    {
        $name = trim((string) ($definition['name'] ?? ''));

        if ($name === '') {
            return;
        }

        $product = Product::withoutGlobalScopes()->create([
            'store_id' => 1,
            'name' => $name,
            'slug' => $this->freeSlug(Str::slug($name), 'products'),
            'sku' => strtoupper(substr($slug, 0, 3)) . '-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
            'description' => (string) ($definition['description'] ?? ''),
            'price' => (float) ($definition['price'] ?? 0),
            'quantity' => (int) ($definition['quantity'] ?? 25),
            'image' => (string) ($definition['image'] ?? ''),
            'is_active' => true,
            'track_stock' => true,
            // Newest first in the order the template lists them, so a "just
            // landed" band and a "featured" band are not the same six cards
            // in the same order.
            'date_available' => now()->subDays($index),
            'views' => (int) ($definition['views'] ?? 0),
            'sort_order' => $index,
        ]);

        DemoContent::create([
            'preset' => $slug,
            'record_type' => DemoContent::PRODUCT,
            'record_id' => $product->id,
        ]);

        $this->addPictures($product, (array) ($definition['images'] ?? []));
        $this->addCategories($slug, $product, $definition['categories'] ?? []);
        $this->addAttributes($slug, $product, (array) ($definition['attributes'] ?? []));

        if (isset($definition['sale']) && (float) $definition['sale'] > 0) {
            ProductSpecial::create([
                'product_id' => $product->id,
                'customer_group_id' => null,
                'price' => (float) $definition['sale'],
                'quantity' => 1,
                'display_mode' => 'sale',
                'priority' => 1,
                'date_start' => null,
                'date_end' => null,
            ]);
        }

        foreach (self::$afterProduct as $callback) {
            $callback($product, $definition, $slug);
        }
    }

    /** More pictures, after the main one: storage paths only. */
    private function addPictures(Product $product, array $paths): void
    {
        $order = 1;
        foreach (array_slice($paths, 0, 8) as $path) {
            $path = is_string($path) ? trim($path) : '';
            if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/') || preg_match('#[:"\'<>\\\\]#', $path)) {
                continue;
            }
            $product->images()->create(['path' => mb_substr($path, 0, 255), 'sort_order' => $order++]);
        }
    }

    /** The categories a demo product sits in, by name; one the store lacks is made and remembered. */
    private function addCategories(string $slug, Product $product, mixed $names): void
    {
        $ids = [];
        foreach (array_slice((array) $names, 0, 5) as $name) {
            $name = is_string($name) ? mb_substr(trim($name), 0, 100) : '';
            if ($name === '') {
                continue;
            }
            $category = \App\Models\Category::where('slug', Str::slug($name))->first();
            if (! $category) {
                $category = \App\Models\Category::create(['store_id' => 1, 'name' => $name, 'slug' => $this->freeSlug(Str::slug($name), 'categories'), 'is_active' => true]);
                DemoContent::create(['preset' => $slug, 'record_type' => DemoContent::CATEGORY, 'record_id' => $category->id]);
            }
            $ids[] = $category->id;
        }
        if ($ids !== []) {
            $product->categories()->syncWithoutDetaching($ids);
        }
    }

    /**
     * Attribute values, as name => value or as {group, name, value, card,
     * filter}. An attribute the store lacks is made (in the group named, or
     * Details) and remembered; one it has keeps its own settings.
     */
    private function addAttributes(string $slug, Product $product, array $attributes): void
    {
        foreach (array_slice($attributes, 0, 20, true) as $key => $entry) {
            $spec = is_array($entry) ? $entry : ['name' => (string) $key, 'value' => $entry];
            $name = mb_substr(trim((string) ($spec['name'] ?? '')), 0, 100);
            $value = is_scalar($spec['value'] ?? null) ? mb_substr(trim((string) $spec['value']), 0, 255) : '';
            if ($name === '' || $value === '') {
                continue;
            }
            $attribute = \App\Models\Attribute::where('name', $name)->first();
            if (! $attribute) {
                $group = \App\Models\AttributeGroup::firstOrCreate(['name' => mb_substr(trim((string) ($spec['group'] ?? 'Details')) ?: 'Details', 0, 100)]);
                $attribute = \App\Models\Attribute::create([
                    'attribute_group_id' => $group->id, 'name' => $name,
                    'show_on_card' => ! empty($spec['card']), 'is_filterable' => ! empty($spec['filter']),
                ]);
                DemoContent::create(['preset' => $slug, 'record_type' => DemoContent::ATTRIBUTE, 'record_id' => $attribute->id]);
            }
            \App\Models\ProductAttribute::updateOrCreate(['product_id' => $product->id, 'attribute_id' => $attribute->id], ['value' => $value]);
        }
    }

    /**
     * @param  array{title?: string, excerpt?: string, content?: string, image?: string, category?: string}  $definition
     */
    private function makePost(string $slug, array $definition, int $index): void
    {
        if (! class_exists(\Extensions\Blog\Models\BlogPost::class)) {
            return;
        }

        $title = trim((string) ($definition['title'] ?? ''));

        if ($title === '') {
            return;
        }

        $categoryId = null;
        $category = trim((string) ($definition['category'] ?? ''));

        if ($category !== '' && class_exists(\Extensions\Blog\Models\BlogCategory::class)) {
            $categoryId = \Extensions\Blog\Models\BlogCategory::firstOrCreate(
                ['slug' => Str::slug($category)],
                ['name' => $category, 'sort_order' => 0]
            )->id;
        }

        $post = \Extensions\Blog\Models\BlogPost::create([
            'title' => $title,
            'slug' => $this->freeSlug(Str::slug($title), 'blog_posts'),
            'excerpt' => (string) ($definition['excerpt'] ?? ''),
            'content' => (string) ($definition['content'] ?? ''),
            'featured_image' => (string) ($definition['image'] ?? ''),
            'blog_category_id' => $categoryId,
            'is_published' => true,
            'published_at' => now()->subDays($index),
        ]);

        DemoContent::create([
            'preset' => $slug,
            'record_type' => DemoContent::POST,
            'record_id' => $post->id,
        ]);
    }

    /** A slug the table does not already hold. */
    private function freeSlug(string $slug, string $table): string
    {
        $slug = $slug !== '' ? $slug : 'item';
        $candidate = $slug;
        $n = 2;

        while (DB::table($table)->where('slug', $candidate)->exists()) {
            $candidate = $slug . '-' . $n++;
        }

        return $candidate;
    }
}
