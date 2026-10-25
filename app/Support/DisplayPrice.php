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
        /** What the cart charges for one unit, special included. */
        public readonly float $price,
        /** The product's (or the variant's) own price, before any special. */
        public readonly float $regularPrice,
        /** The special the cart applies, or null when none does. */
        public readonly ?ProductSpecial $special,
        /** How the special asks to be shown: 'sale' (struck-through regular price), 'override' (a plain price), or null. */
        public readonly ?string $displayMode,
        /** The last day the special applies, or null when it has no end. */
        public readonly ?CarbonInterface $endsAt,
    ) {
    }

    /** True when the regular price should be shown struck through beside the price. */
    public function isSale(): bool
    {
        return $this->displayMode === 'sale' && $this->price < $this->regularPrice;
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
     * @return array{price: float, regular_price: float, on_sale: bool, display_mode: ?string, saving_percent: int, ends_at: ?string}
     */
    public function toArray(): array
    {
        return [
            'price' => $this->price,
            'regular_price' => $this->regularPrice,
            'on_sale' => $this->isSale(),
            'display_mode' => $this->displayMode,
            'saving_percent' => $this->savingPercent(),
            'ends_at' => $this->endsAt?->toDateString(),
        ];
    }
}
