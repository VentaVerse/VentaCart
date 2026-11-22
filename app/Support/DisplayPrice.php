<?php

namespace App\Support;

use App\Models\ProductSpecial;
use Carbon\CarbonInterface;

/**
 * A product's price as a shopper should see it, worked out the same way the
 * cart charges it. Built by Pricing::display().
 */
final class DisplayPrice
{
    public function __construct(
        /** What the cart charges for one unit, any special or discount included. */
        public readonly float $price,
        /** The product's (or the variant's) own price, before any special or discount. */
        public readonly float $regularPrice,
        /** Which rule set the price: 'special', 'discount', or null for the regular price. */
        public readonly ?string $source,
        /** The special that set the price, or null when the price did not come from one. */
        public readonly ?ProductSpecial $special,
        /**
         * The last moment the winning special applies (inclusive, store
         * time zone), or null when the price is not a special's.
         * Countdowns run to this moment.
         */
        public readonly ?CarbonInterface $endsAt,
    ) {
    }

    /**
     * The end moment as ISO 8601 with the store's offset
     * (2026-10-31T21:30:00+08:00), for countdowns and script data, or null.
     */
    public function endsAtIso(): ?string
    {
        return $this->endsAt?->toIso8601String();
    }

    /**
     * True when the regular price should be shown struck through beside the
     * price: whenever the buyer pays less than it, from a special or a
     * discount alike.
     */
    public function isSale(): bool
    {
        return $this->price < $this->regularPrice;
    }

    /** The saving against the regular price, as a whole percentage; 0 when there is none. */
    public function savingPercent(): int
    {
        if ($this->regularPrice <= 0 || $this->price >= $this->regularPrice) {
            return 0;
        }

        return (int) round((1 - $this->price / $this->regularPrice) * 100);
    }

    /**
     * Plain values for JSON answers and script data. Amounts are in the
     * store's currency; format them with money().
     *
     * @return array{price: float, regular_price: float, on_sale: bool, source: ?string, saving_percent: int, ends_at: ?string}
     */
    public function toArray(): array
    {
        return [
            'price' => $this->price,
            'regular_price' => $this->regularPrice,
            'on_sale' => $this->isSale(),
            'source' => $this->source,
            'saving_percent' => $this->savingPercent(),
            'ends_at' => $this->endsAtIso(),
        ];
    }
}
