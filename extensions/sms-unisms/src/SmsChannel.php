<?php

namespace Extensions\SmsUnisms;

use App\Contracts\OrderNotificationChannel;
use App\Models\Order;
use App\Support\Phone;

/**
 * Telling a customer by text what the email already told them.
 *
 * The point is redundancy: an email can land in spam, or in an inbox nobody
 * opens for two days. A text is read. So this says the same thing the status
 * email says, shorter, and is ticked separately because the two are not
 * always both wanted.
 */
class SmsChannel implements OrderNotificationChannel
{
    public function key(): string
    {
        return 'sms';
    }

    public function label(): string
    {
        return 'Send SMS';
    }

    /**
     * Offered only when it could actually send.
     *
     * An extension that is enabled but has no key or sender ID saved would
     * draw a tick that does nothing, which is worse than no tick: the admin
     * would believe the customer had been texted.
     */
    public function available(): bool
    {
        return UniSms::enabled() && UniSms::readiness()['ready'];
    }

    /** The store's own list, kept in this extension's settings. */
    public function wantsStatus(?int $orderStatusId): bool
    {
        return SmsStatusMap::texts($orderStatusId);
    }

    public function statusChanged(Order $order, ?string $oldStatus, ?string $newStatus, ?string $comment = null): array
    {
        $number = $this->numberFor($order);

        if ($number === null) {
            return ['sent' => false, 'reason' => 'No mobile number on this order that could be dialled.'];
        }

        $result = UniSms::send($number, $this->compose($order, $newStatus, $comment));

        return ['sent' => $result['ok'], 'reason' => $result['error']];
    }

    /**
     * The customer's number, in the form a gateway can dial.
     *
     * The account's own number first: it is the one they maintain and the one
     * they confirmed when registering. The order's copy is the fallback for a
     * guest, who has no account to keep a number in.
     */
    private function numberFor(Order $order): ?string
    {
        $code = $order->shippingCountry?->phone_code ?? $order->billingCountry?->phone_code;

        foreach ([$order->customer?->phone, $order->shipping_phone, $order->phone] as $candidate) {
            $number = Phone::e164($candidate, $code);

            if ($number !== null) {
                return $number;
            }
        }

        return null;
    }

    /**
     * What the text says.
     *
     * A text is not an email. No greeting, no signature, no marketing, and
     * never a link: the store, the order, the state it is now in, and the
     * tracking number if there is one.
     */
    public function compose(Order $order, ?string $newStatus, ?string $comment): string
    {
        $tracking = \App\Services\OrderNotificationService::trackingPlaceholders($order);
        $template = UniSms::template();
        $commentText = $this->plain((string) $comment);

        $text = strtr($template, [
            '{store_name}' => (string) config('app.name'),
            '{order_id}' => (string) $order->id,
            '{order_status}' => $newStatus ?: 'updated',
            '{customer_name}' => trim((string) $order->full_name),
            '{tracking_number}' => trim(strip_tags((string) $tracking['tracking_number'])),
            '{courier_name}' => trim((string) $tracking['carrier_name']),
            '{comment}' => $commentText,
        ]);

        // A template that never mentions {comment} still gets the courier's
        // note, appended, so a merchant who kept the default template is not
        // silently missing what came with this status change. A template
        // that already placed {comment} somewhere is left alone, so it is
        // never said twice.
        if ($commentText !== '' && ! str_contains($template, '{comment}')) {
            $text = trim($text) . ' ' . $commentText;
        }

        return mb_substr(UniSms::stripUrls($text), 0, UniSms::MAX_LENGTH);
    }

    /** Email HTML as something readable on a handset. */
    private function plain(string $html): string
    {
        if ($html === '') {
            return '';
        }

        // An anchor becomes what it said, not where it pointed: the tracking
        // number is what a customer can give the courier, and a link would
        // stop the message reaching them at all.
        $text = preg_replace('/<a[^>]*>(.*?)<\/a>/is', '$1', $html) ?? $html;

        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $text))) ?? '');
    }
}
