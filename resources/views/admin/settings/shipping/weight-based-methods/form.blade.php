<x-admin3.settings-shell :title="$method ? 'Edit weight-based method' : 'Add weight-based method'" active="shipping">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.shipping-settings.index') }}">Shipping</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.shipping-settings.weight-based') }}">Weight Based</a>
                <span aria-hidden="true">/</span>
                <span>{{ $method?->admin_name ?: ($method?->name ?? 'New') }}</span>
            </div>
            <h1 class="v3-head__title">{{ $method ? ($method->admin_name ?: $method->name) : 'Add weight-based method' }}</h1>
        </div>
    </div>

    @php
        $ratesData = old('rates', []);
        if (empty($ratesData) && $method?->rates) {
            $ratesData = $method->rates->map(fn ($r) => ['weight_from' => $r->weight_from, 'weight_to' => $r->weight_to, 'cost' => $r->cost])->toArray();
        }
    @endphp

    <x-admin3.form :action="$method ? route('admin.weight-based-methods.update', $method) : route('admin.weight-based-methods.store')"
                   :method="$method ? 'PUT' : 'POST'" class="v3-settle">
      <div x-data="{
            rates: @js($ratesData),
            addRate() {
                const last = this.rates.length > 0 ? this.rates[this.rates.length - 1] : null;
                this.rates.push({
                    weight_from: last ? last.weight_to : '0.000',
                    weight_to: last ? (parseFloat(last.weight_to) + 5).toFixed(3) : '5.000',
                    cost: last ? last.cost : '0.00',
                });
            },
        }">
        <x-admin3.panel title="Method">
            <div class="v3-grid v3-grid--2">
                <x-admin3.field name="admin_name" label="Admin name" hint="Never shown to customers. Blank copies the checkout name.">
                    <input type="text" name="admin_name" class="v3-control"
                           value="{{ old('admin_name', $method?->admin_name) }}">
                </x-admin3.field>
                <x-admin3.field name="name" label="Checkout name" required>
                    <input type="text" name="name" required class="v3-control"
                           value="{{ old('name', $method?->name) }}">
                </x-admin3.field>
            </div>
            <div class="v3-gap v3-actrow">
                <x-admin3.field name="sort_order" label="Sort order">
                    <input type="number" name="sort_order" class="v3-control v3-mono v3-w-sort" value="{{ old('sort_order', $method?->sort_order ?? 0) }}">
                </x-admin3.field>
                <label class="v3-toggle">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $method?->is_active ?? true))>
                    <span class="v3-toggle__track" aria-hidden="true"></span>
                    Active
                </label>
            </div>
        </x-admin3.panel>

        <x-admin3.panel title="Weight tiers">
            <x-slot:acts>
                <button type="button" class="v3-btn v3-btn--secondary v3-btn--sm" @click="addRate(); touch()">
                    <x-admin3.icon name="plus" /> Add tier
                </button>
            </x-slot:acts>
            <template x-if="rates.length > 0">
                <div>
                    <template x-for="(rate, i) in rates" :key="i">
                        <div class="v3-row">
                            <input type="number" :name="'rates['+i+'][weight_from]'" x-model="rate.weight_from" step="0.001" min="0" aria-label="Weight from (kg)" class="v3-control v3-mono">
                            <input type="number" :name="'rates['+i+'][weight_to]'" x-model="rate.weight_to" step="0.001" min="0" aria-label="Weight to (kg)" class="v3-control v3-mono">
                            <input type="number" :name="'rates['+i+'][cost]'" x-model="rate.cost" step="0.01" min="0" aria-label="Cost (₱)" class="v3-control v3-mono">
                            <button type="button" class="v3-row__x" @click="rates.splice(i, 1); touch()" aria-label="Remove tier"><x-admin3.icon name="x" /></button>
                        </div>
                    </template>
                </div>
            </template>
            <template x-if="rates.length === 0">
                <div class="v3-empty">
                    <p class="v3-empty__title">No weight tiers yet</p>
                    <p class="v3-empty__line">The method quotes nothing until a tier covers the cart's weight.</p>
                </div>
            </template>
        </x-admin3.panel>

        <div class="v3-grid v3-grid--2 v3-gap">
            <x-admin3.panel title="Geo zones" note="None checked quotes for every zone.">
                @php $selectedGeoZones = collect(old('geo_zone_ids', $method?->geoZones?->pluck('id')->toArray() ?? []))->map(fn ($v) => (int) $v)->all(); @endphp
                <div class="v3-checklist">
                    @foreach ($geoZones as $zone)
                        <label class="v3-check">
                            <input type="checkbox" name="geo_zone_ids[]" value="{{ $zone->id }}" @checked(in_array($zone->id, $selectedGeoZones, true))>
                            {{ $zone->name }}
                        </label>
                    @endforeach
                    @if ($geoZones->isEmpty())
                        <p class="v3-cell-quiet">No geo zones configured.</p>
                    @endif
                </div>
            </x-admin3.panel>
            <x-admin3.panel title="Customer groups" note="None checked quotes for every customer.">
                @php $selectedGroups = collect(old('customer_group_ids', $method?->customerGroups?->pluck('id')->toArray() ?? []))->map(fn ($v) => (int) $v)->all(); @endphp
                <div class="v3-checklist">
                    @foreach ($customerGroups as $group)
                        <label class="v3-check">
                            <input type="checkbox" name="customer_group_ids[]" value="{{ $group->id }}" @checked(in_array($group->id, $selectedGroups, true))>
                            {{ $group->name }}
                        </label>
                    @endforeach
                </div>
            </x-admin3.panel>
        </div>
      </div>
    </x-admin3.form>
</x-admin3.settings-shell>
