<?php

namespace App\Console\Commands;

use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\ProductSpecial;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateOpenCartPricing extends Command
{
    protected $signature = 'migrate:opencart-pricing {--fresh : Clear existing data before import}';
    protected $description = 'Migrate customer groups, specials, and discounts from OpenCart to Laravel';

    private \PDO $oc;

    public function handle(): int
    {
        $this->oc = new \PDO(
            'mysql:host=localhost;dbname=ngdadm_db;charset=utf8mb4',
            'ngdadm_user',
            '9Vp0jE0CJR1K'
        );
        $this->oc->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        if ($this->option('fresh')) {
            $this->warn('Clearing existing customer groups and product specials...');
            ProductSpecial::truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            CustomerGroup::truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->migrateCustomerGroups();
        $groupMap = $this->buildGroupMap();
        $this->migrateSpecialsAndDiscounts($groupMap);

        $this->newLine();
        $this->info('Migration complete.');
        return 0;
    }

    private function migrateCustomerGroups(): void
    {
        $this->info('Migrating customer groups...');

        $rows = $this->oc->query("
            SELECT cg.customer_group_id, cgd.name, cgd.description, cg.approval, cg.sort_order
            FROM 0012_customer_group cg
            JOIN 0012_customer_group_description cgd
              ON cg.customer_group_id = cgd.customer_group_id AND cgd.language_id = 1
            ORDER BY cg.customer_group_id
        ")->fetchAll();

        foreach ($rows as $row) {
            $group = CustomerGroup::updateOrCreate(
                ['name' => $row['name']],
                [
                    'store_id' => 1,
                    'description' => $row['description'] ?: null,
                    'is_default' => $row['customer_group_id'] == 1,
                ]
            );
            $this->line("  [{$row['customer_group_id']}] {$row['name']} → Laravel ID {$group->id}");
        }

        $this->info("  " . count($rows) . " customer groups migrated.");
    }

    private function buildGroupMap(): array
    {
        // Map OC customer_group_id → Laravel customer_group_id by name
        $ocGroups = $this->oc->query("
            SELECT cg.customer_group_id, cgd.name
            FROM 0012_customer_group cg
            JOIN 0012_customer_group_description cgd
              ON cg.customer_group_id = cgd.customer_group_id AND cgd.language_id = 1
        ")->fetchAll();

        $map = [];
        foreach ($ocGroups as $og) {
            $laravel = CustomerGroup::where('name', $og['name'])->first();
            if ($laravel) {
                $map[(int) $og['customer_group_id']] = $laravel->id;
            }
        }

        $this->info("  Group mapping: " . collect($map)->map(fn ($v, $k) => "OC:{$k}→L:{$v}")->join(', '));
        return $map;
    }

    private function migrateSpecialsAndDiscounts(array $groupMap): void
    {
        $this->info('Migrating specials and discounts...');

        // Build OC product_id → Laravel product_id map
        $productMap = Product::whereNotNull('legacy_oc_id')
            ->pluck('id', 'legacy_oc_id')
            ->all();

        // Clear existing specials (if not already truncated)
        if (!$this->option('fresh')) {
            ProductSpecial::truncate();
            $this->warn('  Cleared existing specials.');
        }

        $inserted = 0;
        $skipped = 0;

        // --- Specials (no quantity, display as sale) ---
        $specials = $this->oc->query("
            SELECT product_id, customer_group_id, priority, price, date_start, date_end
            FROM 0012_product_special
            ORDER BY product_id, priority
        ")->fetchAll();

        foreach ($specials as $row) {
            $laravelProductId = $productMap[(int) $row['product_id']] ?? null;
            $laravelGroupId = $groupMap[(int) $row['customer_group_id']] ?? null;

            if (!$laravelProductId) {
                $skipped++;
                continue;
            }

            ProductSpecial::create([
                'product_id' => $laravelProductId,
                'customer_group_id' => $laravelGroupId,
                'price' => (float) $row['price'],
                'quantity' => 1,
                'display_mode' => 'sale',
                'priority' => (int) $row['priority'],
                'date_start' => $this->parseDate($row['date_start']),
                'date_end' => $this->parseDate($row['date_end']),
            ]);
            $inserted++;
        }

        $this->line("  Specials: {$inserted} imported, {$skipped} skipped (no product match)");

        // --- Discounts (quantity-based, display as override) ---
        $discountInserted = 0;
        $discountSkipped = 0;

        $discounts = $this->oc->query("
            SELECT product_id, customer_group_id, quantity, priority, price, date_start, date_end
            FROM 0012_product_discount
            ORDER BY product_id, customer_group_id, quantity
        ")->fetchAll();

        foreach ($discounts as $row) {
            $laravelProductId = $productMap[(int) $row['product_id']] ?? null;
            $laravelGroupId = $groupMap[(int) $row['customer_group_id']] ?? null;

            if (!$laravelProductId) {
                $discountSkipped++;
                continue;
            }

            $qty = max(1, (int) $row['quantity']);

            ProductSpecial::create([
                'product_id' => $laravelProductId,
                'customer_group_id' => $laravelGroupId,
                'price' => (float) $row['price'],
                'quantity' => $qty,
                'display_mode' => 'override',
                'priority' => (int) $row['priority'],
                'date_start' => $this->parseDate($row['date_start']),
                'date_end' => $this->parseDate($row['date_end']),
            ]);
            $discountInserted++;
        }

        $this->line("  Discounts: {$discountInserted} imported, {$discountSkipped} skipped (no product match)");
        $this->info("  Total pricing rules: " . ($inserted + $discountInserted));
    }

    private function parseDate(string $date): ?string
    {
        if (!$date || $date === '0000-00-00') {
            return null;
        }
        return $date;
    }
}
