<?php

namespace Tests\Fixtures\Extensions\SampleExtension\Cart;

use App\Contracts\CartLineGrouper;
use Illuminate\Support\Collection;

/**
 * Lines of products whose SKU starts with DEMO- belong to the "Demo Store"
 * seller; every other line is left for core to collect.
 */
class SampleCartGrouper implements CartLineGrouper
{
    public function group(Collection $items): array
    {
        $demo = $items->filter(fn ($item) => str_starts_with((string) $item->product?->sku, 'DEMO-'));

        return [[
            'key' => 'demo-store',
            'label' => 'Demo Store',
            'url' => 'https://example.com/shops/demo-store',
            'lines' => $demo->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'shipping' => ['label' => 'Shipping from this shop', 'amount' => 120, 'note' => 'Ships in 2 days'],
        ]];
    }
}
