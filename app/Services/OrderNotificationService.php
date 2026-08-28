<?php

namespace App\Services;

use App\Models\Order;

/**
 * The customer-facing order emails, and the ONE place the tracking
 * placeholders they may carry are resolved.
 *
 * Four code paths told the shopper about an order moving (the admin status
 * change, the admin tracking form, a recorded manual shipment, and a courier
 * webhook), and each built its own tracking data or none at all: the status
 * email never carried tracking from any of them, the courier webhook path
 * could not link the number it had, and the recorded-shipment path sent
 * nothing whatever the operator ticked. Every path now calls here.
 *
 * OrderStatusChanger deliberately never sends mail; callers send AFTER their
 * write has committed, through this class.
 */
class OrderNotificationService
{
    /**
     * Placeholders every customer order email may print. Empty strings when
     * the order carries no tracking number, so a template that prints them
     * prints nothing rather than a literal {placeholder}.
     *
     * tracking_number is the number already linked to its tracking page when
     * one is known, which is how the shipping email has always rendered it;
     * tracking_details is a whole block a merchant can drop into any
     * template without composing it, in the same way {comment} and
     * {payment_details} already travel as blocks.
     *
     * @return array{carrier_name: string, tracking_number: string, tracking_url: string, tracking_details: string}
     */
    public static function trackingPlaceholders(Order $order): array
    {
        $number = trim((string) $order->tracking_number);

        if ($number === '') {
            return ['carrier_name' => '', 'tracking_number' => '', 'tracking_url' => '', 'tracking_details' => ''];
        }

        // Stored snapshot first, the live courier's template second: the
        // accessor already walks that order. An integrated booking has no
        // link until the courier reports one, and that is an honest blank.
        $url = trim((string) ($order->tracking_link ?? ''));

        // The order's own snapshot of who carries it (manual couriers and,
        // since bookings stamp it, integrated ones too), then the live row
        // for orders that predate the snapshot. The checkout method's label
        // is the last resort because a store whose shipping methods ARE its
        // couriers (LBC Express as a rate) still names the right company.
        $carrier = (string) ($order->shipping_courier_name
            ?: ($order->shippingCourier?->name
            ?: ($order->courier_label ?: 'Courier')));

        $linked = $url !== ''
            ? '<a href="' . e($url) . '" style="color: #F5A623; text-decoration: underline;">' . e($number) . '</a>'
            : e($number);

        $details = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 20px 0;">'
            . '<tr><td style="padding: 16px; background-color: #222222; border-radius: 6px;">'
            . '<p style="margin: 0 0 4px 0; font-size: 12px; color: #999999; text-transform: uppercase; letter-spacing: 1px;">Tracking</p>'
            . '<p style="margin: 0 0 4px 0; font-size: 14px; color: #e8e8e8;"><strong>Courier:</strong> ' . e($carrier) . '</p>'
            . '<p style="margin: 0; font-size: 14px; color: #e8e8e8;"><strong>Tracking number:</strong> <span style="color: #F5A623;">' . $linked . '</span></p>'
            . '</td></tr></table>';

        return [
            'carrier_name'     => $carrier,
            'tracking_number'  => $linked,
            'tracking_url'     => $url,
            'tracking_details' => $details,
        ];
    }

    /**
     * "Your order moved from X to Y." Carries the tracking placeholders, so a
     * status that means the parcel is on its way can show where it is.
     */
    public static function statusChanged(Order $order, ?string $oldStatus, ?string $newStatus, ?string $comment = null): bool
    {
        if (! $order->email) {
            return false;
        }

        return TemplateMailService::send('order_status_update', $order->email, self::base($order) + [
            'old_status' => $oldStatus ?: 'Unknown',
            'new_status' => $newStatus ?: 'Unknown',
            'comment'    => (string) $comment,
        ] + self::trackingPlaceholders($order));
    }

    /**
     * "Your order has shipped." Only meaningful with a tracking number, so
     * without one nothing is sent and the caller is told so.
     */
    public static function shipped(Order $order): bool
    {
        if (! $order->email || trim((string) $order->tracking_number) === '') {
            return false;
        }

        return TemplateMailService::send('shipping_notification', $order->email, self::base($order) + self::trackingPlaceholders($order));
    }

    /** @return array{customer_name: string, order_id: int, store_name: string} */
    private static function base(Order $order): array
    {
        return [
            'customer_name' => trim($order->first_name . ' ' . $order->last_name),
            'order_id'      => $order->id,
            'store_name'    => (string) config('app.name'),
        ];
    }
}
