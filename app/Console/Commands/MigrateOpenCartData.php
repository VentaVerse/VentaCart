<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateOpenCartData extends Command
{
    protected $signature = 'oc:migrate {--customers} {--orders} {--reviews} {--all} {--dry-run}';
    protected $description = 'Migrate customers, orders, and reviews from OpenCart (0012_ tables) to Venta';

    private int $storeId;

    // OC country_id => ISO 2-letter code
    private array $countryMap = [
        168 => 'PH',
        223 => 'US',
        97 => 'HU',
        74 => 'FR',
        107 => 'JP',
        222 => 'GB',
        13 => 'AU',
        38 => 'CA',
        193 => 'SG',
        81 => 'DE',
    ];

    public function handle(): int
    {
        $this->storeId = (int) config('app.store_id', 1);
        $dryRun = $this->option('dry-run');
        $all = $this->option('all');

        if (!$all && !$this->option('customers') && !$this->option('orders') && !$this->option('reviews')) {
            $this->error('Specify --customers, --orders, --reviews, or --all');
            return 1;
        }

        if ($dryRun) {
            $this->warn('DRY RUN — no data will be written.');
        }

        // Verify OC tables exist
        try {
            DB::select('SELECT 1 FROM 0012_customer LIMIT 1');
        } catch (\Throwable) {
            $this->error('OpenCart tables (0012_*) not found in database. Import ngdadm_db.sql first.');
            return 1;
        }

        if ($all || $this->option('customers')) {
            $this->migrateCustomers($dryRun);
        }

        if ($all || $this->option('orders')) {
            $this->migrateOrders($dryRun);
        }

        if ($all || $this->option('reviews')) {
            $this->migrateReviews($dryRun);
        }

        $this->newLine();
        $this->info('Migration complete.');
        return 0;
    }

    private function migrateCustomers(bool $dryRun): void
    {
        $this->info('Migrating customers...');

        $ocCustomers = DB::table('0012_customer')
            ->where('status', 1)
            ->orderBy('customer_id')
            ->get();

        $existing = DB::table('customers')
            ->where('store_id', $this->storeId)
            ->pluck('legacy_oc_id')
            ->filter()
            ->flip()
            ->all();

        $inserted = 0;
        $skipped = 0;

        $bar = $this->output->createProgressBar($ocCustomers->count());

        foreach ($ocCustomers as $oc) {
            $bar->advance();

            if (isset($existing[$oc->customer_id])) {
                $skipped++;
                continue;
            }

            // Skip if email already exists in Venta (different OC ID but same email)
            $emailExists = DB::table('customers')
                ->where('store_id', $this->storeId)
                ->where('email', $oc->email)
                ->exists();

            if ($emailExists) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $inserted++;
                continue;
            }

            DB::table('customers')->insert([
                'store_id' => $this->storeId,
                'customer_group_id' => $oc->customer_group_id ?: 1,
                'first_name' => $oc->firstname,
                'last_name' => $oc->lastname,
                'email' => $oc->email,
                'phone' => $oc->telephone ?: null,
                'password' => null, // Force password reset
                'is_active' => true,
                'newsletter' => (bool) $oc->newsletter,
                'legacy_oc_id' => $oc->customer_id,
                'created_at' => $oc->date_added,
                'updated_at' => $oc->date_added,
            ]);

            $inserted++;
        }

        $bar->finish();
        $this->newLine();
        $this->info("  Customers: {$inserted} inserted, {$skipped} skipped");
    }

    private function migrateOrders(bool $dryRun): void
    {
        $this->info('Migrating orders...');

        // Build valid order status IDs
        $validStatusIds = DB::table('order_statuses')->pluck('id')->flip()->all();

        // Build product mapping: OC product_id => Venta product id
        $productMap = DB::table('products')
            ->whereNotNull('legacy_oc_id')
            ->pluck('id', 'legacy_oc_id')
            ->all();

        // Build customer mapping: OC customer_id => Venta customer id
        $customerMap = DB::table('customers')
            ->where('store_id', $this->storeId)
            ->whereNotNull('legacy_oc_id')
            ->pluck('id', 'legacy_oc_id')
            ->all();

        // Check what's already migrated (use order ID directly since OC order_id becomes Venta id)
        $existingOrderIds = DB::table('orders')
            ->pluck('id')
            ->flip()
            ->all();

        $ocOrders = DB::table('0012_order')
            ->orderBy('order_id')
            ->get();

        $ordersInserted = 0;
        $ordersSkipped = 0;
        $productsInserted = 0;
        $historyInserted = 0;

        $bar = $this->output->createProgressBar($ocOrders->count());

        foreach ($ocOrders as $oc) {
            $bar->advance();

            if (isset($existingOrderIds[$oc->order_id])) {
                $ordersSkipped++;
                continue;
            }

            $customerId = $customerMap[$oc->customer_id] ?? null;
            $countryCode = $this->resolveCountryCode($oc->shipping_country, $oc->shipping_country_id);
            $billingCountryCode = $this->resolveCountryCode($oc->payment_country, $oc->payment_country_id);

            // Get subtotal and shipping from order_total
            $totals = DB::table('0012_order_total')
                ->where('order_id', $oc->order_id)
                ->whereIn('code', ['sub_total', 'shipping'])
                ->pluck('value', 'code');

            $subtotal = (float) ($totals['sub_total'] ?? $oc->total);
            $shippingCost = (float) ($totals['shipping'] ?? 0);

            if ($dryRun) {
                $ordersInserted++;
                continue;
            }

            DB::table('orders')->insert([
                'id' => $oc->order_id,
                'store_id' => $this->storeId,
                'customer_id' => $customerId,
                'order_status_id' => isset($validStatusIds[$oc->order_status_id]) ? $oc->order_status_id : null,
                'first_name' => $oc->firstname,
                'last_name' => $oc->lastname,
                'email' => $oc->email,
                'phone' => $oc->telephone ?: null,
                'shipping_first_name' => $oc->shipping_firstname ?: null,
                'shipping_last_name' => $oc->shipping_lastname ?: null,
                'shipping_address_1' => trim(($oc->shipping_address_1 ?: '') . ($oc->shipping_address_2 ? ', ' . $oc->shipping_address_2 : '')) ?: null,
                'shipping_city' => $oc->shipping_city ?: null,
                'shipping_state' => $oc->shipping_zone ?: null,
                'shipping_postcode' => $oc->shipping_postcode ?: null,
                'shipping_country_code' => $countryCode,
                'billing_first_name' => $oc->payment_firstname ?: null,
                'billing_last_name' => $oc->payment_lastname ?: null,
                'billing_address_1' => trim(($oc->payment_address_1 ?: '') . ($oc->payment_address_2 ? ', ' . $oc->payment_address_2 : '')) ?: null,
                'billing_city' => $oc->payment_city ?: null,
                'billing_state' => $oc->payment_zone ?: null,
                'billing_postcode' => $oc->payment_postcode ?: null,
                'billing_country_code' => $billingCountryCode,
                'payment_method' => $this->mapPaymentMethod($oc->payment_code),
                'shipping_method' => $oc->shipping_method ?: null,
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'total' => (float) $oc->total,
                'comment' => $oc->comment ?: null,
                'stock_adjusted' => true, // Historical — don't re-adjust stock
                'created_at' => $oc->date_added,
                'updated_at' => $oc->date_modified ?: $oc->date_added,
            ]);

            $ordersInserted++;

            // Migrate order products
            $ocProducts = DB::table('0012_order_product')
                ->where('order_id', $oc->order_id)
                ->get();

            foreach ($ocProducts as $op) {
                $ventaProductId = $productMap[$op->product_id] ?? null;

                // Get variant label from order_option
                $variantLabel = DB::table('0012_order_option')
                    ->where('order_product_id', $op->order_product_id)
                    ->orderBy('order_option_id')
                    ->get()
                    ->map(fn($o) => $o->name . ': ' . $o->value)
                    ->implode(' / ') ?: null;

                DB::table('order_products')->insert([
                    'order_id' => $oc->order_id,
                    'product_id' => $ventaProductId,
                    'name' => $op->name,
                    'sku' => $op->model ?: null,
                    'variant_label' => $variantLabel,
                    'price' => (float) $op->price,
                    'quantity' => $op->quantity,
                    'total' => (float) $op->total,
                    'is_custom' => $ventaProductId === null,
                    'created_at' => $oc->date_added,
                    'updated_at' => $oc->date_added,
                ]);

                $productsInserted++;
            }

            // Migrate order history
            $ocHistory = DB::table('0012_order_history')
                ->where('order_id', $oc->order_id)
                ->orderBy('date_added')
                ->get();

            foreach ($ocHistory as $oh) {
                DB::table('order_histories')->insert([
                    'order_id' => $oc->order_id,
                    'order_status_id' => isset($validStatusIds[$oh->order_status_id]) ? $oh->order_status_id : null,
                    'comment' => $oh->comment ?: null,
                    'notify_customer' => (bool) $oh->notify,
                    'created_at' => $oh->date_added,
                    'updated_at' => $oh->date_added,
                ]);

                $historyInserted++;
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info("  Orders: {$ordersInserted} inserted, {$ordersSkipped} skipped");
        $this->info("  Order products: {$productsInserted} inserted");
        $this->info("  Order history: {$historyInserted} inserted");
    }

    private function resolveCountryCode(string $countryName, int $ocCountryId): ?string
    {
        if (isset($this->countryMap[$ocCountryId])) {
            return $this->countryMap[$ocCountryId];
        }

        if ($countryName) {
            $code = DB::table('countries')
                ->whereRaw('LOWER(name) = ?', [strtolower(trim($countryName))])
                ->value('iso2');

            if ($code) {
                $this->countryMap[$ocCountryId] = $code;
                return $code;
            }
        }

        return null;
    }

    private function mapPaymentMethod(string $ocCode): ?string
    {
        return match (true) {
            str_contains($ocCode, 'transfer') || str_contains($ocCode, 'bank') => 'bank_transfer',
            str_contains($ocCode, 'cod') => 'cod',
            str_contains($ocCode, 'hitpay') => 'hitpay',
            str_contains($ocCode, 'paypal') || str_contains($ocCode, 'pp_') => 'paypal',
            str_contains($ocCode, 'Shopee') => 'shopee',
            str_contains($ocCode, 'Lazada') => 'lazada',
            default => $ocCode ?: null,
        };
    }

    private function migrateReviews(bool $dryRun): void
    {
        $this->info('Migrating reviews...');

        $productMap = DB::table('products')
            ->whereNotNull('legacy_oc_id')
            ->pluck('id', 'legacy_oc_id')
            ->all();

        $customerMap = DB::table('customers')
            ->where('store_id', $this->storeId)
            ->whereNotNull('legacy_oc_id')
            ->pluck('id', 'legacy_oc_id')
            ->all();

        $existingCount = DB::table('reviews')->count();

        $ocReviews = DB::table('0012_review')
            ->where('store_id', 0)
            ->orderBy('review_id')
            ->get();

        $inserted = 0;
        $skipped = 0;

        $bar = $this->output->createProgressBar($ocReviews->count());

        foreach ($ocReviews as $oc) {
            $bar->advance();

            $ventaProductId = $productMap[$oc->product_id] ?? null;

            if (!$ventaProductId) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $inserted++;
                continue;
            }

            // Clean review text — strip Shopee/Lazada HTML comment tags
            $body = trim(preg_replace('/<!--\[.*?\]-->/', '', $oc->text));

            $statusMap = [1 => 'approved', 0 => 'pending'];

            $validDate = fn($d) => $d && $d !== '0000-00-00 00:00:00' ? $d : now();
            $createdAt = $validDate($oc->date_added);
            $updatedAt = $validDate($oc->date_modified) !== now() ? $validDate($oc->date_modified) : $createdAt;

            DB::table('reviews')->insert([
                'product_id' => $ventaProductId,
                'customer_id' => $customerMap[$oc->customer_id] ?? null,
                'token' => bin2hex(random_bytes(16)),
                'name' => $oc->author ?: 'Anonymous',
                'email' => '',
                'rating' => max(1, min(5, (int) $oc->rating)),
                'title' => $oc->title ?: null,
                'body' => $body ?: null,
                'status' => $statusMap[$oc->status] ?? 'pending',
                'is_verified_purchase' => (bool) $oc->verified_buyer,
                'published_at' => $oc->status == 1 ? $createdAt : null,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ]);

            $inserted++;
        }

        $bar->finish();
        $this->newLine();
        $this->info("  Reviews: {$inserted} inserted, {$skipped} skipped (unmapped products)");
    }
}
