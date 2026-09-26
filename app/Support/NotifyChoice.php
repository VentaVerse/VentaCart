<?php

namespace App\Support;

/**
 * What the caller of a status change wants said, if anything.
 *
 * Each half is answered separately, because they are separate acts: a store
 * may email without texting. Null means "the store's automatic settings
 * answer this" (the bound email policy, each channel's own list), which is
 * the normal case. An explicit value is one person, or one API caller,
 * deciding for one order, and it wins.
 */
final class NotifyChoice
{
    /** @param list<string>|null $channels */
    private function __construct(
        public readonly ?bool $email,
        public readonly ?array $channels,
        private readonly bool $toldElsewhere = false,
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
        return new self(false, null, true);
    }

    /** Tell nobody: an internal move the customer has no business hearing. */
    public static function silent(): self
    {
        return new self(false, []);
    }

    /**
     * Did the caller take a position on CHANNELS, rather than deferring to
     * each channel's own wantsStatus()? Only explicit() and silent() state
     * a channels list, even an empty one; follow(), emailAlways() and
     * emailHandledElsewhere() all leave $channels null and defer.
     *
     * Used to decide whether a status change that did not move the status
     * still deserves an announcement, such as an operator ticking Notify
     * (or a channel) while recording tracking on an order that is already
     * on its target status: that decision only ever comes from a human
     * with the order open (explicit()), or from a caller deliberately
     * silencing everything (silent()). emailHandledElsewhere() answers a
     * different question (this caller emails on its own) and must NOT
     * count as explicit here, or a HitPay webhook retry landing on an
     * order already at the target status would announce all over again.
     */
    public function isExplicit(): bool
    {
        return $this->channels !== null;
    }

    /**
     * Did the caller already tell the customer some other way, even though
     * the status email itself is suppressed?
     *
     * Only emailHandledElsewhere() sets this. Checkout and the HitPay paths
     * send their own confirmation email instead of a status email, but the
     * customer was still told: a history row written under this choice
     * should still read as customer-visible (notify_customer), which is
     * what the account page's history list keys off, separately from
     * whether the STATUS email itself went out.
     */
    public function toldCustomerElsewhere(): bool
    {
        return $this->toldElsewhere;
    }
}
