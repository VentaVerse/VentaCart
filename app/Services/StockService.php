<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderStatus;

class StockService
{
    public static function adjustForStatusChange(Order $order, OrderStatus $newStatus): void
    {
        $action = $newStatus->stock_action;

        if (!$action) {
            return;
        }

        if ($action === 'decrement' && !$order->stock_adjusted) {
            self::decrementStock($order);
            $order->update(['stock_adjusted' => true]);
        } elseif ($action === 'increment' && $order->stock_adjusted) {
            self::incrementStock($order);
            $order->update(['stock_adjusted' => false]);
        }
    }

    private static function decrementStock(Order $order): void
    {
        $order->loadMissing('products');

        foreach ($order->products as $item) {
            if ($item->product_id && $item->product?->track_stock) {
                $item->product->decrement('quantity', $item->quantity);
            }
            if ($item->product_variant_id && $item->productVariant) {
                $item->productVariant->decrement('quantity', $item->quantity);
            }
        }
    }

    private static function incrementStock(Order $order): void
    {
        $order->loadMissing('products');

        foreach ($order->products as $item) {
            if ($item->product_id && $item->product?->track_stock) {
                $item->product->increment('quantity', $item->quantity);
            }
            if ($item->product_variant_id && $item->productVariant) {
                $item->productVariant->increment('quantity', $item->quantity);
            }
        }
    }
}
