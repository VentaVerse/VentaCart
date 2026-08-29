<?php

namespace App\Support;

/**
 * The tone an order status wears on the storefront: the colour family of
 * its badge on My Orders, the order page and the account dashboard. Stored
 * per status and chosen by the merchant (Settings, Order Status); the
 * suggestion here only fills a fresh seed and the one-time backfill for a
 * store that predates the column, so a status the merchant renamed keeps
 * whatever tone they set afterwards.
 */
final class StatusTone
{
    public const NEUTRAL = 'neutral';
    public const INFO = 'info';
    public const WARNING = 'warning';
    public const SUCCESS = 'success';
    public const DANGER = 'danger';

    /** @var list<string> */
    public const ALL = [self::NEUTRAL, self::INFO, self::WARNING, self::SUCCESS, self::DANGER];

    /** @var array<string, string> */
    public const LABELS = [
        self::NEUTRAL => 'Neutral',
        self::INFO => 'In progress',
        self::WARNING => 'Attention',
        self::SUCCESS => 'Done',
        self::DANGER => 'Stopped',
    ];

    public static function suggest(string $name, bool $deductsStock = false): string
    {
        $n = strtolower($name);

        foreach (['cancel', 'denied', 'fail', 'refund', 'revers', 'chargeback', 'expired', 'void', 'lost', 'damaged', 'return'] as $word) {
            if (str_contains($n, $word)) {
                return self::DANGER;
            }
        }
        foreach (['complete', 'delivered', 'received', 'processed', 'paid'] as $word) {
            if (str_contains($n, $word) && ! str_contains($n, 'unpaid')) {
                return self::SUCCESS;
            }
        }
        foreach (['unpaid', 'pending', 'quote', 'awaiting', 'on hold'] as $word) {
            if (str_contains($n, $word)) {
                return self::WARNING;
            }
        }

        return $deductsStock ? self::INFO : self::NEUTRAL;
    }
}
