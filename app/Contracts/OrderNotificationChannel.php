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
     * A gateway that answered "not yet" (a rate limit) returns retry true,
     * optionally with retry_after in seconds, and the queued job tries again
     * instead of recording a failure. A channel that throttles can also
     * define a limiter named "order-notification:{key}" with Laravel's
     * RateLimiter::for(), which the job honours before sending at all.
     *
     * $newStatusId names the status the order moved to, so a channel can
     * answer per status (a message per status, say) without looking the
     * status up by its display name. Null for a caller that has only a name.
     *
     * @return array{sent: bool, reason: string|null, retry?: bool, retry_after?: int}
     */
    public function statusChanged(Order $order, ?string $oldStatus, ?string $newStatus, ?string $comment = null, ?int $newStatusId = null): array;

    /**
     * Does this channel want to speak about this status?
     *
     * The channel answers from its own settings, so no caller has to know
     * that a channel has settings at all. A channel with no policy of its
     * own answers true and lets the ticked/unticked caller decide.
     */
    public function wantsStatus(?int $orderStatusId): bool;
}
