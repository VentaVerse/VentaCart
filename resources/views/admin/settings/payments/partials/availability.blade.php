@php
    $brandItems = $brands->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])->values()->all();
    $categoryItems = [];
    foreach ($categories as $cat) {
        $categoryItems[] = ['id' => $cat->id, 'name' => $cat->name];
        foreach ($cat->children as $child) {
            $categoryItems[] = ['id' => $child->id, 'name' => '- ' . $child->name];
        }
    }
    $groupItems = $customerGroups->map(fn ($g) => ['id' => $g->id, 'name' => $g->name])->values()->all();
    $zoneItems = $geoZones->map(fn ($z) => ['id' => $z->id, 'name' => $z->name])->values()->all();
    $shippingItems = collect($shippingMethods)->map(fn ($m) => ['id' => $m['key'], 'name' => $m['name']])->all();
    $currencyItems = $currencies->map(fn ($c) => ['id' => $c->code, 'name' => $c->code . ' (' . $c->symbol . ')'])->values()->all();

    $resolveNames = function (array $ids, array $items) {
        $byId = collect($items)->keyBy('id');
        return collect($ids)->map(fn ($id) => $byId->get($id)['name'] ?? (string) $id)->implode(', ');
    };

    $sentences = [];
    if ($restriction) {
        if ($restriction->min_total) $sentences[] = 'Minimum order total: ₱' . number_format($restriction->min_total, 2);
        if ($restriction->max_total) $sentences[] = 'Maximum order total: ₱' . number_format($restriction->max_total, 2);
        if ($restriction->min_weight) $sentences[] = 'Minimum weight: ' . $restriction->min_weight . 'kg';
        if ($restriction->max_weight) $sentences[] = 'Maximum weight: ' . $restriction->max_weight . 'kg';
        if ($restriction->min_quantity) $sentences[] = 'Minimum quantity: ' . $restriction->min_quantity . ' items';
        if ($restriction->max_quantity) $sentences[] = 'Maximum quantity: ' . $restriction->max_quantity . ' items';
        if (!empty($restriction->geo_zone_ids)) $sentences[] = 'Only available in geo zones: ' . $resolveNames($restriction->geo_zone_ids, $zoneItems);
        if (!empty($restriction->customer_group_ids)) $sentences[] = 'Only available for customer groups: ' . $resolveNames($restriction->customer_group_ids, $groupItems);
        if (!empty($restriction->category_ids)) $sentences[] = 'Only available when the cart contains categories: ' . $resolveNames($restriction->category_ids, $categoryItems);
        if (!empty($restriction->brand_ids)) $sentences[] = 'Only available when the cart contains brands: ' . $resolveNames($restriction->brand_ids, $brandItems);
        if (!empty($restriction->shipping_method_keys)) $sentences[] = 'Only available with shipping methods: ' . $resolveNames($restriction->shipping_method_keys, $shippingItems);
        if (!empty($restriction->currency_codes)) $sentences[] = 'Only available in currencies: ' . $resolveNames($restriction->currency_codes, $currencyItems);
        if (!empty($restriction->exclude_geo_zone_ids)) $sentences[] = 'Hidden for geo zones: ' . $resolveNames($restriction->exclude_geo_zone_ids, $zoneItems);
        if (!empty($restriction->exclude_customer_group_ids)) $sentences[] = 'Hidden for customer groups: ' . $resolveNames($restriction->exclude_customer_group_ids, $groupItems);
        if (!empty($restriction->exclude_category_ids)) $sentences[] = 'Hidden when the cart contains categories: ' . $resolveNames($restriction->exclude_category_ids, $categoryItems);
        if (!empty($restriction->exclude_brand_ids)) $sentences[] = 'Hidden when the cart contains brands: ' . $resolveNames($restriction->exclude_brand_ids, $brandItems);
        if (!empty($restriction->exclude_currency_codes)) $sentences[] = 'Hidden in currencies: ' . $resolveNames($restriction->exclude_currency_codes, $currencyItems);
    }
@endphp

<x-admin3.panel title="Availability rule">
    <x-slot:acts>
        @if ($restriction)
            <input type="hidden" form="restriction-toggle-form" name="toggle_active" value="1">
            <button type="submit" form="restriction-toggle-form" class="v3-toggle v3-toggle--btn" role="switch"
                    aria-checked="{{ $restriction->is_active ? 'true' : 'false' }}"
                    aria-label="Availability rule active">
                <span class="v3-toggle__track" aria-hidden="true"></span>
            </button>
        @endif
    </x-slot:acts>
    @if ($restriction && count($sentences) > 0)
        <ul class="v3-rulelist">
            @foreach ($sentences as $sentence)
                <li>{{ $sentence }}</li>
            @endforeach
        </ul>
    @else
        <p class="v3-cell-quiet">Available everywhere.</p>
    @endif
</x-admin3.panel>

@if ($restriction)
    <form id="restriction-toggle-form" method="POST" action="{{ route('admin.payment-restrictions.toggle', $restriction) }}" hidden>
        @csrf
    </form>
@endif

{{-- The rule is a record with its own CRUD; it saves in place like
     every other record editor rather than through the page save bar. --}}
