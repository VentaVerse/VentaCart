<x-admin.shell :title="$rate ? 'Edit flat rate' : 'Add flat rate'">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.shipping-settings.index') }}">Shipping</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.shipping-settings.flat-rate') }}">Flat Rate</a>
                <span aria-hidden="true">/</span>
                <span>{{ $rate?->name ?? 'New' }}</span>
            </div>
            <h1 class="v-head__title">{{ $rate ? ($rate->name ?: 'Edit flat rate') : 'Add flat rate' }}</h1>
        </div>
    </div>

    <x-admin.form :action="$rate ? route('admin.flat-rates.update', $rate) : route('admin.flat-rates.store')"
                   :method="$rate ? 'PUT' : 'POST'" class="v-settle">
        <x-admin.panel title="Method">
            <div class="v-narrow">
                <x-admin.field name="name" label="Name" hint="Appears at checkout exactly as typed." required>
                    <input type="text" name="name" required class="v-control"
                           value="{{ old('name', $rate?->name) }}">
                </x-admin.field>
                <div class="v-gap">
                    <x-admin.field name="cost" label="Cost (₱)" hint="0 makes it free, for pickup or promos." required>
                        <input type="number" name="cost" step="0.01" min="0" required class="v-control v-mono v-w-rate"
                               value="{{ old('cost', $rate?->cost ?? '0.00') }}">
                    </x-admin.field>
                </div>
                <div class="v-grid v-grid--2 v-gap">
                    <x-admin.field name="geo_zone_id" label="Geo zone">
                        <select name="geo_zone_id" class="v-control">
                            <option value="">All zones</option>
                            @foreach ($geoZones as $zone)
                                <option value="{{ $zone->id }}" @selected(old('geo_zone_id', $rate?->geo_zone_id) == $zone->id)>{{ $zone->name }}</option>
                            @endforeach
                        </select>
                    </x-admin.field>
                    <x-admin.field name="customer_group_id" label="Customer group">
                        <select name="customer_group_id" class="v-control">
                            <option value="">All customers</option>
                            @foreach ($customerGroups as $group)
                                <option value="{{ $group->id }}" @selected(old('customer_group_id', $rate?->customer_group_id) == $group->id)>{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </x-admin.field>
                </div>
                <div class="v-gap v-actrow">
                    <x-admin.field name="sort_order" label="Sort order">
                        <input type="number" name="sort_order" class="v-control v-mono v-w-sort" value="{{ old('sort_order', $rate?->sort_order ?? 0) }}">
                    </x-admin.field>
                    <label class="v-toggle">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $rate?->is_active ?? true))>
                        <span class="v-toggle__track" aria-hidden="true"></span>
                        Active
                    </label>
                </div>
            </div>
        </x-admin.panel>
    </x-admin.form>
</x-admin.shell>
