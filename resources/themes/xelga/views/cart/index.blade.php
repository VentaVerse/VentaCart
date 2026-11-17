{{-- The cart page: the cart template's sections, with core's cart lines, flags and subtotal. --}}
<x-store-layout title="Cart">
    @include('xelga::snippets.template', ['pageType' => 'cart', 'page' => compact('items', 'subtotal', 'hasOutOfStock', 'outOfStockIds', 'hasUnavailable', 'unavailableIds') + (isset($cartGroups) ? ['cartGroups' => $cartGroups] : [])])
</x-store-layout>
