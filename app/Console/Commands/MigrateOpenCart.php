<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MigrateOpenCart extends Command
{
    protected $signature = 'migrate:opencart {--dry-run : Show counts without importing}';
    protected $description = 'Migrate brands, categories, and products from OpenCart to Laravel';

    private string $prefix = '0012_';
    private int $storeId = 1;
    private \PDO $oc;

    // ID remapping: OC ID => Laravel ID
    private array $brandMap = [];
    private array $categoryMap = [];
    private array $productMap = [];

    public function handle(): int
    {
        $this->oc = new \PDO(
            'mysql:host=localhost;dbname=ngdadm_db;charset=utf8mb4',
            'ngdadm_user',
            '9Vp0jE0CJR1K',
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );

        $brandCount = $this->ocCount('manufacturer');
        $categoryCount = $this->ocCount('category');
        $productCount = $this->ocCount('product');

        $this->info("OpenCart data found:");
        $this->info("  Brands:     {$brandCount}");
        $this->info("  Categories: {$categoryCount}");
        $this->info("  Products:   {$productCount}");

        if ($this->option('dry-run')) {
            $this->warn('Dry run — no data imported.');
            return 0;
        }

        if (!$this->confirm('This will CLEAR all existing brands, categories, and products. Continue?')) {
            return 0;
        }

        try {
            $this->clearExistingData();
            $this->migrateBrands();
            $this->migrateCategories();
            $this->migrateProducts();
            $this->migrateProductCategories();

            $this->newLine();
            $this->info('Migration complete!');
            $this->table(['Entity', 'Imported'], [
                ['Brands', count($this->brandMap)],
                ['Categories', count($this->categoryMap)],
                ['Products', count($this->productMap)],
            ]);

            return 0;
        } catch (\Throwable $e) {
            $this->error("Migration failed: {$e->getMessage()}");
            $this->error($e->getTraceAsString());
            return 1;
        }
    }

    private function clearExistingData(): void
    {
        $this->warn('Clearing existing data...');

        // Disable FK checks for truncation
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('category_product')->truncate();
        DB::table('product_specials')->truncate();
        DB::table('product_images')->truncate();
        DB::table('product_variant_values')->truncate();
        DB::table('product_variants')->truncate();
        DB::table('variant_type_values')->truncate();
        DB::table('variant_types')->truncate();
        DB::table('products')->truncate();
        DB::table('categories')->truncate();
        DB::table('brands')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->info('  Cleared brands, categories, products, and related tables.');
    }

    private function migrateBrands(): void
    {
        $this->info('Migrating brands...');

        $rows = $this->ocQuery("
            SELECT m.manufacturer_id, m.name, m.image, m.sort_order
            FROM {$this->prefix}manufacturer m
            ORDER BY m.sort_order, m.name
        ");

        $usedSlugs = [];

        foreach ($rows as $row) {
            $slug = $this->uniqueSlug($row['name'], $usedSlugs);
            $usedSlugs[] = $slug;

            $id = DB::table('brands')->insertGetId([
                'store_id' => $this->storeId,
                'name' => $row['name'],
                'slug' => $slug,
                'image' => null, // Images migrated separately
                'sort_order' => (int) $row['sort_order'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->brandMap[(int) $row['manufacturer_id']] = $id;
        }

        $this->info("  Imported " . count($this->brandMap) . " brands.");
    }

    private function migrateCategories(): void
    {
        $this->info('Migrating categories...');

        $rows = $this->ocQuery("
            SELECT c.category_id, cd.name, cd.description, cd.meta_title,
                   cd.meta_description, cd.meta_keyword, cd.seo_keyword,
                   c.parent_id, c.image, c.sort_order, c.status,
                   c.date_added, c.date_modified
            FROM {$this->prefix}category c
            JOIN {$this->prefix}category_description cd
                ON c.category_id = cd.category_id AND cd.language_id = 1
            ORDER BY c.parent_id, c.sort_order, cd.name
        ");

        $usedSlugs = [];

        // First pass: insert all categories with parent_id = NULL
        foreach ($rows as $row) {
            $slug = $this->uniqueSlug(
                !empty($row['seo_keyword']) ? $row['seo_keyword'] : $row['name'],
                $usedSlugs
            );
            $usedSlugs[] = $slug;

            $description = trim($row['description'] ?? '');
            // Strip empty HTML tags from OpenCart descriptions
            $description = $this->cleanHtml($description);

            $id = DB::table('categories')->insertGetId([
                'store_id' => $this->storeId,
                'parent_id' => null, // Set in second pass
                'name' => $row['name'],
                'slug' => $slug,
                'description' => $description ?: null,
                'image' => null, // Images migrated separately
                'sort_order' => (int) $row['sort_order'],
                'is_active' => (bool) $row['status'],
                'meta_title' => $row['meta_title'] ?: null,
                'meta_description' => $row['meta_description'] ?: null,
                'meta_keywords' => $row['meta_keyword'] ?: null,
                'legacy_oc_id' => (int) $row['category_id'],
                'created_at' => $this->sanitizeDatetime($row['date_added']),
                'updated_at' => $this->sanitizeDatetime($row['date_modified']),
            ]);

            $this->categoryMap[(int) $row['category_id']] = $id;
        }

        // Second pass: set parent_id using the map
        foreach ($rows as $row) {
            $ocParentId = (int) $row['parent_id'];
            if ($ocParentId > 0 && isset($this->categoryMap[$ocParentId])) {
                $newId = $this->categoryMap[(int) $row['category_id']];
                DB::table('categories')
                    ->where('id', $newId)
                    ->update(['parent_id' => $this->categoryMap[$ocParentId]]);
            }
        }

        $this->info("  Imported " . count($this->categoryMap) . " categories.");
    }

    private function migrateProducts(): void
    {
        $this->info('Migrating products...');

        $rows = $this->ocQuery("
            SELECT p.product_id, pd.name, pd.description, pd.meta_title,
                   pd.meta_description, pd.meta_keyword, pd.seo_keyword,
                   p.model, p.sku, p.price, p.cost, p.quantity,
                   p.weight, p.weight_class_id,
                   p.length, p.width, p.height,
                   p.manufacturer_id, p.status, p.date_available,
                   p.sort_order, p.minimum, p.subtract, p.viewed,
                   p.date_added, p.date_modified
            FROM {$this->prefix}product p
            JOIN {$this->prefix}product_description pd
                ON p.product_id = pd.product_id AND pd.language_id = 1
            ORDER BY p.product_id
        ");

        $usedSlugs = [];
        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        foreach ($rows as $row) {
            $slug = $this->uniqueSlug(
                !empty($row['seo_keyword']) ? $row['seo_keyword'] : $row['name'],
                $usedSlugs
            );
            $usedSlugs[] = $slug;

            $brandId = null;
            $ocManufacturerId = (int) $row['manufacturer_id'];
            if ($ocManufacturerId > 0 && isset($this->brandMap[$ocManufacturerId])) {
                $brandId = $this->brandMap[$ocManufacturerId];
            }

            // Convert weight to kg if needed (weight_class_id: 1=kg, 2=g, 3=lb, 4=oz)
            $weight = (float) $row['weight'];
            $weightClassId = (int) $row['weight_class_id'];
            $weight = match ($weightClassId) {
                2 => round($weight / 1000, 3),    // grams to kg
                3 => round($weight * 0.453592, 3), // lbs to kg
                4 => round($weight * 0.0283495, 3),// oz to kg
                default => round($weight, 3),       // already kg or unknown
            };

            $dateAvailable = $this->sanitizeDate($row['date_available']);
            $createdAt = $this->sanitizeDatetime($row['date_added']);
            $updatedAt = $this->sanitizeDatetime($row['date_modified']);

            $description = trim($row['description'] ?? '');
            $description = $this->cleanHtml($description);

            $id = DB::table('products')->insertGetId([
                'store_id' => $this->storeId,
                'name' => $row['name'],
                'slug' => $slug,
                'sku' => $row['sku'] ?: null,
                'brand_id' => $brandId,
                'description' => $description ?: null,
                'price' => round((float) $row['price'], 2),
                'cost' => round((float) $row['cost'], 2),
                'quantity' => (int) $row['quantity'],
                'weight' => $weight,
                'weight_unit' => 'kg',
                'length' => round((float) $row['length'], 2),
                'width' => round((float) $row['width'], 2),
                'height' => round((float) $row['height'], 2),
                'has_variants' => false,
                'image' => null, // Images migrated separately
                'is_active' => (bool) $row['status'],
                'date_available' => $dateAvailable,
                'sort_order' => (int) $row['sort_order'],
                'minimum_quantity' => max(1, (int) $row['minimum']),
                'track_stock' => (bool) $row['subtract'],
                'meta_title' => $row['meta_title'] ?: null,
                'meta_description' => $row['meta_description'] ?: null,
                'meta_keywords' => $row['meta_keyword'] ?: null,
                'legacy_oc_id' => (int) $row['product_id'],
                'views' => (int) $row['viewed'],
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ]);

            $this->productMap[(int) $row['product_id']] = $id;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("  Imported " . count($this->productMap) . " products.");
    }

    private function migrateProductCategories(): void
    {
        $this->info('Migrating product-category relationships...');

        $rows = $this->ocQuery("
            SELECT product_id, category_id
            FROM {$this->prefix}product_to_category
        ");

        $inserted = 0;

        foreach ($rows as $row) {
            $productId = $this->productMap[(int) $row['product_id']] ?? null;
            $categoryId = $this->categoryMap[(int) $row['category_id']] ?? null;

            if ($productId && $categoryId) {
                DB::table('category_product')->insert([
                    'product_id' => $productId,
                    'category_id' => $categoryId,
                ]);
                $inserted++;
            }
        }

        $this->info("  Imported {$inserted} product-category links.");
    }

    // --- Helpers ---

    private function ocQuery(string $sql): array
    {
        return $this->oc->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function ocCount(string $table): int
    {
        $result = $this->oc->query("SELECT COUNT(*) as c FROM {$this->prefix}{$table}");
        return (int) $result->fetch(\PDO::FETCH_ASSOC)['c'];
    }

    private function decodeEntities(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        return html_entity_decode($value, ENT_QUOTES, 'UTF-8');
    }

    private function uniqueSlug(string $source, array &$existing): string
    {
        $slug = Str::slug($source);
        if (empty($slug)) {
            $slug = 'item-' . count($existing);
        }

        $original = $slug;
        $counter = 1;
        while (in_array($slug, $existing, true)) {
            $slug = "{$original}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function sanitizeDate(?string $date): ?string
    {
        if (!$date || str_starts_with($date, '0000')) {
            return null;
        }
        return $date;
    }

    private function sanitizeDatetime(?string $datetime): string
    {
        if (!$datetime || str_starts_with($datetime, '0000')) {
            return now()->toDateTimeString();
        }
        return $datetime;
    }

    private function cleanHtml(string $html): string
    {
        // Remove empty paragraphs and nbsp
        $html = preg_replace('/<p>\s*(&nbsp;)?\s*<\/p>/i', '', $html);
        $html = preg_replace('/^\s*<br\s*\/?>\s*/i', '', $html);
        return trim($html);
    }
}
