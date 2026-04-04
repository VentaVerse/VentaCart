<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\OrderProduct;
use App\Models\OrderStatus;
use App\Services\PaymentMethods\BankTransferPayment;
use App\Services\PaymentMethods\CodPayment;
use App\Services\PaymentMethods\HitPayPayment;
use App\Services\PaymentMethods\PaymentMethodInterface;
use App\Services\PaymentMethods\PaymentResult;
use App\Services\ActivityLogger;
use App\Services\PaymentRestrictionService;
use App\Mail\OrderConfirmationMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CheckoutService
{
    private int $storeId;

    public function __construct()
    {
        $this->storeId = (int) config('app.store_id', 1);
    }

    /**
     * Get available payment methods, optionally filtered by restrictions.
     *
     * @param array|null $address          Shipping address ['state'=>..., 'city'=>..., 'country_id'=>...]
     * @param float|null $subtotal         Order subtotal
     * @param array      $restrictionContext  Extra context: weight, quantity, customer_group_id, category_ids, brand_ids, shipping_method_key
     */
    public function getAvailablePaymentMethods(?array $address = null, ?float $subtotal = null, array $restrictionContext = []): array
    {
        $methods = [];

        // Bank Transfer: may expand to multiple entries
        $bt = new BankTransferPayment();
        if ($bt->isEnabled()) {
            $bankOptions = BankTransferPayment::getPaymentOptions();
            foreach ($bankOptions as $option) {
                $methods[] = $option;
            }
        }

        // HitPay
        $hitpay = new HitPayPayment();
        if ($hitpay->isEnabled()) {
            $methods[] = $hitpay;
        }

        // COD
        $cod = new CodPayment();
        if ($cod->isEnabled()) {
            $methods[] = $cod;
        }

        // Apply payment restrictions if address and subtotal are provided
        if ($address !== null && $subtotal !== null) {
            $methods = array_values(array_filter($methods, function (PaymentMethodInterface $method) use ($address, $subtotal, $restrictionContext) {
                return PaymentRestrictionService::isAllowed(
                    $method->getKey(),
                    $address,
                    $subtotal,
                    $restrictionContext['weight'] ?? null,
                    $restrictionContext['quantity'] ?? null,
                    $restrictionContext['customer_group_id'] ?? null,
                    $restrictionContext['category_ids'] ?? [],
                    $restrictionContext['brand_ids'] ?? [],
                    $restrictionContext['shipping_method_key'] ?? null
                );
            }));
        }

        return $methods;
    }

    public function getPaymentMethod(string $key): ?PaymentMethodInterface
    {
        foreach ($this->getAvailablePaymentMethods() as $method) {
            if ($method->getKey() === $key) {
                return $method;
            }
        }
        return null;
    }

    public function placeOrder(
        Customer $customer,
        CustomerAddress $shippingAddress,
        ?array $billingAddress,
        string $paymentMethodKey,
        string $shippingMethodKey,
        Cart $cart,
        ?string $comment = null,
        ?string $shippingMethodName = null,
        ?float $shippingCostOverride = null
    ): array {
        $paymentMethod = $this->getPaymentMethod($paymentMethodKey);
        if (!$paymentMethod) {
            throw new \InvalidArgumentException('Invalid payment method.');
        }

        $items = $cart->items()->with(['product', 'productVariant'])->get();
        if ($items->isEmpty()) {
            throw new \InvalidArgumentException('Cart is empty.');
        }

        // Block checkout if cart contains out-of-stock items
        $blockOutOfStock = \App\Models\Setting::where('group', 'checkout')
            ->where('key', 'block_out_of_stock')
            ->value('value') ?? '1';

        if ($blockOutOfStock === '1') {
            $outOfStock = [];
            foreach ($items as $item) {
                $product = $item->product;
                if (!$product || !$product->track_stock) {
                    continue;
                }

                if ($item->productVariant) {
                    if ($item->productVariant->quantity < $item->quantity) {
                        $label = $item->name . ($item->variant_label ? ' — ' . $item->variant_label : '');
                        $outOfStock[] = $label;
                    }
                } elseif ($product->quantity < $item->quantity) {
                    $outOfStock[] = $item->name;
                }
            }

            if (!empty($outOfStock)) {
                throw new \InvalidArgumentException(
                    'The following items are out of stock or have insufficient quantity: ' . implode(', ', $outOfStock) . '. Please update your cart before checkout.'
                );
            }
        }

        $subtotal = $items->sum(function ($item) {
            return $item->price * $item->quantity;
        });

        // Verify payment restrictions
        $addressData = [
            'state' => $shippingAddress->state,
            'city' => $shippingAddress->city,
            'country_id' => $shippingAddress->country_id ?? null,
        ];
        $totalWeight = $items->sum(function ($item) {
            return ($item->product?->weight ?? 0) * $item->quantity;
        });
        $totalQuantity = $items->sum('quantity');
        $cartCategoryIds = $items->pluck('product.category_id')->filter()->unique()->values()->all();
        $cartBrandIds = $items->pluck('product.brand_id')->filter()->unique()->values()->all();
        $customerGroupId = $customer->customer_group_id;

        if (!PaymentRestrictionService::isAllowed(
            $paymentMethodKey, $addressData, $subtotal,
            $totalWeight, $totalQuantity, $customerGroupId,
            $cartCategoryIds, $cartBrandIds, $shippingMethodKey
        )) {
            throw new \InvalidArgumentException('Selected payment method is not available for this order.');
        }

        // Verify shipping cost by re-fetching from providers
        $quoteService = app(\App\Services\Shipping\ShippingQuoteService::class);
        $liveQuotes = $quoteService->getQuotes([
            'province' => $shippingAddress->state,
            'city' => $shippingAddress->city,
            'barangay' => $shippingAddress->barangay,
            'country_id' => $shippingAddress->country_id ?? null,
        ], $items, $subtotal);

        $matchedQuote = collect($liveQuotes)->firstWhere('key', $shippingMethodKey);

        if ($matchedQuote) {
            // Use the verified cost from the provider
            $shippingCost = (float) $matchedQuote['cost'];
            $shippingMethod = $matchedQuote;
        } elseif ($shippingCostOverride !== null) {
            // Fallback: trust the override if no live quote matches (provider may be down)
            $shippingCost = $shippingCostOverride;
            $shippingMethod = ['key' => $shippingMethodKey, 'name' => $shippingMethodName ?? 'Shipping', 'cost' => $shippingCostOverride];
        } else {
            throw new \InvalidArgumentException('Selected shipping method is no longer available. Please go back and select a shipping option.');
        }
        $total = $subtotal + $shippingCost;

        // Build shipping meta from the matched provider
        $shippingMeta = null;
        if ($matchedQuote) {
            foreach ($quoteService->getProviders() as $provider) {
                if (str_starts_with($shippingMethodKey, $provider->getKey() . '_') || $shippingMethodKey === $provider->getKey()) {
                    $shippingMeta = $provider->buildShippingMeta($shippingMethodKey, $matchedQuote);
                    break;
                }
            }
        }

        // Resolve initial order status based on payment method
        $statusSettingKey = match (preg_replace('/_\d+$/', '', $paymentMethodKey)) {
            'bank_transfer' => 'bank_transfer_success_status_id',
            'cod' => 'cod_success_status_id',
            default => null,
        };

        $initialStatus = null;
        if ($statusSettingKey) {
            $statusId = \App\Models\Setting::where('group', 'payment')->where('key', $statusSettingKey)->value('value');
            if ($statusId) {
                $initialStatus = OrderStatus::find($statusId);
            }
        }

        $defaultStatus = $initialStatus ?? OrderStatus::where('store_id', $this->storeId)
            ->where('is_default', true)
            ->first();

        $order = DB::transaction(function () use (
            $customer, $shippingAddress, $billingAddress, $paymentMethodKey,
            $shippingMethod, $shippingMethodKey, $shippingMethodName, $shippingMeta, $items, $subtotal, $shippingCost, $total,
            $comment, $cart
        ) {
            $orderData = [
                'store_id' => $this->storeId,
                'customer_id' => $customer->id,
                'order_status_id' => null,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'shipping_first_name' => $shippingAddress->first_name,
                'shipping_last_name' => $shippingAddress->last_name,
                'shipping_address_1' => $shippingAddress->address_1,
                'shipping_city' => $shippingAddress->city,
                'shipping_district' => $shippingAddress->district,
                'shipping_barangay' => $shippingAddress->barangay,
                'shipping_state' => $shippingAddress->state,
                'shipping_postcode' => $shippingAddress->postcode,
                'shipping_country_id' => $shippingAddress->country_id,
                'shipping_phone' => $shippingAddress->phone,
                'payment_method' => $paymentMethodKey,
                'shipping_method' => $shippingMethodName ?? $shippingMethod['name'],
                'shipping_meta' => $shippingMeta,
                'dph_courier_name' => str_starts_with($shippingMethodKey, 'dph_') ? ($shippingMethodName ?? $shippingMethod['name']) : null,
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'total' => $total,
                'comment' => $comment,
            ];

            if ($billingAddress) {
                $orderData = array_merge($orderData, [
                    'billing_first_name' => $billingAddress['first_name'],
                    'billing_last_name' => $billingAddress['last_name'],
                    'billing_address_1' => $billingAddress['address_1'],
                    'billing_city' => $billingAddress['city'],
                    'billing_district' => $billingAddress['district'] ?? null,
                    'billing_barangay' => $billingAddress['barangay'] ?? null,
                    'billing_state' => $billingAddress['state'] ?? null,
                    'billing_postcode' => $billingAddress['postcode'] ?? null,
                    'billing_country_id' => $billingAddress['country_id'],
                ]);
            }

            $order = Order::create($orderData);

            foreach ($items as $item) {
                OrderProduct::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'variant_label' => $item->variant_label,
                    'price' => $item->price,
                    'quantity' => $item->quantity,
                    'total' => $item->price * $item->quantity,
                    'is_custom' => false,
                ]);
            }

            return $order;
        });

        $paymentResult = $paymentMethod->processPayment($order);

        // Failed API call (e.g. HitPay rejected) — return without clearing cart
        if (!$paymentResult->success) {
            return ['order' => $order, 'payment' => $paymentResult];
        }

        // Redirect-based payments (e.g. HitPay): webhook confirms and sets status
        if ($paymentResult->redirectUrl) {
            return ['order' => $order, 'payment' => $paymentResult];
        }

        // Immediate payments (e.g. bank transfer, COD): confirm order now
        $this->confirmOrder($order, $defaultStatus);
        $cart->items()->delete();

        return ['order' => $order, 'payment' => $paymentResult];
    }

    /**
     * Confirm an order — sets status, creates history, adjusts stock, sends emails.
     * Called when payment is confirmed (immediately for Bank/COD, via webhook for HitPay).
     */
    public function confirmOrder(Order $order, ?OrderStatus $status = null): void
    {
        $status = $status ?? OrderStatus::where('store_id', $order->store_id)
            ->where('is_default', true)
            ->first();

        if ($status) {
            $order->update(['order_status_id' => $status->id]);

            OrderHistory::create([
                'order_id' => $order->id,
                'order_status_id' => $status->id,
                'comment' => 'Order confirmed.',
            ]);

            StockService::adjustForStatusChange($order, $status);
        }

        $order->load('products', 'status');

        ActivityLogger::log(
            $order->customer_id,
            'order_placed',
            "Placed order #{$order->id} for " . number_format($order->total, 2),
            $order,
            ['total' => $order->total]
        );

        self::sendOrderConfirmationEmail($order);
        self::sendAdminOrderNotification($order);
    }

    public static function sendOrderConfirmationEmail(Order $order): void
    {
        $order->loadMissing('products.product');
        $currency = \App\Models\Setting::where('key', 'currency_symbol')->value('value') ?? '₱';

        $itemsHtml = self::buildOrderItemsHtml($order, $currency);
        $addressHtml = self::buildShippingAddressHtml($order);
        $shippingCostLabel = $order->shipping_cost > 0
            ? $currency . number_format($order->shipping_cost, 2)
            : '<span style="color: #4ade80;">FREE</span>';

        $sent = TemplateMailService::send('order_confirmation', $order->email, [
            'customer_name' => $order->first_name . ' ' . $order->last_name,
            'order_id' => $order->id,
            'subtotal' => $currency . number_format($order->subtotal, 2),
            'shipping_method' => $order->shipping_method ?: 'Standard',
            'shipping_cost' => $shippingCostLabel,
            'order_total' => $currency . number_format($order->total, 2),
            'order_items' => $itemsHtml,
            'shipping_address' => $addressHtml,
            'payment_method' => $order->payment_method_label,
            'payment_details' => self::buildPaymentDetailsHtml($order),
            'comment' => self::buildCommentHtml($order, 'Order Note'),
            'store_name' => config('app.name'),
        ]);

        // Fallback to Blade mailable if template is disabled or missing
        if (!$sent) {
            try {
                Mail::to($order->email)->send(new \App\Mail\OrderConfirmationMail($order));
            } catch (\Exception $e) {
                Log::warning('Order confirmation email failed: ' . $e->getMessage());
            }
        }
    }

    public static function sendAdminOrderNotification(Order $order): void
    {
        $adminEmail = \App\Models\Setting::where('group', 'mail')->where('key', 'from_address')->value('value');
        if (!$adminEmail) return;

        $order->loadMissing('products.product');
        $currency = \App\Models\Setting::where('key', 'currency_symbol')->value('value') ?? '₱';

        $itemsHtml = self::buildOrderItemsHtml($order, $currency);
        $addressHtml = self::buildShippingAddressHtml($order);
        $shippingCostLabel = $order->shipping_cost > 0
            ? $currency . number_format($order->shipping_cost, 2)
            : '<span style="color: #4ade80;">FREE</span>';

        TemplateMailService::send('new_order_admin', $adminEmail, [
            'order_id' => $order->id,
            'customer_name' => $order->first_name . ' ' . $order->last_name,
            'customer_email' => $order->email,
            'payment_method' => $order->payment_method_label,
            'subtotal' => $currency . number_format($order->subtotal, 2),
            'shipping_method' => $order->shipping_method ?: 'Standard',
            'shipping_cost' => $shippingCostLabel,
            'order_total' => $currency . number_format($order->total, 2),
            'order_items' => $itemsHtml,
            'shipping_address' => $addressHtml,
            'comment' => self::buildCommentHtml($order, 'Customer Note'),
        ]);
    }

    private static function buildPaymentDetailsHtml(Order $order): string
    {
        $label = $order->payment_method_label;
        $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 16px 0;">'
            . '<tr><td style="padding: 16px 20px; background-color: #222222; border-radius: 6px;">'
            . '<p style="margin: 0 0 6px 0; font-size: 11px; font-weight: 700; color: #999; text-transform: uppercase; letter-spacing: 1px;">Payment Method</p>'
            . '<p style="margin: 0; font-size: 14px; color: #e8e8e8;">' . e($label) . '</p>';

        if (str_starts_with($order->payment_method, 'bank_transfer')) {
            $bankAcct = null;
            if (preg_match('/^bank_transfer_(\d+)$/', $order->payment_method, $m)) {
                $bankAcct = \App\Models\BankAccount::find((int) $m[1]);
            }
            if ($bankAcct) {
                $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top: 12px; border-top: 1px solid #333;">'
                    . '<tr><td style="padding: 8px 0 2px 0; font-size: 13px; color: #999;">Bank:</td>'
                    . '<td style="padding: 8px 0 2px 0; font-size: 13px; color: #e8e8e8; text-align: right;">' . e($bankAcct->bank_name) . '</td></tr>'
                    . '<tr><td style="padding: 2px 0; font-size: 13px; color: #999;">Account Name:</td>'
                    . '<td style="padding: 2px 0; font-size: 13px; color: #e8e8e8; text-align: right;">' . e($bankAcct->account_name) . '</td></tr>'
                    . '<tr><td style="padding: 2px 0 8px 0; font-size: 13px; color: #999;">Account Number:</td>'
                    . '<td style="padding: 2px 0 8px 0; font-size: 13px; color: #e8e8e8; text-align: right;">' . e($bankAcct->account_number) . '</td></tr>'
                    . '<tr><td colspan="2" style="padding: 8px 0 0 0; font-size: 13px; color: #999; border-top: 1px solid #333;">Instructions:</td></tr>'
                    . '<tr><td colspan="2" style="padding: 4px 0 0 0; font-size: 13px; color: #e8e8e8; line-height: 1.5;">'
                    . ($bankAcct->instructions ? nl2br(e($bankAcct->instructions)) : '<span style="color: #666;">—</span>')
                    . '</td></tr></table>';

                if ($bankAcct->qr_image) {
                    $qrUrl = url('/storage/' . $bankAcct->qr_image);
                    $html .= '<div style="margin-top: 12px; text-align: center;">'
                        . '<img src="' . e($qrUrl) . '" alt="QR Code" width="180" height="180" style="width: 180px; height: 180px; border-radius: 6px; display: inline-block;">'
                        . '</div>';
                }
            }
        }

        $html .= '</td></tr></table>';
        return $html;
    }

    private static function buildCommentHtml(Order $order, string $heading = 'Order Note'): string
    {
        $noteText = $order->comment
            ? nl2br(e($order->comment))
            : '<span style="color: #666;">—</span>';

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 12px 0; background-color: #222222; border-radius: 6px;">'
            . '<tr><td style="padding: 16px 20px;">'
            . '<p style="margin: 0 0 6px 0; font-size: 11px; font-weight: 700; color: #999; text-transform: uppercase; letter-spacing: 1px;">' . e($heading) . '</p>'
            . '<p style="margin: 0; font-size: 13px; color: #e8e8e8; line-height: 1.5;">' . $noteText . '</p>'
            . '</td></tr></table>';
    }

    private static function buildOrderItemsHtml(Order $order, string $currency): string
    {
        $html = '';
        foreach ($order->products as $item) {
            $image = $item->product?->image;
            $imgHtml = '';
            if ($image) {
                $imgUrl = url('/storage/' . $image);
                $imgHtml = '<td style="width: 50px; padding: 4px 8px 4px 0; vertical-align: middle;">'
                    . '<img src="' . e($imgUrl) . '" alt="" width="50" height="50" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px; display: block;">'
                    . '</td>';
            }

            $textHtml = e($item->name)
                . ($item->variant_label ? ' — ' . e($item->variant_label) : '')
                . ' &times; ' . $item->quantity
                . ' = ' . $currency . number_format($item->total, 2);

            $html .= '<table cellpadding="0" cellspacing="0" border="0" style="margin: 2px 0; width: 100%;"><tr>'
                . $imgHtml
                . '<td style="font-size: 13px; color: #ccc; vertical-align: middle;">' . $textHtml . '</td>'
                . '</tr></table>';
        }
        return $html;
    }

    private static function buildShippingAddressHtml(Order $order): string
    {
        $lines = [];
        $lines[] = e($order->shipping_first_name . ' ' . $order->shipping_last_name);
        if ($order->shipping_address_1) {
            $lines[] = e($order->shipping_address_1);
        }
        // Extension fields (e.g. PH address) — rendered only if populated
        if ($order->shipping_barangay) {
            $lines[] = e($order->shipping_barangay);
        }
        $cityLine = '';
        if ($order->shipping_district) {
            $cityLine = e($order->shipping_district) . ', ';
        }
        $cityLine .= e($order->shipping_city ?? '');
        if ($cityLine) {
            $lines[] = $cityLine;
        }
        $stateLine = '';
        if ($order->shipping_state) {
            $stateLine .= e($order->shipping_state);
        }
        if ($order->shipping_postcode) {
            $stateLine .= ($stateLine ? ' ' : '') . e($order->shipping_postcode);
        }
        if ($stateLine) {
            $lines[] = $stateLine;
        }
        if ($order->shipping_country_id) {
            $country = \App\Models\Country::find($order->shipping_country_id);
            if ($country) {
                $lines[] = e($country->name);
            }
        }
        if ($order->shipping_phone) {
            $phoneCode = '';
            if ($order->shipping_country_id) {
                $countryForPhone = $country ?? \App\Models\Country::find($order->shipping_country_id);
                if ($countryForPhone && $countryForPhone->phone_code) {
                    $phoneCode = '+' . $countryForPhone->phone_code . ' ';
                }
            }
            $lines[] = 'Phone: ' . $phoneCode . e($order->shipping_phone);
        }
        return implode('<br>', $lines);
    }
}
