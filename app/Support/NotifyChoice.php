<?php

namespace App\Support;

/**
 * What the caller of a status change wants said, if anything.
 *
 * Each half is answered separately, because they are separate acts: a store
 * may email without texting. Null means "the store already answered this on
 * the status, or in the channel's own settings", which is the normal case.
 * An explicit value is one person deciding for one order, and it wins.
 */
final class NotifyChoice
{
    /** @param list<string>|null $channels */
    private function __construct(
        public readonly ?bool $email,
        public readonly ?array $channels,
    ) {
    }

    /** The store's ticks decide both halves. */
    public static function follow(): self
    {
        return new self(null, null);
    }

    /**
     * Someone with the order open decided, for this change only.
     *
     * @param list<string> $channels channel keys that were ticked
     */
    public static function explicit(bool $email, array $channels = []): self
    {
        return new self($email, $channels);
    }

    /**
     * Email regardless of the ticks; channels still follow them.
     *
     * For the paths where the email is the point of the act, such as a
     * customer cancelling their own order and being told it worked.
     */
    public static function emailAlways(): self
    {
        return new self(true, null);
    }

    /**
     * No status email, because this path sends its own; channels follow.
     *
     * Checkout and the payment paths already send an order confirmation. A
     * status email on top would tell the same customer the same thing twice
     * in the same second.
     */
    public static function emailHandledElsewhere(): self
    {
        return new self(false, null);
    }

    /** Tell nobody: an internal move the customer has no business hearing. */
    public static function silent(): self
    {
        return new self(false, []);
    }

    /**
     * Did a caller actually decide something, rather than defer to the
     * store's own ticks? Every factory but follow() answers yes: it is the
     * one that leaves $email null. Used to decide whether a status change
     * that did not move the status still deserves an announcement, such as
     * an operator ticking Notify while recording tracking on an order that
     * is already on its target status.
     */
    public function isExplicit(): bool
    {
        return $this->email !== null;
    }
}
