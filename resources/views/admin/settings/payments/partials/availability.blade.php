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
    <x-admin.card title="Availability rule">
        <x-slot name="headerActions">
            @if ($restriction)
                <form method="POST" action="{{ route('admin.payment-restrictions.toggle', $restriction) }}" data-v-form>
                    @csrf
                    <x-admin.toggle name="toggle_active" lead
                        label="{{ $restriction->is_active ? 'Active' : 'Disabled' }}"
                        :checked="$restriction->is_active"
                        x-on:change="$el.closest('form').submit()" />
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
            <p class="v-panel__blank">Available everywhere.</p>
        @endif
    </x-admin.card>

    {{-- Full edit form --}}
    <x-admin.card :title="$restriction ? 'Edit availability rule' : 'Add availability rule'">
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
                <h4 class="v-label mb-3">Order limits</h4>
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
                <div class="flex items-center gap-1.5 mb-4">
                    <h4 class="v-label text-v-accent">Include conditions</h4>
                    <x-admin.hint label="About include conditions">A section left empty is skipped; every section that has entries must match for {{ $name }} to appear.</x-admin.hint>
                </div>
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

                    {{-- Shipping keys are strings ('flat_rate', 'dhl') rather than
                         integer ids. The shared listbox preserves non-numeric ids
                         verbatim, so this row no longer needs its own copy of the
                         widget. --}}
                    @include('admin.partials.dual-listbox', [
                        'name' => 'shipping_method_keys',
                        'label' => 'Only with these Shipping Methods',
                        'hint' => 'Payment method only appears when the customer selects one of these shipping methods.',
                        'items' => $shippingItems,
                        'selected' => old('shipping_method_keys', $restriction?->shipping_method_keys ?? []),
                    ])
                </div>
            </div>

            {{-- Exclude conditions --}}
            <div>
                <div class="flex items-center gap-1.5 mb-4">
                    <h4 class="v-label text-v-danger">Exclude conditions</h4>
                    <x-admin.hint label="About exclude conditions">One match anywhere below is enough to hide {{ $name }}.</x-admin.hint>
                </div>
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

            <input type="hidden" name="is_active" value="0">
            <x-admin.checkbox name="is_active" label="Active"
                :checked="(bool) old('is_active', $restriction?->is_active ?? true)" />

            <x-admin.form-actions>
                @if ($restriction)
                    <x-admin.button variant="danger" type="button"
                        @click="$dispatch('open-modal', 'delete-restriction-{{ $method }}')">
                        Delete rule
                    </x-admin.button>
                @endif
                <x-admin.button variant="primary" type="submit">{{ $restriction ? 'Save' : 'Create restriction' }}</x-admin.button>
            </x-admin.form-actions>
        </form>

        {{-- The dialog carries its own form, so it sits outside the edit form
             above rather than nested inside it. --}}
        @if ($restriction)
            <x-admin.confirm-dialog
                name="delete-restriction-{{ $method }}"
                title="Delete availability rule"
                message="This removes every condition listed above. {{ $name }} becomes available everywhere."
                confirm="Delete rule"
                method="DELETE"
                :action="route('admin.payment-restrictions.destroy', $restriction)" />
        @endif
    </x-admin.card>
</div>
