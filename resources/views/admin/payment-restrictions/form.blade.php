<x-admin-layout>
    <x-slot name="header">{{ $restriction ? 'Edit Restriction: ' . \App\Models\PaymentMethodRestriction::getPaymentMethodLabel($restriction->payment_method) : 'Add Payment Restriction' }}</x-slot>
    <x-slot name="headerActions">
        <a href="{{ route('admin.payment-restrictions.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors">
            Back to Restrictions
        </a>
    </x-slot>

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

    <form method="POST"
          action="{{ $restriction ? route('admin.payment-restrictions.update', $restriction) : route('admin.payment-restrictions.store') }}"
          class="max-w-4xl">
        @csrf
        @if ($restriction)
            @method('PUT')
        @endif

        {{-- General --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">General</h3>
            <div class="space-y-4">
                @if ($restriction)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                        <div class="text-sm text-gray-900 bg-gray-50 rounded-lg px-3 py-2 border border-gray-200">
                            {{ \App\Models\PaymentMethodRestriction::getPaymentMethodLabel($restriction->payment_method) }}
                            <span class="text-xs text-gray-400 font-mono ml-2">({{ $restriction->payment_method }})</span>
                        </div>
                    </div>
                @else
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                        <select name="payment_method" required class="w-64 rounded-lg border-gray-300 text-sm">
                            <option value="">Select payment method...</option>
                            @foreach ($availableMethods as $method)
                                <option value="{{ $method }}" {{ old('payment_method') === $method ? 'selected' : '' }}>
                                    {{ \App\Models\PaymentMethodRestriction::getPaymentMethodLabel($method) }}
                                </option>
                            @endforeach
                        </select>
                        @error('payment_method') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Min Order Total</label>
                        <input type="number" name="min_total" value="{{ old('min_total', $restriction?->min_total) }}" min="0" step="0.01"
                            class="w-full rounded-lg border-gray-300 text-sm" placeholder="e.g. 500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Max Order Total</label>
                        <input type="number" name="max_total" value="{{ old('max_total', $restriction?->max_total) }}" min="0" step="0.01"
                            class="w-full rounded-lg border-gray-300 text-sm" placeholder="e.g. 50000">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Min Weight (kg)</label>
                        <input type="number" name="min_weight" value="{{ old('min_weight', $restriction?->min_weight) }}" min="0" step="0.01"
                            class="w-full rounded-lg border-gray-300 text-sm" placeholder="e.g. 0.5">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Max Weight (kg)</label>
                        <input type="number" name="max_weight" value="{{ old('max_weight', $restriction?->max_weight) }}" min="0" step="0.01"
                            class="w-full rounded-lg border-gray-300 text-sm" placeholder="e.g. 30">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Min Quantity</label>
                        <input type="number" name="min_quantity" value="{{ old('min_quantity', $restriction?->min_quantity) }}" min="1"
                            class="w-full rounded-lg border-gray-300 text-sm" placeholder="e.g. 1">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Max Quantity</label>
                        <input type="number" name="max_quantity" value="{{ old('max_quantity', $restriction?->max_quantity) }}" min="1"
                            class="w-full rounded-lg border-gray-300 text-sm" placeholder="e.g. 100">
                    </div>
                </div>

                <label class="flex items-center gap-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1"
                        {{ old('is_active', $restriction?->is_active ?? true) ? 'checked' : '' }}
                        class="rounded border-gray-300 text-indigo-600">
                    <span class="text-sm text-gray-700">Active</span>
                </label>
            </div>
        </div>

        {{-- Conditions --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-1 uppercase tracking-wide">Conditions</h3>
            <p class="text-xs text-gray-500 mb-6">All conditions must be met for the payment method to appear. Leave a section empty to skip that condition.</p>

            <div class="space-y-6">
                @include('admin.partials.dual-listbox', [
                    'name' => 'geo_zone_ids',
                    'label' => 'Only for these Geo Zones',
                    'hint' => 'Customer\'s shipping address must be in one of the selected zones.',
                    'items' => $zoneItems,
                    'selected' => old('geo_zone_ids', $restriction?->geo_zone_ids ?? []),
                ])

                @include('admin.partials.dual-listbox', [
                    'name' => 'customer_group_ids',
                    'label' => 'Only for these Customer Groups',
                    'hint' => 'Leave empty = all groups.',
                    'items' => $groupItems,
                    'selected' => old('customer_group_ids', $restriction?->customer_group_ids ?? []),
                ])

                @include('admin.partials.dual-listbox', [
                    'name' => 'category_ids',
                    'label' => 'Only when cart contains these Categories',
                    'hint' => 'Cart must have at least one product from a selected category.',
                    'items' => $categoryItems,
                    'selected' => old('category_ids', $restriction?->category_ids ?? []),
                ])

                @include('admin.partials.dual-listbox', [
                    'name' => 'brand_ids',
                    'label' => 'Only when cart contains these Brands',
                    'hint' => 'Cart must have at least one product from a selected brand.',
                    'items' => $brandItems,
                    'selected' => old('brand_ids', $restriction?->brand_ids ?? []),
                ])

                {{-- Shipping method listbox uses string keys instead of integer IDs --}}
                <div x-data="{
                    search: '',
                    allItems: @js($shippingItems),
                    selectedKeys: @js(old('shipping_method_keys', $restriction?->shipping_method_keys ?? [])),
                    showList: false,
                    highlightedAvailable: [],
                    highlightedSelected: [],

                    get available() {
                        return this.allItems.filter(item =>
                            !this.selectedKeys.includes(item.id) &&
                            (!this.search || item.name.toLowerCase().includes(this.search.toLowerCase()))
                        );
                    },

                    get selectedItems() {
                        return this.allItems.filter(item => this.selectedKeys.includes(item.id));
                    },

                    toggleHighlightAvailable(id, event) {
                        if (event.ctrlKey || event.metaKey) {
                            if (this.highlightedAvailable.includes(id)) {
                                this.highlightedAvailable = this.highlightedAvailable.filter(i => i !== id);
                            } else {
                                this.highlightedAvailable.push(id);
                            }
                        } else {
                            this.highlightedAvailable = [id];
                        }
                    },

                    toggleHighlightSelected(id, event) {
                        if (event.ctrlKey || event.metaKey) {
                            if (this.highlightedSelected.includes(id)) {
                                this.highlightedSelected = this.highlightedSelected.filter(i => i !== id);
                            } else {
                                this.highlightedSelected.push(id);
                            }
                        } else {
                            this.highlightedSelected = [id];
                        }
                    },

                    addHighlighted() {
                        this.highlightedAvailable.forEach(id => {
                            if (!this.selectedKeys.includes(id)) {
                                this.selectedKeys.push(id);
                            }
                        });
                        this.highlightedAvailable = [];
                    },

                    removeHighlighted() {
                        this.highlightedSelected.forEach(id => {
                            this.selectedKeys = this.selectedKeys.filter(i => i !== id);
                        });
                        this.highlightedSelected = [];
                    },

                    addAll() {
                        this.available.forEach(item => {
                            if (!this.selectedKeys.includes(item.id)) {
                                this.selectedKeys.push(item.id);
                            }
                        });
                        this.highlightedAvailable = [];
                    },

                    removeAll() {
                        this.selectedKeys = [];
                        this.highlightedSelected = [];
                    }
                }">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Only with these Shipping Methods</label>
                    <p class="text-xs text-gray-500 mb-2">Payment method only appears when customer selects one of these shipping methods.</p>

                    <button type="button" @click="showList = !showList"
                        class="flex items-center gap-2 text-sm text-indigo-600 hover:text-indigo-800 mb-2">
                        <svg class="w-4 h-4 transition-transform" :class="showList && 'rotate-90'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        <span x-text="selectedKeys.length > 0 ? selectedKeys.length + ' selected' : 'Select items'"></span>
                    </button>

                    <div class="flex flex-wrap gap-1.5 mb-2" x-show="selectedKeys.length > 0 && !showList">
                        <template x-for="item in selectedItems" :key="'tag-' + item.id">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-indigo-50 text-indigo-700 text-xs font-medium rounded-full border border-indigo-200">
                                <span x-text="item.name"></span>
                                <button type="button" @click="selectedKeys = selectedKeys.filter(i => i !== item.id)" class="text-indigo-400 hover:text-indigo-700">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </span>
                        </template>
                    </div>

                    <div x-show="showList" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                        class="flex items-stretch gap-0">
                        <div class="flex-1 border border-gray-200 rounded-l-lg overflow-hidden">
                            <div class="bg-gray-50 px-3 py-2 border-b border-gray-200">
                                <span class="text-xs font-medium text-gray-500 uppercase">Available</span>
                            </div>
                            <div class="h-36 overflow-y-auto">
                                <template x-for="item in available" :key="item.id">
                                    <div @click="toggleHighlightAvailable(item.id, $event)"
                                        :class="highlightedAvailable.includes(item.id) ? 'bg-indigo-100 text-indigo-800' : 'text-gray-700 hover:bg-gray-50'"
                                        class="w-full text-left px-3 py-1.5 text-sm cursor-pointer select-none transition-colors">
                                        <span x-text="item.name"></span>
                                    </div>
                                </template>
                                <div x-show="available.length === 0" class="px-3 py-6 text-xs text-gray-400 text-center">All items selected</div>
                            </div>
                        </div>
                        <div class="flex flex-col items-center justify-center gap-2 px-2 bg-gray-50 border-y border-gray-200">
                            <button type="button" @click="addAll()" title="Add all" class="w-8 h-8 flex items-center justify-center rounded bg-white border border-gray-300 text-gray-500 hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-300 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
                            </button>
                            <button type="button" @click="addHighlighted()" title="Add selected" :disabled="highlightedAvailable.length === 0" class="w-8 h-8 flex items-center justify-center rounded bg-white border border-gray-300 text-gray-500 hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-300 transition-colors disabled:opacity-30 disabled:cursor-not-allowed">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </button>
                            <button type="button" @click="removeHighlighted()" title="Remove selected" :disabled="highlightedSelected.length === 0" class="w-8 h-8 flex items-center justify-center rounded bg-white border border-gray-300 text-gray-500 hover:bg-red-50 hover:text-red-600 hover:border-red-300 transition-colors disabled:opacity-30 disabled:cursor-not-allowed">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <button type="button" @click="removeAll()" title="Remove all" x-show="selectedKeys.length > 0" class="w-8 h-8 flex items-center justify-center rounded bg-white border border-gray-300 text-gray-500 hover:bg-red-50 hover:text-red-600 hover:border-red-300 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7M19 19l-7-7 7-7"/></svg>
                            </button>
                        </div>
                        <div class="flex-1 border border-gray-200 rounded-r-lg overflow-hidden">
                            <div class="bg-gray-50 px-3 py-2 border-b border-gray-200">
                                <span class="text-xs font-medium text-gray-500 uppercase">Selected <span class="text-indigo-600" x-text="'(' + selectedKeys.length + ')'"></span></span>
                            </div>
                            <div class="h-36 overflow-y-auto">
                                <template x-for="item in selectedItems" :key="item.id">
                                    <div @click="toggleHighlightSelected(item.id, $event)"
                                        :class="highlightedSelected.includes(item.id) ? 'bg-red-100 text-red-800' : 'text-gray-700 hover:bg-gray-50'"
                                        class="w-full px-3 py-1.5 text-sm cursor-pointer select-none transition-colors">
                                        <span x-text="item.name"></span>
                                    </div>
                                </template>
                                <div x-show="selectedKeys.length === 0" class="px-3 py-6 text-xs text-gray-400 text-center">None selected</div>
                            </div>
                        </div>
                    </div>

                    <template x-for="key in selectedKeys" :key="'hidden-' + key">
                        <input type="hidden" name="shipping_method_keys[]" :value="key">
                    </template>
                </div>
            </div>
        </div>

        {{-- Exclusions --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-1 uppercase tracking-wide">Exclusions</h3>
            <p class="text-xs text-gray-500 mb-6">Payment method will NOT appear if the order matches any exclusion below.</p>

            <div class="space-y-6">
                @include('admin.partials.dual-listbox', [
                    'name' => 'exclude_geo_zone_ids',
                    'label' => 'Exclude Geo Zones',
                    'hint' => 'Block this payment method for customers in these zones.',
                    'items' => $zoneItems,
                    'selected' => old('exclude_geo_zone_ids', $restriction?->exclude_geo_zone_ids ?? []),
                ])

                @include('admin.partials.dual-listbox', [
                    'name' => 'exclude_customer_group_ids',
                    'label' => 'Exclude Customer Groups',
                    'hint' => 'These groups will never see this payment method.',
                    'items' => $groupItems,
                    'selected' => old('exclude_customer_group_ids', $restriction?->exclude_customer_group_ids ?? []),
                ])

                @include('admin.partials.dual-listbox', [
                    'name' => 'exclude_category_ids',
                    'label' => 'Exclude Categories',
                    'hint' => 'If cart contains ANY product from these categories, payment method is hidden.',
                    'items' => $categoryItems,
                    'selected' => old('exclude_category_ids', $restriction?->exclude_category_ids ?? []),
                ])

                @include('admin.partials.dual-listbox', [
                    'name' => 'exclude_brand_ids',
                    'label' => 'Exclude Brands',
                    'hint' => 'If cart contains ANY product from these brands, payment method is hidden.',
                    'items' => $brandItems,
                    'selected' => old('exclude_brand_ids', $restriction?->exclude_brand_ids ?? []),
                ])
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.payment-restrictions.index') }}" class="px-6 py-2.5 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300">Cancel</a>
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                {{ $restriction ? 'Update Restriction' : 'Create Restriction' }}
            </button>
        </div>
    </form>
</x-admin-layout>