<x-admin3.panel :title="$restriction ? 'Edit availability rule' : 'Add availability rule'">
    <form method="POST"
          action="{{ $restriction ? route('admin.payment-restrictions.update', $restriction) : route('admin.payment-restrictions.store') }}">
        @csrf
        @if ($restriction)
            @method('PUT')
        @else
            <input type="hidden" name="payment_method" value="{{ $method }}">
        @endif

        <div class="v3-label v3-gap-sm">Order limits</div>
        <div class="v3-grid v3-grid--3">
            <x-admin3.field name="min_total" label="Min order total">
                <input type="number" name="min_total" min="0" step="0.01" class="v3-control v3-mono" placeholder="e.g. 500" value="{{ old('min_total', $restriction?->min_total) }}">
            </x-admin3.field>
            <x-admin3.field name="max_total" label="Max order total">
                <input type="number" name="max_total" min="0" step="0.01" class="v3-control v3-mono" placeholder="e.g. 50000" value="{{ old('max_total', $restriction?->max_total) }}">
            </x-admin3.field>
            <x-admin3.field name="min_weight" label="Min weight (kg)">
                <input type="number" name="min_weight" min="0" step="0.01" class="v3-control v3-mono" placeholder="e.g. 0.5" value="{{ old('min_weight', $restriction?->min_weight) }}">
            </x-admin3.field>
            <x-admin3.field name="max_weight" label="Max weight (kg)">
                <input type="number" name="max_weight" min="0" step="0.01" class="v3-control v3-mono" placeholder="e.g. 30" value="{{ old('max_weight', $restriction?->max_weight) }}">
            </x-admin3.field>
            <x-admin3.field name="min_quantity" label="Min quantity">
                <input type="number" name="min_quantity" min="1" class="v3-control v3-mono" placeholder="e.g. 1" value="{{ old('min_quantity', $restriction?->min_quantity) }}">
            </x-admin3.field>
            <x-admin3.field name="max_quantity" label="Max quantity">
                <input type="number" name="max_quantity" min="1" class="v3-control v3-mono" placeholder="e.g. 100" value="{{ old('max_quantity', $restriction?->max_quantity) }}">
            </x-admin3.field>
        </div>

        <div class="v3-gap-lg">
            <div class="v3-label v3-gap-sm">Include conditions (a section left empty is skipped; every filled section must match)</div>
            <x-admin3.picklist name="geo_zone_ids" label="Only for these geo zones"
                hint="The shipping address must fall in one of the picked zones."
                :items="$zoneItems" :selected="old('geo_zone_ids', $restriction?->geo_zone_ids ?? [])" />
            <div class="v3-gap">
                <x-admin3.picklist name="customer_group_ids" label="Only for these customer groups"
                    :items="$groupItems" :selected="old('customer_group_ids', $restriction?->customer_group_ids ?? [])" />
            </div>
            <div class="v3-gap">
                <x-admin3.picklist name="category_ids" label="Only when the cart contains these categories"
                    :items="$categoryItems" :selected="old('category_ids', $restriction?->category_ids ?? [])" />
            </div>
            <div class="v3-gap">
                <x-admin3.picklist name="brand_ids" label="Only when the cart contains these brands"
                    :items="$brandItems" :selected="old('brand_ids', $restriction?->brand_ids ?? [])" />
            </div>
            <div class="v3-gap">
                <x-admin3.picklist name="currency_codes" label="Only in these currencies"
                    :items="$currencyItems" :selected="old('currency_codes', $restriction?->currency_codes ?? [])" />
            </div>
            <div class="v3-gap">
                <x-admin3.picklist name="shipping_method_keys" label="Only with these shipping methods"
                    :items="$shippingItems" :selected="old('shipping_method_keys', $restriction?->shipping_method_keys ?? [])" />
            </div>
        </div>

        <div class="v3-gap-lg">
            <div class="v3-label v3-gap-sm">Exclude conditions (one match anywhere below hides {{ $name }})</div>
            <x-admin3.picklist name="exclude_geo_zone_ids" label="Exclude geo zones"
                :items="$zoneItems" :selected="old('exclude_geo_zone_ids', $restriction?->exclude_geo_zone_ids ?? [])" />
            <div class="v3-gap">
                <x-admin3.picklist name="exclude_customer_group_ids" label="Exclude customer groups"
                    :items="$groupItems" :selected="old('exclude_customer_group_ids', $restriction?->exclude_customer_group_ids ?? [])" />
            </div>
            <div class="v3-gap">
                <x-admin3.picklist name="exclude_category_ids" label="Exclude categories"
                    :items="$categoryItems" :selected="old('exclude_category_ids', $restriction?->exclude_category_ids ?? [])" />
            </div>
            <div class="v3-gap">
                <x-admin3.picklist name="exclude_brand_ids" label="Exclude brands"
                    :items="$brandItems" :selected="old('exclude_brand_ids', $restriction?->exclude_brand_ids ?? [])" />
            </div>
            <div class="v3-gap">
                <x-admin3.picklist name="exclude_currency_codes" label="Exclude currencies"
                    :items="$currencyItems" :selected="old('exclude_currency_codes', $restriction?->exclude_currency_codes ?? [])" />
            </div>
        </div>

        <div class="v3-gap v3-actrow">
            <label class="v3-check">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $restriction?->is_active ?? true))>
                Active
            </label>
            @if ($restriction)
                <button type="button" class="v3-btn v3-btn--danger" @click="$dispatch('v3-confirm', 'delete-restriction-{{ $method }}')">Delete rule</button>
            @endif
            <button type="submit" class="v3-btn v3-btn--primary">{{ $restriction ? 'Save rule' : 'Create rule' }}</button>
        </div>
    </form>
</x-admin3.panel>

@if ($restriction)
    <x-admin3.confirm name="delete-restriction-{{ $method }}" title="Delete the availability rule?"
        message="Every condition above is removed. {{ $name }} becomes available everywhere."
        confirm="Delete rule" :action="route('admin.payment-restrictions.destroy', $restriction)" method="DELETE" />
@endif
