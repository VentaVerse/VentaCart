<x-admin-layout>
    <x-slot name="breadcrumb">
        <a href="{{ route('admin.payment-settings.index') }}" class="text-gray-500 hover:text-gray-700 font-medium">Payment</a>
    </x-slot>
    <x-slot name="header">Restrictions</x-slot>

    @php
        $brandItems = $brands->map(function($b) { return ['id' => $b->id, 'name' => $b->name]; })->values()->all();
        $categoryItems = [];
        foreach ($categories as $cat) {
            $categoryItems[] = ['id' => $cat->id, 'name' => $cat->name];
            foreach ($cat->children as $child) {
                $categoryItems[] = ['id' => $child->id, 'name' => '— ' . $child->name];
            }
        }
        $groupItems = $customerGroups->map(function($g) { return ['id' => $g->id, 'name' => $g->name]; })->values()->all();
        $zoneItems = $geoZones->map(function($z) { return ['id' => $z->id, 'name' => $z->name]; })->values()->all();
        $shippingItems = collect($shippingMethods)->map(function($m) { return ['id' => $m['key'], 'name' => $m['name']]; })->all();
    @endphp

    <div x-data="{ activeTab: '{{ request('tab', $methods->first()['key']) }}' }">
        {{-- Tab Navigation --}}
        <div class="bg-white rounded-t-xl shadow-sm border border-gray-200">
            <nav class="flex border-b border-gray-200 overflow-x-auto">
                @foreach ($methods as $m)
                    <button type="button" @click="activeTab = '{{ $m['key'] }}'"
                        :class="activeTab === '{{ $m['key'] }}' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap px-5 py-3 border-b-2 text-sm font-medium transition-colors flex items-center gap-2">
                        {{ $m['label'] }}
                        @if ($m['restriction'])
                            <span class="w-2 h-2 rounded-full {{ $m['restriction']->is_active ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
                        @else
                            <span class="text-[10px] text-gray-400">(none)</span>
                        @endif
                    </button>
                @endforeach
            </nav>

            <div class="px-6 py-3 bg-gray-50/50 border-b border-gray-100">
                <p class="text-xs text-gray-500">Configure conditions for each payment method to appear at checkout. All inclusion conditions use AND logic. Exclusions block if any match.</p>
            </div>
        </div>

        {{-- Tab Content --}}
        @foreach ($methods as $m)
            @php $r = $m['restriction']; @endphp
            <div x-show="activeTab === '{{ $m['key'] }}'" x-cloak
                 class="bg-white rounded-b-xl shadow-sm border border-x border-b border-gray-200 border-t-0">

                <form method="POST"
                      action="{{ $r ? route('admin.payment-restrictions.update', $r) : route('admin.payment-restrictions.store') }}">
                    @csrf
                    @if ($r) @method('PUT') @endif
                    @if (!$r)
                        <input type="hidden" name="payment_method" value="{{ $m['key'] }}">
                    @endif

                    {{-- General Settings --}}
                    <div class="p-6 border-b border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">General</h3>
                            <label class="flex items-center gap-2">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1"
                                    {{ old('is_active', $r?->is_active ?? true) ? 'checked' : '' }}
                                    class="rounded border-gray-300 text-indigo-600">
                                <span class="text-sm text-gray-700">Active</span>
                            </label>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 max-w-3xl">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Min Order Total</label>
                                <input type="number" name="min_total" value="{{ old('min_total', $r?->min_total) }}" min="0" step="0.01"
                                    class="w-full rounded-lg border-gray-300 text-sm" placeholder="e.g. 500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Max Order Total</label>
                                <input type="number" name="max_total" value="{{ old('max_total', $r?->max_total) }}" min="0" step="0.01"
                                    class="w-full rounded-lg border-gray-300 text-sm" placeholder="e.g. 50000">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Min Weight (kg)</label>
                                <input type="number" name="min_weight" value="{{ old('min_weight', $r?->min_weight) }}" min="0" step="0.01"
                                    class="w-full rounded-lg border-gray-300 text-sm" placeholder="e.g. 0.5">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Max Weight (kg)</label>
                                <input type="number" name="max_weight" value="{{ old('max_weight', $r?->max_weight) }}" min="0" step="0.01"
                                    class="w-full rounded-lg border-gray-300 text-sm" placeholder="e.g. 30">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Min Quantity</label>
                                <input type="number" name="min_quantity" value="{{ old('min_quantity', $r?->min_quantity) }}" min="1"
                                    class="w-full rounded-lg border-gray-300 text-sm" placeholder="e.g. 1">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Max Quantity</label>
                                <input type="number" name="max_quantity" value="{{ old('max_quantity', $r?->max_quantity) }}" min="1"
                                    class="w-full rounded-lg border-gray-300 text-sm" placeholder="e.g. 100">
                            </div>
                        </div>
                    </div>

                    {{-- Conditions --}}
                    <div class="p-6 border-b border-gray-100">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Conditions</h3>
                        <p class="text-xs text-gray-500 mb-4">All conditions must be met. Leave empty to skip.</p>
                        <div class="space-y-6">
                            @include('admin.partials.dual-listbox', [
                                'name' => 'geo_zone_ids',
                                'label' => 'Only for these Geo Zones',
                                'hint' => 'Shipping address must be in one of the selected zones.',
                                'items' => $zoneItems,
                                'selected' => old('geo_zone_ids', $r?->geo_zone_ids ?? []),
                            ])
                            @include('admin.partials.dual-listbox', [
                                'name' => 'customer_group_ids',
                                'label' => 'Only for these Customer Groups',
                                'hint' => 'Leave empty = all groups.',
                                'items' => $groupItems,
                                'selected' => old('customer_group_ids', $r?->customer_group_ids ?? []),
                            ])
                            @include('admin.partials.dual-listbox', [
                                'name' => 'category_ids',
                                'label' => 'Only when cart contains these Categories',
                                'hint' => 'Cart must have at least one product from a selected category.',
                                'items' => $categoryItems,
                                'selected' => old('category_ids', $r?->category_ids ?? []),
                            ])
                            @include('admin.partials.dual-listbox', [
                                'name' => 'brand_ids',
                                'label' => 'Only when cart contains these Brands',
                                'hint' => 'Cart must have at least one product from a selected brand.',
                                'items' => $brandItems,
                                'selected' => old('brand_ids', $r?->brand_ids ?? []),
                            ])
                        </div>
                    </div>

                    {{-- Exclusions --}}
                    <div class="p-6 border-b border-gray-100">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Exclusions</h3>
                        <p class="text-xs text-gray-500 mb-4">Payment method will NOT appear if any exclusion matches.</p>
                        <div class="space-y-6">
                            @include('admin.partials.dual-listbox', [
                                'name' => 'exclude_geo_zone_ids',
                                'label' => 'Exclude Geo Zones',
                                'hint' => 'Block for customers in these zones.',
                                'items' => $zoneItems,
                                'selected' => old('exclude_geo_zone_ids', $r?->exclude_geo_zone_ids ?? []),
                            ])
                            @include('admin.partials.dual-listbox', [
                                'name' => 'exclude_customer_group_ids',
                                'label' => 'Exclude Customer Groups',
                                'hint' => 'These groups will never see this method.',
                                'items' => $groupItems,
                                'selected' => old('exclude_customer_group_ids', $r?->exclude_customer_group_ids ?? []),
                            ])
                            @include('admin.partials.dual-listbox', [
                                'name' => 'exclude_category_ids',
                                'label' => 'Exclude Categories',
                                'hint' => 'If cart has ANY product from these categories, method is hidden.',
                                'items' => $categoryItems,
                                'selected' => old('exclude_category_ids', $r?->exclude_category_ids ?? []),
                            ])
                            @include('admin.partials.dual-listbox', [
                                'name' => 'exclude_brand_ids',
                                'label' => 'Exclude Brands',
                                'hint' => 'If cart has ANY product from these brands, method is hidden.',
                                'items' => $brandItems,
                                'selected' => old('exclude_brand_ids', $r?->exclude_brand_ids ?? []),
                            ])
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="p-6 flex items-center justify-between">
                        @if ($r)
                            <form method="POST" action="{{ route('admin.payment-restrictions.destroy', $r) }}" onsubmit="return confirm('Remove all restrictions for {{ $m['label'] }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-sm text-red-600 hover:text-red-800 font-medium">Delete Restriction</button>
                            </form>
                        @else
                            <div></div>
                        @endif
                        <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                            {{ $r ? 'Update' : 'Create Restriction' }}
                        </button>
                    </div>
                </form>
            </div>
        @endforeach
    </div>
</x-admin-layout>
