<?php

namespace App\Console\Commands;

use App\Models\EmailTemplate;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\Review;
use App\Models\Setting;
use App\Services\TemplateMailService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SendReviewRequests extends Command
{
    protected $signature = 'review:send';
    protected $description = 'Send review request emails for completed orders';

    public function handle(): int
    {
        $enabled = Setting::where('group', 'reviews')->where('key', 'enabled')->value('value');
        if (!$enabled) {
            $this->info('Reviews are disabled in settings.');
            return self::SUCCESS;
        }

        $triggerStatusId = (int) Setting::where('group', 'reviews')->where('key', 'trigger_status_id')->value('value');
        $delayDays = (int) (Setting::where('group', 'reviews')->where('key', 'trigger_delay_days')->value('value') ?: 7);

        if (!$triggerStatusId) {
            $this->warn('No trigger status configured for review requests.');
            return self::SUCCESS;
        }

        $cutoffDate = now()->subDays($delayDays);
        $storeName = Setting::where('group', 'identity')->where('key', 'site_name')->value('value') ?: config('app.name');

        // Find orders that reached the trigger status at least X days ago
        $orderIds = OrderHistory::where('order_status_id', $triggerStatusId)
            ->where('created_at', '<=', $cutoffDate)
            ->pluck('order_id')
            ->unique();

        if ($orderIds->isEmpty()) {
            $this->info('No qualifying orders found.');
            return self::SUCCESS;
        }

        $query = Order::whereIn('id', $orderIds)
            ->with(['products.product.images', 'customer']);

        // Filter by customer groups if configured
        $allowedGroups = json_decode(Setting::where('group', 'reviews')->where('key', 'review_customer_groups')->value('value') ?? '[]', true);
        if (!empty($allowedGroups)) {
            $query->whereHas('customer', function ($q) use ($allowedGroups) {
                $q->whereIn('customer_group_id', $allowedGroups);
            });
        }

        // Determine which email template to use
        $templateId = Setting::where('group', 'reviews')->where('key', 'review_email_template_id')->value('value');
        $template = $templateId ? EmailTemplate::find($templateId) : EmailTemplate::where('slug', 'review_request')->first();
        $templateSlug = $template ? $template->slug : 'review_request';

        $orders = $query->get();

        $sentCount = 0;

        foreach ($orders as $order) {
            foreach ($order->products as $orderProduct) {
                if (!$orderProduct->product_id || !$orderProduct->product) {
                    continue;
                }

                // Skip if a review already exists for this order+product combination
                $exists = Review::where('order_id', $order->id)
                    ->where('product_id', $orderProduct->product_id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $token = Str::random(64);
                $product = $orderProduct->product;
                $customerName = $order->customer
                    ? $order->customer->first_name
                    : $order->first_name;
                $customerEmail = $order->customer
                    ? $order->customer->email
                    : $order->email;

                // Create pending review record
                Review::create([
                    'product_id' => $product->id,
                    'customer_id' => $order->customer_id,
                    'order_id' => $order->id,
                    'token' => $token,
                    'name' => $customerName ?? 'Customer',
                    'email' => $customerEmail,
                    'status' => 'pending',
                    'is_verified_purchase' => true,
                ]);

                // Get product image
                $productImage = $product->images->first();
                $productImageUrl = $productImage
                    ? asset('storage/' . $productImage->path)
                    : '';

                // Send review request email
                TemplateMailService::send($templateSlug, $customerEmail, [
                    'customer_name' => $customerName ?? 'Customer',
                    'product_name' => $product->name,
                    'product_image' => $productImageUrl,
                    'review_url' => route('review.submit', $token),
                    'store_name' => $storeName,
                ]);

                $sentCount++;
            }
        }

        $this->info("Sent {$sentCount} review request(s).");
        return self::SUCCESS;
    }
}
