<?php

namespace Extensions\SmsUnisms;

use App\Contracts\OrderNotificationChannel;
use App\Models\Order;
use App\Models\OrderStatus;
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

    public function statusChanged(Order $order, ?string $oldStatus, ?string $newStatus, ?string $comment = null, ?int $newStatusId = null): array
    {
        $statusId = $newStatusId ?? $this->statusIdNamed($newStatus);
        $template = SmsStatusMap::templateFor($statusId);

        // Normally unreachable: wantsStatus() already said no. It still
        // guards an explicit tick on the order page for a status the
        // merchant never wrote a message for (or switched off), which has
        // nothing to send.
        if ($template === null) {
            return ['sent' => false, 'reason' => 'No switched-on SMS template covers this status.'];
        }

        $number = $this->numberFor($order);

        if ($number === null) {
            $reason = 'No mobile number on this order that could be dialled.';
            SmsLog::create([
                'source' => 'status_change', 'order_id' => $order->id, 'order_status_id' => $statusId,
                'sms_template_id' => $template->id, 'recipient' => mb_substr((string) ($order->phone ?: '(none)'), 0, 32),
                'message' => $this->compose($template->message, $order, $newStatus, $comment),
                'status' => 'failed', 'error' => $reason,
            ]);

            return ['sent' => false, 'reason' => $reason];
        }

        $result = UniSms::send($number, $this->compose($template->message, $order, $newStatus, $comment), null, [
            'source' => 'status_change',
            'order_id' => $order->id,
            'order_status_id' => $statusId,
            'sms_template_id' => $template->id,
        ]);

        // 429 is UniSMS saying "one request every two seconds", not "no":
        // the job goes back on the queue rather than recording a failure.
        if (($result['status'] ?? null) === 429) {
            return ['sent' => false, 'reason' => $result['error'], 'retry' => true, 'retry_after' => UniSms::SECONDS_PER_REQUEST];
        }

        return ['sent' => $result['ok'], 'reason' => $result['error']];
    }

    /**
     * For a caller that knows the status only by name. Names are what the
     * customer reads, so a store does not give two statuses the same one.
     */
    private function statusIdNamed(?string $name): ?int
    {
        if ($name === null || $name === '') {
            return null;
        }

        $id = OrderStatus::where('store_id', (int) config('app.store_id', 1))->where('name', $name)->value('id');

        return $id === null ? null : (int) $id;
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
     * The merchant's template is authoritative: a placeholder the template
     * does not mention does not appear, whatever the order carries, and
     * nothing is appended on top of it.
     */
    public function compose(string $template, Order $order, ?string $newStatus, ?string $comment): string
    {
        $tracking = \App\Services\OrderNotificationService::trackingPlaceholders($order);

        return UniSms::render($template, [
            'store_name' => (string) config('app.name'),
            'order_id' => (string) $order->id,
            'order_status' => $newStatus ?: 'updated',
            'customer_name' => (string) $order->full_name,
            'tracking_number' => strip_tags((string) $tracking['tracking_number']),
            'courier_name' => (string) $tracking['carrier_name'],
            'comment' => $this->plain((string) $comment),
        ]);
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
