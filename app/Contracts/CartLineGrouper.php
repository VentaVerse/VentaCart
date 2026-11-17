<?php

namespace App\Contracts;

use Illuminate\Support\Collection;

/**
 * Splits a cart's lines into groups a theme draws under their own header,
 * such as one group per seller. Registered by an extension with
 * App\Support\CartGroups::use(). Core checks what it returns, so a theme
 * only ever draws a clean list in which every line appears exactly once.
 */
interface CartLineGrouper
{
    /**
     * @param  Collection<int, \App\Models\CartItem>  $items  the cart's lines, as the cart page loaded them
     * @return list<array{key: string, label: string, url?: ?string, lines: list<int>, shipping?: ?array{label: string, amount: int|float|null, note?: ?string}}>
     */
    public function group(Collection $items): array;
}
