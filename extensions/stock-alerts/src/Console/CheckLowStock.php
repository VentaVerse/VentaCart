<?php

namespace Extensions\StockAlerts\Console;

use Extensions\StockAlerts\Models\LowStockAlert;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Services\TemplateMailService;
use App\Support\LowStock;
use Illuminate\Console\Command;

class CheckLowStock extends Command
{
    protected $signature = 'stock:check-low';
    protected $description = 'Check for low stock products and send admin alerts';

    public function handle(): int
    {
        $enabled = Setting::where('group', 'stock')->where('key', 'low_stock_alert_enabled')->value('value');
        if (!$enabled) {
            $this->info('Low stock alerts are disabled.');
            return 0;
        }

        $threshold = LowStock::threshold();
        $alertEmail = Setting::where('group', 'stock')->where('key', 'low_stock_alert_email')->value('value');
        if (!$alertEmail) {
            $alertEmail = \App\Models\User::first()?->email;
        }
        if (!$alertEmail) {
            $this->error('No alert email configured.');
            return 1;
        }

        $storeName = Setting::where('group', 'identity')->where('key', 'site_name')->value('value') ?? 'Store';
        $alertCount = 0;

        // Check products (non-variant). The predicate lives in App\Support\LowStock
        // so this command, the day sheet's work line and the catalog's Low stock
        // filter can never mean three different things by "low".
        $lowProducts = LowStock::products($threshold)->get();

        foreach ($lowProducts as $product) {
            $existing = LowStockAlert::where('product_id', $product->id)
                ->whereNull('product_variant_id')
                ->first();

            if ($existing && $product->quantity <= $existing->quantity_at_alert) {
                continue;
            }

            TemplateMailService::send('low_stock_admin', $alertEmail, [
                'product_name' => $product->name,
                'product_sku' => $product->sku ?? 'N/A',
                'current_qty' => $product->quantity,
                'threshold' => $threshold,
                'store_name' => $storeName,
            ]);

            LowStockAlert::updateOrCreate(
                ['product_id' => $product->id, 'product_variant_id' => null],
                ['quantity_at_alert' => $product->quantity, 'alerted_at' => now()]
            );

            $alertCount++;
        }

        // Check variants
        $lowVariants = LowStock::variants($threshold)->with('product')->get();

        foreach ($lowVariants as $variant) {
            $existing = LowStockAlert::where('product_id', $variant->product_id)
                ->where('product_variant_id', $variant->id)
                ->first();

            if ($existing && $variant->quantity <= $existing->quantity_at_alert) {
                continue;
            }

            TemplateMailService::send('low_stock_admin', $alertEmail, [
                'product_name' => $variant->product->name . ' (' . ($variant->name ?? $variant->sku) . ')',
                'product_sku' => $variant->sku ?? 'N/A',
                'current_qty' => $variant->quantity,
                'threshold' => $threshold,
                'store_name' => $storeName,
            ]);

            LowStockAlert::updateOrCreate(
                ['product_id' => $variant->product_id, 'product_variant_id' => $variant->id],
                ['quantity_at_alert' => $variant->quantity, 'alerted_at' => now()]
            );

            $alertCount++;
        }

        // Clean up alerts for products back above threshold (reset for future alerts)
        LowStockAlert::whereNull('product_variant_id')
            ->whereHas('product', function ($q) use ($threshold) {
                $q->where('quantity', '>', $threshold);
            })
            ->delete();

        LowStockAlert::whereNotNull('product_variant_id')
            ->get()
            ->each(function ($alert) use ($threshold) {
                $variant = ProductVariant::find($alert->product_variant_id);
                if ($variant && $variant->quantity > $threshold) {
                    $alert->delete();
                }
            });

        $this->info("Low stock check complete. Alerts sent: {$alertCount}");
        return 0;
    }
}
