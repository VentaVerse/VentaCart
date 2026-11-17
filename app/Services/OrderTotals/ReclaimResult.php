<?php

namespace App\Services\OrderTotals;

/**
 * What a total line did when an order it had given back to was paid after
 * all: a note for the order's history, and whether the merchant must look
 * at the order because the line could not take back what it owed.
 */
final readonly class ReclaimResult
{
    public function __construct(
        public string $note,
        public bool $needsReview = false,
    ) {}
}
