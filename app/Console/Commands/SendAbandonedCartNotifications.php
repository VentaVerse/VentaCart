<?php

namespace App\Console\Commands;

use App\Models\AbandonedCartNotification;
use App\Models\AbandonedCartRule;
use App\Models\Cart;
use App\Models\OrderProduct;
use App\Models\Setting;
use App\Services\TemplateMailService;
use Illuminate\Console\Command;

class SendAbandonedCartNotifications extends Command
{
    protected $signature = 'cart:notify';
    protected $description = 'Send abandoned cart notification emails based on configured rules';

    public function handle(): int
    {
        $enabled = Setting::where('group', 'abandoned_cart')
            ->where('key', 'enabled')
            ->value('value') === '1';

        if (!$enabled) {
            $this->info('Abandoned cart notifications are disabled.');
            return self::SUCCESS;
        }

        $rules = AbandonedCartRule::where('is_active', true)
            ->with('emailTemplate')
            ->orderByDesc('priority')
            ->get();

        if ($rules->isEmpty()) {
            $this->info('No active abandoned cart rules configured.');
            return self::SUCCESS;
        }

        $storeName = Setting::where('group', 'identity')
            ->where('key', 'site_name')
            ->value('value') ?: config('app.name');

        $cartUrl = rtrim(config('app.url'), '/') . '/cart';

        $sent = 0;
        $skippedMaxed = 0;
        $skippedRecovered = 0;

        $skippedOutOfStock = 0;
        $skipOutOfStock = Setting::where('group', 'abandoned_cart')
            ->where('key', 'skip_out_of_stock')
            ->value('value') === '1';

        // Get all abandoned carts for registered customers
        $carts = Cart::whereNotNull('customer_id')
            ->whereHas('items')
            ->with(['customer', 'items.product', 'items.productVariant'])
            ->get();

        foreach ($carts as $cart) {
            $customer = $cart->customer;
            if (!$customer || !$customer->email) {
                continue;
            }

            // Find the first matching rule by priority
            $matchedRule = null;
            foreach ($rules as $rule) {
                // Check cart age
                $ageHours = $cart->updated_at->diffInHours(now());
                if ($ageHours < $rule->min_cart_age_hours) {
                    continue;
                }

                // Check customer group
                if ($rule->customer_group_id !== null && $customer->customer_group_id !== $rule->customer_group_id) {
                    continue;
                }

                $matchedRule = $rule;
                break;
            }

            if (!$matchedRule) {
                continue;
            }

            // Check if already maxed out for this rule
            $notification = AbandonedCartNotification::where('cart_id', $cart->id)
                ->where('abandoned_cart_rule_id', $matchedRule->id)
                ->first();

            if ($notification && $notification->send_count >= $matchedRule->max_sends) {
                $skippedMaxed++;
                continue;
            }

            // Check if recovered — customer ordered any of the same products after cart was last updated
            $cartProductIds = $cart->items->pluck('product_id')->unique()->toArray();
            $recovered = OrderProduct::whereHas('order', function ($q) use ($customer, $cart) {
                $q->where('customer_id', $customer->id)
                  ->where('created_at', '>', $cart->updated_at);
            })->whereIn('product_id', $cartProductIds)->exists();

            if ($recovered) {
                $skippedRecovered++;
                continue;
            }

            // Skip if all cart items are out of stock
            if ($skipOutOfStock) {
                $hasInStock = $cart->items->contains(function ($item) {
                    if ($item->product_variant_id && $item->productVariant) {
                        return $item->productVariant->quantity > 0;
                    }
                    if ($item->product && $item->product->track_stock) {
                        return $item->product->quantity > 0;
                    }
                    return true;
                });

                if (!$hasInStock) {
                    $skippedOutOfStock++;
                    continue;
                }
            }

            // Build cart items HTML
            $cartItemsHtml = $cart->items->map(function ($item) {
                $label = $item->name;
                if ($item->variant_label) {
                    $label .= ' (' . $item->variant_label . ')';
                }
                return '<p style="margin: 4px 0; font-size: 14px; color: #e8e8e8;">'
                    . e($item->quantity) . 'x ' . e($label) . ': '
                    . number_format($item->price * $item->quantity, 2)
                    . '</p>';
            })->implode('');

            $cartTotal = number_format($cart->total, 2);

            // Send email
            $success = TemplateMailService::send($matchedRule->emailTemplate->slug, $customer->email, [
                'customer_name' => $customer->full_name ?: $customer->first_name,
                'cart_items' => $cartItemsHtml,
                'cart_total' => $cartTotal,
                'cart_url' => $cartUrl,
                'store_name' => $storeName,
            ]);

            if ($success) {
                AbandonedCartNotification::updateOrCreate(
                    [
                        'cart_id' => $cart->id,
                        'abandoned_cart_rule_id' => $matchedRule->id,
                    ],
                    [
                        'customer_id' => $customer->id,
                        'email_template_id' => $matchedRule->email_template_id,
                        'send_count' => ($notification ? $notification->send_count : 0) + 1,
                        'last_sent_at' => now(),
                    ]
                );
                $sent++;
            }
        }

        $this->info("Abandoned cart notifications: {$sent} sent, {$skippedMaxed} maxed out, {$skippedRecovered} recovered, {$skippedOutOfStock} out of stock.");
        return self::SUCCESS;
    }
}
