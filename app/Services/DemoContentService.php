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
