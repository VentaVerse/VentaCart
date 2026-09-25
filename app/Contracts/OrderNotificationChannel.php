<?php

namespace App\Contracts;

use App\Models\Order;

/**
 * Another way of telling a customer their order moved.
 *
 * Email is core's own and is not a channel: it is the one every store has and
 * it needs no extension. A channel is anything else, and it arrives the way
 * payments, shipping rates and couriers do, by registering itself while its
 * extension boots.
 *
 * Core never names one. The order page and the fulfilment queue draw a tick
 * per registered channel and ask each by key, so turning an extension off
 * removes its tick from both screens with nothing edited, and deleting the
 * extension entirely leaves both screens working with one tick fewer.
 */
interface OrderNotificationChannel
{
    /** Stable, and the value its tick posts. Never shown to anyone. */
    public function key(): string;

    /** What the tick says. The merchant reads this, so it names the act. */
    public function label(): string;

    /**
     * Can this channel actually send right now?
     *
     * A channel whose extension is enabled but which has no credentials
     * saved answers false, so the admin is not offered a tick that would
     * quietly do nothing.
     */
    public function available(): bool;

    /**
     * Tell the customer the order moved.
     *
     * Never throws. A gateway being down must not roll back a status change
     * or stop the email: the order moved whatever the network did.
     *
     * @return array{sent: bool, reason: string|null}
     */
    public function statusChanged(Order $order, ?string $oldStatus, ?string $newStatus, ?string $comment = null): array;

    /**
     * Does this channel want to speak about this status?
     *
     * The channel answers from its own settings, so no caller has to know
     * that a channel has settings at all. A channel with no policy of its
     * own answers true and lets the ticked/unticked caller decide.
     */
    public function wantsStatus(?int $orderStatusId): bool;
}
