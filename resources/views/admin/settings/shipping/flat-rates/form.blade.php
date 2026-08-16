<x-admin3.settings-shell :title="$rate ? 'Edit flat rate' : 'Add flat rate'" active="shipping">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.shipping-settings.index') }}">Shipping</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.shipping-settings.flat-rate') }}">Flat Rate</a>
                <span aria-hidden="true">/</span>
                <span>{{ $rate?->name ?? 'New' }}</span>
            </div>
            <h1 class="v3-head__title">{{ $rate ? ($rate->name ?: 'Edit flat rate') : 'Add flat rate' }}</h1>
        </div>
    </div>

    <x-admin3.form :action="$rate ? route('admin.flat-rates.update', $rate) : route('admin.flat-rates.store')"
                   :method="$rate ? 'PUT' : 'POST'" class="v3-settle">
        <x-admin3.panel title="Method">
            <div class="v3-narrow">
                <x-admin3.field name="name" label="Name" hint="Appears at checkout exactly as typed." required>
                    <input type="text" name="name" required class="v3-control" placeholder="e.g. Standard Flat Rate, Store Pickup"
                           value="{{ old('name', $rate?->name) }}">
                </x-admin3.field>
                <div class="v3-gap">
                    <x-admin3.field name="cost" label="Cost (₱)" hint="0 makes it free, for pickup or promos." required>
                        <input type="number" name="cost" step="0.01" min="0" required class="v3-control v3-mono v3-w-rate"
                               value="{{ old('cost', $rate?->cost ?? '0.00') }}">
                    </x-admin3.field>
                </div>
                <div class="v3-grid v3-grid--2 v3-gap">
                    <x-admin3.field name="geo_zone_id" label="Geo zone">
                        <select name="geo_zone_id" class="v3-control">
                            <option value="">All zones</option>
                            @foreach ($geoZones as $zone)
                                <option value="{{ $zone->id }}" @selected(old('geo_zone_id', $rate?->geo_zone_id) == $zone->id)>{{ $zone->name }}</option>
                            @endforeach
                        </select>
                    </x-admin3.field>
                    <x-admin3.field name="customer_group_id" label="Customer group">
                        <select name="customer_group_id" class="v3-control">
                            <option value="">All customers</option>
                            @foreach ($customerGroups as $group)
                                <option value="{{ $group->id }}" @selected(old('customer_group_id', $rate?->customer_group_id) == $group->id)>{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </x-admin3.field>
                </div>
                <div class="v3-gap v3-actrow">
                    <x-admin3.field name="sort_order" label="Sort order">
                        <input type="number" name="sort_order" class="v3-control v3-mono v3-w-sort" value="{{ old('sort_order', $rate?->sort_order ?? 0) }}">
                    </x-admin3.field>
                    <label class="v3-toggle">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $rate?->is_active ?? true))>
                        <span class="v3-toggle__track" aria-hidden="true"></span>
                        Active
                    </label>
                </div>
            </div>
        </x-admin3.panel>
    </x-admin3.form>
</x-admin3.settings-shell>
