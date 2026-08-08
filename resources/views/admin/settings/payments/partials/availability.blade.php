@php
    $brandItems = $brands->map(function ($b) { return ['id' => $b->id, 'name' => $b->name]; })->values()->all();
    $categoryItems = [];
    foreach ($categories as $cat) {
        $categoryItems[] = ['id' => $cat->id, 'name' => $cat->name];
        foreach ($cat->children as $child) {
            $categoryItems[] = ['id' => $child->id, 'name' => '- ' . $child->name];
        }
    }
    $groupItems = $customerGroups->map(function ($g) { return ['id' => $g->id, 'name' => $g->name]; })->values()->all();
    $zoneItems = $geoZones->map(function ($z) { return ['id' => $z->id, 'name' => $z->name]; })->values()->all();
    $shippingItems = collect($shippingMethods)->map(function ($m) { return ['id' => $m['key'], 'name' => $m['name']]; })->all();
    $currencyItems = $currencies->map(function ($c) { return ['id' => $c->code, 'name' => $c->code . ' (' . $c->symbol . ')']; })->values()->all();

    // Resolves a list of stored ids/keys to their display names using one of
    // the item arrays above. Falls back to the raw id for anything that no
    // longer resolves (e.g. a deleted brand still referenced by a rule).
    $resolveNames = function (array $ids, array $items) {
        $byId = collect($items)->keyBy('id');
        return collect($ids)->map(function ($id) use ($byId) {
            return $byId->get($id)['name'] ?? (string) $id;
        })->implode(', ');
    };

    $sentences = [];
    if ($restriction) {
        if ($restriction->min_total) {
            $sentences[] = 'Minimum order total: ₱' . number_format($restriction->min_total, 2);
        }
        if ($restriction->max_total) {
            $sentences[] = 'Maximum order total: ₱' . number_format($restriction->max_total, 2);
        }
        if ($restriction->min_weight) {
            $sentences[] = 'Minimum weight: ' . $restriction->min_weight . 'kg';
        }
        if ($restriction->max_weight) {
            $sentences[] = 'Maximum weight: ' . $restriction->max_weight . 'kg';
        }
        if ($restriction->min_quantity) {
            $sentences[] = 'Minimum quantity: ' . $restriction->min_quantity . ' items';
        }
        if ($restriction->max_quantity) {
            $sentences[] = 'Maximum quantity: ' . $restriction->max_quantity . ' items';
        }
        if (!empty($restriction->geo_zone_ids)) {
            $sentences[] = 'Only available in geo zones: ' . $resolveNames($restriction->geo_zone_ids, $zoneItems);
        }
        if (!empty($restriction->customer_group_ids)) {
            $sentences[] = 'Only available for customer groups: ' . $resolveNames($restriction->customer_group_ids, $groupItems);
        }
        if (!empty($restriction->category_ids)) {
            $sentences[] = 'Only available when cart contains categories: ' . $resolveNames($restriction->category_ids, $categoryItems);
        }
        if (!empty($restriction->brand_ids)) {
            $sentences[] = 'Only available when cart contains brands: ' . $resolveNames($restriction->brand_ids, $brandItems);
        }
        if (!empty($restriction->shipping_method_keys)) {
            $sentences[] = 'Only available with shipping methods: ' . $resolveNames($restriction->shipping_method_keys, $shippingItems);
        }
        if (!empty($restriction->currency_codes)) {
            $sentences[] = 'Only available in currencies: ' . $resolveNames($restriction->currency_codes, $currencyItems);
        }
        if (!empty($restriction->exclude_geo_zone_ids)) {
            $sentences[] = 'Hidden for geo zones: ' . $resolveNames($restriction->exclude_geo_zone_ids, $zoneItems);
        }
        if (!empty($restriction->exclude_customer_group_ids)) {
            $sentences[] = 'Hidden for customer groups: ' . $resolveNames($restriction->exclude_customer_group_ids, $groupItems);
        }
        if (!empty($restriction->exclude_category_ids)) {
            $sentences[] = 'Hidden when cart contains categories: ' . $resolveNames($restriction->exclude_category_ids, $categoryItems);
        }
        if (!empty($restriction->exclude_brand_ids)) {
            $sentences[] = 'Hidden when cart contains brands: ' . $resolveNames($restriction->exclude_brand_ids, $brandItems);
        }
        if (!empty($restriction->exclude_currency_codes)) {
            $sentences[] = 'Hidden in currencies: ' . $resolveNames($restriction->exclude_currency_codes, $currencyItems);
        }
    }
@endphp

