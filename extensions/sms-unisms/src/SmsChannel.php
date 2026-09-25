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

        $result = UniSms::send($number, $this->message($order, $newStatus, $comment));

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
     * A text is not an email. No greeting, no signature, no marketing: the
     * store, the order, the state it is now in, and the one link worth
     * tapping. The comment arrives as email HTML, so its tracking anchor is
     * flattened to a bare URL that a phone will make tappable on its own.
     */
    private function message(Order $order, ?string $newStatus, ?string $comment): string
    {
        $store = (string) config('app.name');
        $line = trim($store . ': order #' . $order->id . ' is now ' . ($newStatus ?: 'updated') . '.');

        $tail = $this->plain((string) $comment);

        if ($tail !== '') {
            $line .= ' ' . $tail;
        }

        return mb_substr($line, 0, UniSms::MAX_LENGTH);
    }

    /** Email HTML as something readable on a handset. */
    private function plain(string $html): string
    {
        if ($html === '') {
            return '';
        }

        // An anchor becomes its destination: the phone linkifies a bare URL,
        // and "TRK123" with no address is not something anyone can tap.
        $text = preg_replace('/<a[^>]*href="([^"]*)"[^>]*>.*?<\/a>/is', '$1', $html) ?? $html;

        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $text))) ?? '');
    }
}
