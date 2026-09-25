<?php

namespace App\Services;

use App\Jobs\SendOrderStatusNotification;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Support\NotifyChoice;
use Illuminate\Support\Facades\Log;

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
    /**
     * The extra ways of telling a customer, registered by whatever is
     * installed. Same socket payments, shipping and couriers use.
     *
     * @var list<class-string<\App\Contracts\OrderNotificationChannel>>
     */
    private static array $channels = [];

    /** Called by an extension's service provider while it boots. */
    public static function registerChannel(string $class): void
    {
        if (! in_array($class, self::$channels, true)) {
            self::$channels[] = $class;
        }
    }

    /**
     * The channels that could send right now.
     *
     * Both the order page and the fulfilment queue draw their ticks from
     * this, which is why neither has to know a gateway exists: an extension
     * that is off registers nothing and its tick is simply not drawn.
     *
     * @return list<\App\Contracts\OrderNotificationChannel>
     */
    public static function channels(): array
    {
        $out = [];

        foreach (self::$channels as $class) {
            try {
                $channel = app($class);

                if ($channel->available()) {
                    $out[] = $channel;
                }
            } catch (\Throwable $e) {
                // A channel that cannot even be built is one the merchant
                // should not be offered. Never fatal to the page drawing it.
                Log::warning('Notification channel unavailable', ['class' => $class, 'error' => $e->getMessage()]);
            }
        }

        return $out;
    }

    /**
     * Send a status change through the channels the operator ticked.
     *
     * Separate from statusChanged() on purpose: the email tick and each
     * channel's tick are independent, so a store can text without emailing,
     * email without texting, or do both, and one failing never touches the
     * other.
     *
     * @param  list<string>  $keys  channel keys the operator ticked
     * @return array<string, array{sent: bool, reason: string|null}>
     */
    public static function statusChangedVia(array $keys, Order $order, ?string $oldStatus, ?string $newStatus, ?string $comment = null): array
    {
        $results = [];

        foreach (self::channels() as $channel) {
            if (! in_array($channel->key(), $keys, true)) {
                continue;
            }

            try {
                $results[$channel->key()] = $channel->statusChanged($order, $oldStatus, $newStatus, $comment);
            } catch (\Throwable $e) {
                // The contract says a channel never throws. This is the belt
                // for the one that forgets: the order already moved.
                Log::error('Notification channel threw', ['channel' => $channel->key(), 'error' => $e->getMessage()]);
                $results[$channel->key()] = ['sent' => false, 'reason' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Tell whoever should be told that this order moved.
     *
     * The one place that resolves the question. Email answers from the
     * status's own tick, each channel from its own settings, and an explicit
     * choice from a person with the order open beats both. Nothing is sent
     * here: a job per message is queued, so a slow gateway never holds open
     * a courier's webhook or the ERP's API call.
     *
     * @return array{email: bool, channels: list<string>} what was queued
     */
    public static function announce(
        Order $order,
        ?OrderStatus $newStatus,
        ?string $oldStatusName,
        ?string $comment,
        NotifyChoice $choice,
        ?int $historyId = null,
        ?array $plan = null,
    ): array {
        $plan ??= self::plan($newStatus, $choice);

        foreach ($plan['email'] ? ['email'] : [] as $target) {
            SendOrderStatusNotification::dispatch($order->id, $target, $oldStatusName, $newStatus?->name, $comment, $historyId);
        }

        foreach ($plan['channels'] as $key) {
            SendOrderStatusNotification::dispatch($order->id, $key, $oldStatusName, $newStatus?->name, $comment, $historyId);
        }

        return $plan;
    }

    /**
     * Who would be told about a move to this status, without telling them.
     *
     * Split from announce() so the doorway can write the history row with
     * the answer already in hand (notify_customer is true when anything at
     * all is queued), then queue exactly what it wrote down.
     *
     * @return array{email: bool, channels: list<string>}
     */
    public static function plan(?OrderStatus $newStatus, NotifyChoice $choice): array
    {
        $email = $choice->email ?? (bool) ($newStatus?->emails_customer);

        $keys = [];
        foreach (self::channels() as $channel) {
            $wanted = $choice->channels === null
                ? $channel->wantsStatus($newStatus?->id)
                : in_array($channel->key(), $choice->channels, true);

            if ($wanted) {
                $keys[] = $channel->key();
            }
        }

        return ['email' => $email, 'channels' => $keys];
    }

    /**
     * A status comment with the parcel's tracking on the end of it.
     *
     * The order page and the fulfilment queue both tell a customer the same
     * thing and were assembling that sentence separately, which is how they
     * drifted into sending different emails for the same act. One sentence,
     * built once, and the carrier and link are resolved by the same fallbacks
     * the shipping email uses rather than a second guess at them.
     *
     * The caller decides WHEN to append: the order page only does so when the
     * tracking was part of that submission, so a plain status change on an
     * order that already shipped does not repeat the number.
     */
    public static function withTracking(Order $order, ?string $comment = null): string
    {
        $tracking = self::trackingPlaceholders($order);
        $number = trim((string) $tracking['tracking_number']);
        $comment = trim((string) $comment);

        if ($number === '') {
            return $comment;
        }

        $carrier = trim((string) $tracking['carrier_name']);
        $url = trim((string) $tracking['tracking_url']);

        $tail = ($carrier !== '' ? e($carrier) . ': ' : '')
            . ($url !== ''
                ? '<a href="' . e($url) . '" target="_blank" rel="noopener">' . e($number) . '</a>'
                : e($number));

        return $comment !== '' ? $comment . ' | ' . $tail : $tail;
    }

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