<div class="space-y-6">
    {{-- Rule summary --}}
    <x-admin.card title="Availability rule" description="Who can see and use {{ $name }} at checkout.">
        <x-slot name="headerActions">
            @if ($restriction)
                <form method="POST" action="{{ route('admin.payment-restrictions.toggle', $restriction) }}" data-v-form>
                    @csrf
                    <label class="flex items-center gap-2 text-[13px] text-v-secondary cursor-pointer">
                        <span>{{ $restriction->is_active ? 'Active' : 'Disabled' }}</span>
                        <x-admin.toggle name="toggle_active" :checked="$restriction->is_active"
                            x-on:change="$el.closest('form').submit()" />
                    </label>
                </form>
            @endif
        </x-slot>

        @if ($restriction && count($sentences) > 0)
            <ul class="list-disc list-inside space-y-1.5 text-[13px] text-v-text">
                @foreach ($sentences as $sentence)
                    <li>{{ $sentence }}</li>
                @endforeach
            </ul>
        @else
            <p class="text-[13px] text-v-secondary">No restrictions: available everywhere</p>
        @endif
    </x-admin.card>

    {{-- Full edit form --}}
    <x-admin.card :title="$restriction ? 'Edit availability rule' : 'Add availability rule'"
        description="Every condition below must be met for {{ $name }} to appear at checkout; exclusions hide it if any match.">
        <form method="POST"
              action="{{ $restriction ? route('admin.payment-restrictions.update', $restriction) : route('admin.payment-restrictions.store') }}"
              data-v-form class="space-y-8">
            @csrf
            @if ($restriction)
                @method('PUT')
            @else
                <input type="hidden" name="payment_method" value="{{ $method }}">
            @endif

            {{-- Order limits --}}
            <div>
                <h4 class="text-xs font-semibold text-v-muted uppercase tracking-wider mb-3">Order limits</h4>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                    <x-admin.field name="min_total" label="Min order total">
                        <x-admin.input type="number" name="min_total" :value="$restriction?->min_total"
                            min="0" step="0.01" placeholder="e.g. 500" />
                    </x-admin.field>
                    <x-admin.field name="max_total" label="Max order total">
                        <x-admin.input type="number" name="max_total" :value="$restriction?->max_total"
                            min="0" step="0.01" placeholder="e.g. 50000" />
                    </x-admin.field>
                    <x-admin.field name="min_weight" label="Min weight (kg)">
                        <x-admin.input type="number" name="min_weight" :value="$restriction?->min_weight"
                            min="0" step="0.01" placeholder="e.g. 0.5" />
                    </x-admin.field>
                    <x-admin.field name="max_weight" label="Max weight (kg)">
                        <x-admin.input type="number" name="max_weight" :value="$restriction?->max_weight"
                            min="0" step="0.01" placeholder="e.g. 30" />
                    </x-admin.field>
                    <x-admin.field name="min_quantity" label="Min quantity">
                        <x-admin.input type="number" name="min_quantity" :value="$restriction?->min_quantity"
                            min="1" placeholder="e.g. 1" />
                    </x-admin.field>
                    <x-admin.field name="max_quantity" label="Max quantity">
                        <x-admin.input type="number" name="max_quantity" :value="$restriction?->max_quantity"
                            min="1" placeholder="e.g. 100" />
                    </x-admin.field>
                </div>
            </div>

            {{-- Include conditions --}}
            <div>
                <h4 class="text-xs font-semibold text-v-accent uppercase tracking-wider mb-1">Include conditions</h4>
                <p class="text-xs text-v-muted mb-4">All conditions must be met for {{ $name }} to appear. Leave a section empty to skip that condition.</p>
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

                    @include('admin.partials.dual-listbox', [
                        'name' => 'currency_codes',
                        'label' => 'Only in these Currencies',
                        'hint' => 'Show this payment method only when the shopper is viewing in a selected currency.',
                        'items' => $currencyItems,
                        'selected' => old('currency_codes', $restriction?->currency_codes ?? []),
                    ])

                    {{-- Shipping method listbox uses string keys instead of integer IDs — ported as-is from the old restriction form. --}}
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

            {{-- Exclude conditions --}}
            <div>
                <h4 class="text-xs font-semibold text-v-danger uppercase tracking-wider mb-1">Exclude conditions</h4>
                <p class="text-xs text-v-muted mb-4">{{ $name }} will NOT appear if the order matches any exclusion below.</p>
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

                    @include('admin.partials.dual-listbox', [
                        'name' => 'exclude_currency_codes',
                        'label' => 'Exclude Currencies',
                        'hint' => 'Hide this payment method when the shopper is viewing in these currencies.',
                        'items' => $currencyItems,
                        'selected' => old('exclude_currency_codes', $restriction?->exclude_currency_codes ?? []),
                    ])
                </div>
            </div>

            <label class="flex items-center gap-2 text-[13px] text-v-text">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1"
                    {{ old('is_active', $restriction?->is_active ?? true) ? 'checked' : '' }}
                    class="rounded border-v-border text-v-accent">
                Active
            </label>

            <x-admin.form-actions>
                @if ($restriction)
                    <x-admin.button variant="danger" type="button"
                        onclick="if(confirm('Delete the availability rule for {{ $name }}? This removes ALL conditions listed above. {{ $name }} will become available everywhere.')) document.getElementById('delete-restriction-{{ $method }}').submit()">
                        Delete rule
                    </x-admin.button>
                @endif
                <x-admin.button variant="primary" type="submit">{{ $restriction ? 'Save' : 'Create restriction' }}</x-admin.button>
            </x-admin.form-actions>
        </form>

        @if ($restriction)
            <form id="delete-restriction-{{ $method }}" method="POST"
                  action="{{ route('admin.payment-restrictions.destroy', $restriction) }}" class="hidden">
                @csrf
                @method('DELETE')
            </form>
        @endif
    </x-admin.card>
</div>
