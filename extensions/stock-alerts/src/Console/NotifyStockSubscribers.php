<?php

namespace Extensions\StockAlerts\Console;

use Extensions\StockAlerts\Mail\StockNotificationMail;
use Extensions\StockAlerts\Models\StockNotification;
use Extensions\StockAlerts\Models\StockNotificationTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotifyStockSubscribers extends Command
{
    protected $signature = 'stock:notify';
    protected $description = 'Send email notifications for products back in stock';

    public function handle(): int
    {
        $waiting = StockNotification::waiting()
            ->with(['product.images', 'variant'])
            ->get();

        if ($waiting->isEmpty()) {
            $this->info('No waiting subscribers.');
            return 0;
        }

        $template = StockNotificationTemplate::where('store_id', 1)->first();
        if (!$template) {
            $this->error('No email template found.');
            return 1;
        }

        $notified = 0;
        $failed = 0;

        $grouped = $waiting->groupBy(fn ($n) => $n->product_id . '_' . ($n->product_variant_id ?? 'base'));

        foreach ($grouped as $key => $subscribers) {
            $first = $subscribers->first();
            $product = $first->product;

            if (!$product || !$product->is_active) {
                continue;
            }

            $inStock = false;
            if ($first->product_variant_id) {
                $variant = $first->variant;
                $inStock = $variant && $variant->quantity >= 1;
            } else {
                $inStock = !$product->track_stock || $product->quantity >= 1;
            }

            if (!$inStock) {
                continue;
            }

            foreach ($subscribers as $notification) {
                try {
                    Mail::to($notification->email)->send(
                        new StockNotificationMail($notification, $template)
                    );
                    $notification->update([
                        'status' => 'notified',
                        'notified_at' => now(),
                    ]);
                    $notified++;
                } catch (\Throwable $e) {
                    $notification->update(['status' => 'failed']);
                    $failed++;
                    Log::error("StockNotify: failed to send to {$notification->email}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->info("Done. Notified: {$notified}, Failed: {$failed}");
        return 0;
    }
}
