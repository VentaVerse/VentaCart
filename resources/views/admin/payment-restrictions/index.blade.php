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
        $currencyItems = $currencies->map(function($c) { return ['id' => $c->code, 'name' => $c->code . ' (' . $c->symbol . ')']; })->values()->all();
    @endphp

    <div x-data="{ active: '{{ request('tab', $methods->first()['key']) }}' }" class="flex gap-6">
        {{-- Left sidebar --}}
        <nav class="w-52 shrink-0">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100">
                    <h3 class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Payment Methods</h3>
                </div>
                @foreach ($methods as $m)
                    <button type="button" @click="active = '{{ $m['key'] }}'"
                        :class="active === '{{ $m['key'] }}' ? 'bg-indigo-50 text-indigo-700 border-l-2 border-indigo-500' : 'text-gray-600 hover:bg-gray-50 border-l-2 border-transparent'"
                        class="flex items-center justify-between w-full px-4 py-2.5 text-sm font-medium transition-colors text-left">
                        <span>{{ $m['label'] }}</span>
                        @if ($m['restriction'])
                            <span class="w-2 h-2 rounded-full shrink-0 {{ $m['restriction']->is_active ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
                        @else
                            <span class="text-[10px] text-gray-400">—</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </nav>

        {{-- Right content --}}
        <div class="flex-1 min-w-0">
            @foreach ($methods as $m)
                @php $r = $m['restriction']; @endphp
                <div x-show="active === '{{ $m['key'] }}'" x-cloak class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="flex items-center justify-between p-5 border-b border-gray-100">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900">{{ $m['label'] }}</h3>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $r ? 'Restriction configured' : 'No restriction — available to everyone' }}</p>
                        </div>
                        @if ($r)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $r->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500' }}">
                                {{ $r->is_active ? 'Active' : 'Disabled' }}
                            </span>
                        @endif
                    </div>

                    <form method="POST"
                          action="{{ $r ? route('admin.payment-restrictions.update', $r) : route('admin.payment-restrictions.store') }}">
                        @csrf
                        @if ($r) @method('PUT') @endif
                        @if (!$r)
                            <input type="hidden" name="payment_method" value="{{ $m['key'] }}">
                        @endif

                        <div class="p-5 space-y-6">
                            {{-- Totals / Weight / Quantity --}}
                            <div>
                                <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Order Limits</h4>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Min Total</label>
                                        <input type="number" name="min_total" value="{{ old('min_total', $r?->min_total) }}" min="0" step="0.01"
                                            class="w-full rounded-lg border-gray-300 text-sm" placeholder="e.g. 500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Max Total</label>
                                        <input type="number" name="max_total" value="{{ old('max_total', $r?->max_total) }}" min="0" step="0.01"
                                            class="w-full rounded-lg border-gray-300 text-sm" placeholder="e.g. 50000">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Min Weight (kg)</label>
                                        <input type="number" name="min_weight" value="{{ old('min_weight', $r?->min_weight) }}" min="0" step="0.01"
                                            class="w-full rounded-lg border-gray-300 text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Max Weight (kg)</label>
                                        <input type="number" name="max_weight" value="{{ old('max_weight', $r?->max_weight) }}" min="0" step="0.01"
                                            class="w-full rounded-lg border-gray-300 text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Min Qty</label>
                                        <input type="number" name="min_quantity" value="{{ old('min_quantity', $r?->min_quantity) }}" min="1"
                                            class="w-full rounded-lg border-gray-300 text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Max Qty</label>
                                        <input type="number" name="max_quantity" value="{{ old('max_quantity', $r?->max_quantity) }}" min="1"
                                            class="w-full rounded-lg border-gray-300 text-sm">
                                    </div>
                                </div>
                            </div>

                            {{-- Conditions & Exclusions side by side --}}
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <div>
                                    <h4 class="text-xs font-semibold text-emerald-600 uppercase tracking-wider mb-1">Include Conditions</h4>
                                    <p class="text-xs text-gray-500 mb-3">All must be met. Leave empty to skip.</p>
                                    <div class="space-y-4">
                                        @include('admin.partials.dual-listbox', ['name' => 'geo_zone_ids', 'label' => 'Geo Zones', 'hint' => 'Only show for these zones', 'items' => $zoneItems, 'selected' => old('geo_zone_ids', $r?->geo_zone_ids ?? [])])
                                        @include('admin.partials.dual-listbox', ['name' => 'customer_group_ids', 'label' => 'Customer Groups', 'hint' => 'Only show for these groups', 'items' => $groupItems, 'selected' => old('customer_group_ids', $r?->customer_group_ids ?? [])])
                                        @include('admin.partials.dual-listbox', ['name' => 'category_ids', 'label' => 'Categories', 'hint' => 'Cart must contain products from these', 'items' => $categoryItems, 'selected' => old('category_ids', $r?->category_ids ?? [])])
                                        @include('admin.partials.dual-listbox', ['name' => 'brand_ids', 'label' => 'Brands', 'hint' => 'Cart must contain products from these', 'items' => $brandItems, 'selected' => old('brand_ids', $r?->brand_ids ?? [])])
                                        @include('admin.partials.dual-listbox', ['name' => 'currency_codes', 'label' => 'Currencies', 'hint' => 'Only show when viewing in these currencies', 'items' => $currencyItems, 'selected' => old('currency_codes', $r?->currency_codes ?? [])])
                                    </div>
                                </div>
                                <div>
                                    <h4 class="text-xs font-semibold text-red-500 uppercase tracking-wider mb-1">Exclude Conditions</h4>
                                    <p class="text-xs text-gray-500 mb-3">Block if any match.</p>
                                    <div class="space-y-4">
                                        @include('admin.partials.dual-listbox', ['name' => 'exclude_geo_zone_ids', 'label' => 'Geo Zones', 'hint' => 'Block for these zones', 'items' => $zoneItems, 'selected' => old('exclude_geo_zone_ids', $r?->exclude_geo_zone_ids ?? [])])
                                        @include('admin.partials.dual-listbox', ['name' => 'exclude_customer_group_ids', 'label' => 'Customer Groups', 'hint' => 'Block for these groups', 'items' => $groupItems, 'selected' => old('exclude_customer_group_ids', $r?->exclude_customer_group_ids ?? [])])
                                        @include('admin.partials.dual-listbox', ['name' => 'exclude_category_ids', 'label' => 'Categories', 'hint' => 'Block if cart has these', 'items' => $categoryItems, 'selected' => old('exclude_category_ids', $r?->exclude_category_ids ?? [])])
                                        @include('admin.partials.dual-listbox', ['name' => 'exclude_brand_ids', 'label' => 'Brands', 'hint' => 'Block if cart has these', 'items' => $brandItems, 'selected' => old('exclude_brand_ids', $r?->exclude_brand_ids ?? [])])
                                        @include('admin.partials.dual-listbox', ['name' => 'exclude_currency_codes', 'label' => 'Currencies', 'hint' => 'Block when viewing in these currencies', 'items' => $currencyItems, 'selected' => old('exclude_currency_codes', $r?->exclude_currency_codes ?? [])])
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Footer --}}
                        <div class="px-5 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between rounded-b-lg">
                            <div class="flex items-center gap-4">
                                <label class="flex items-center gap-2">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1"
                                        {{ old('is_active', $r?->is_active ?? true) ? 'checked' : '' }}
                                        class="rounded border-gray-300 text-indigo-600">
                                    <span class="text-sm text-gray-700">Active</span>
                                </label>
                            </div>
                            <div class="flex items-center gap-3">
                                @if ($r)
                                    <button type="button" onclick="if(confirm('Remove all restrictions for {{ $m['label'] }}?')) document.getElementById('delete-{{ $m['key'] }}').submit()"
                                        class="text-xs text-red-600 hover:text-red-800 font-medium">Delete</button>
                                @endif
                                <button type="submit" class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                                    {{ $r ? 'Save' : 'Create Restriction' }}
                                </button>
                            </div>
                        </div>
                    </form>

                    @if ($r)
                        <form id="delete-{{ $m['key'] }}" method="POST" action="{{ route('admin.payment-restrictions.destroy', $r) }}" class="hidden">
                            @csrf @method('DELETE')
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</x-admin-layout>
