<x-admin-layout>
    <x-slot name="header">{{ $rule ? 'Edit Rule: ' . $rule->name : 'Create Free Shipping Rule' }}</x-slot>
    <x-slot name="headerActions">
        <a href="{{ route('admin.free-shipping-rules.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors">
            Back to Rules
        </a>
    </x-slot>

    @php
        $brandItems = $brands->map(fn($b) => ['id' => $b->id, 'name' => $b->name])->values()->all();
        $categoryItems = [];
        foreach ($categories as $cat) {
            $categoryItems[] = ['id' => $cat->id, 'name' => $cat->name];
            foreach ($cat->children as $child) {
                $categoryItems[] = ['id' => $child->id, 'name' => '— ' . $child->name];
            }
        }
        $groupItems = $customerGroups->map(fn($g) => ['id' => $g->id, 'name' => $g->name])->values()->all();
        $zoneItems = $shippingZones->map(fn($z) => ['id' => $z->id, 'name' => $z->name])->values()->all();
    @endphp

    <form method="POST"
          action="{{ $rule ? route('admin.free-shipping-rules.update', $rule) : route('admin.free-shipping-rules.store') }}"
          class="">
        @csrf
        @if ($rule)
            @method('PUT')
        @endif

        {{-- General --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">General</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Rule Name <span class="text-xs font-normal text-gray-400">(admin reference)</span></label>
                    <input type="text" name="name" value="{{ old('name', $rule?->name) }}" required
                        class="w-full rounded-lg border-gray-300 text-sm" placeholder="e.g. Free Shipping over ₱5,000">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Checkout Name <span class="text-xs font-normal text-gray-400">(shown to customer; falls back to Rule Name)</span></label>
                    <input type="text" name="checkout_name" value="{{ old('checkout_name', $rule?->checkout_name) }}"
                        class="w-full rounded-lg border-gray-300 text-sm" placeholder="e.g. Free Shipping">
                    @error('checkout_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Min Order Total</label>
                        <input type="number" name="min_order_total" value="{{ old('min_order_total', $rule?->min_order_total) }}" min="0" step="0.01"
                            class="w-full rounded-lg border-gray-300 text-sm" placeholder="e.g. 5000">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Min Quantity</label>
                        <input type="number" name="min_quantity" value="{{ old('min_quantity', $rule?->min_quantity) }}" min="1"
                            class="w-full rounded-lg border-gray-300 text-sm" placeholder="e.g. 3">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                        <input type="number" name="sort_order" value="{{ old('sort_order', $rule?->sort_order ?? 0) }}" min="0"
                            class="w-full rounded-lg border-gray-300 text-sm">
                    </div>
                </div>
                <label class="flex items-center gap-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1"
                        {{ old('is_active', $rule?->is_active ?? true) ? 'checked' : '' }}
                        class="rounded border-gray-300 text-indigo-600">
                    <span class="text-sm text-gray-700">Active</span>
                </label>
                <div class="border-t border-gray-200 pt-4 mt-2 space-y-3">
                    <label class="flex items-start gap-2">
                        <input type="hidden" name="apply_whole_cart" value="0">
                        <input type="checkbox" name="apply_whole_cart" value="1"
                            {{ old('apply_whole_cart', $rule?->apply_whole_cart ?? false) ? 'checked' : '' }}
                            class="rounded border-gray-300 text-indigo-600 mt-0.5">
                        <div>
                            <span class="text-sm text-gray-700 font-medium">Apply to whole cart</span>
                            <p class="text-xs text-gray-500">If ANY qualifying product is in the cart, the entire cart gets free shipping — even if other products don't match the brand/category conditions.</p>
                        </div>
                    </label>
                    <label class="flex items-start gap-2">
                        <input type="hidden" name="show_badge" value="0">
                        <input type="checkbox" name="show_badge" value="1"
                            {{ old('show_badge', $rule?->show_badge ?? false) ? 'checked' : '' }}
                            class="rounded border-gray-300 text-indigo-600 mt-0.5">
                        <div>
                            <span class="text-sm text-gray-700 font-medium">Show "Free Shipping" badge</span>
                            <p class="text-xs text-gray-500">Display a "Free Shipping" label on product cards and product pages for items that qualify for this rule.</p>
                        </div>
                    </label>
                </div>
            </div>
        </div>

        {{-- Conditions --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-1 uppercase tracking-wide">Conditions</h3>
            <p class="text-xs text-gray-500 mb-6">All conditions must be met. Leave a section empty to skip that condition.</p>

            <div class="space-y-6">
                @include('admin.partials.dual-listbox', [
                    'name' => 'brand_ids',
                    'label' => 'Only for these Brands',
                    'hint' => 'Cart must contain ONLY products from selected brands.',
                    'items' => $brandItems,
                    'selected' => old('brand_ids', $rule?->brand_ids ?? []),
                ])

                @include('admin.partials.dual-listbox', [
                    'name' => 'category_ids',
                    'label' => 'Only for these Categories',
                    'hint' => 'Cart must contain ONLY products from selected categories.',
                    'items' => $categoryItems,
                    'selected' => old('category_ids', $rule?->category_ids ?? []),
                ])

                @include('admin.partials.dual-listbox', [
                    'name' => 'customer_group_ids',
                    'label' => 'Only for these Customer Groups',
                    'hint' => 'Leave empty = all groups.',
                    'items' => $groupItems,
                    'selected' => old('customer_group_ids', $rule?->customer_group_ids ?? []),
                ])

                @include('admin.partials.dual-listbox', [
                    'name' => 'shipping_zone_ids',
                    'label' => 'Only for these Shipping Zones',
                    'hint' => 'Leave empty = all zones.',
                    'items' => $zoneItems,
                    'selected' => old('shipping_zone_ids', $rule?->shipping_zone_ids ?? []),
                ])
            </div>
        </div>

        {{-- Exclusions --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-1 uppercase tracking-wide">Exclusions</h3>
            <p class="text-xs text-gray-500 mb-6">Free shipping will NOT apply if the cart/customer matches any exclusion.</p>

            <div class="space-y-6">
                @include('admin.partials.dual-listbox', [
                    'name' => 'exclude_customer_group_ids',
                    'label' => 'Exclude Customer Groups',
                    'hint' => 'These groups will never get free shipping from this rule.',
                    'items' => $groupItems,
                    'selected' => old('exclude_customer_group_ids', $rule?->exclude_customer_group_ids ?? []),
                ])

                @include('admin.partials.dual-listbox', [
                    'name' => 'exclude_brand_ids',
                    'label' => 'Exclude Brands',
                    'hint' => 'If cart contains ANY product from these brands, free shipping won\'t apply.',
                    'items' => $brandItems,
                    'selected' => old('exclude_brand_ids', $rule?->exclude_brand_ids ?? []),
                ])

                @include('admin.partials.dual-listbox', [
                    'name' => 'exclude_category_ids',
                    'label' => 'Exclude Categories',
                    'hint' => 'If cart contains ANY product from these categories, free shipping won\'t apply.',
                    'items' => $categoryItems,
                    'selected' => old('exclude_category_ids', $rule?->exclude_category_ids ?? []),
                ])
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.free-shipping-rules.index') }}" class="px-6 py-2.5 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300">Cancel</a>
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                {{ $rule ? 'Update Rule' : 'Create Rule' }}
            </button>
        </div>
    </form>
</x-admin-layout>
