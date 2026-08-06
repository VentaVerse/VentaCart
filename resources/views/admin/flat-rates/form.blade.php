<x-admin.shell :title="$rate ? 'Edit Flat Rate' : 'New Flat Rate'">
    <x-admin.page :title="$rate ? 'Edit Flat Rate' : 'New Flat Rate'" :breadcrumb="[['label' => 'Settings', 'url' => route('admin.settings.index')], ['label' => 'Shipping', 'url' => route('admin.shipping-settings.index', ['tab' => 'flat-rate'])]]">
        <x-slot:actions>
            <x-admin.button variant="ghost" :href="route('admin.shipping-settings.index', ['tab' => 'flat-rate'])">Back to Shipping</x-admin.button>
        </x-slot:actions>

        <div class="max-w-xl">
            <x-admin.card>
                <form method="POST" action="{{ $rate ? route('admin.flat-rates.update', $rate) : route('admin.flat-rates.store') }}" data-v-form class="space-y-5">
                    @csrf
                    @if ($rate) @method('PUT') @endif

                    <x-admin.field name="name" label="Name" help="This name appears in checkout exactly as you type it">
                        <x-admin.input type="text" name="name" required :value="$rate?->name" placeholder="e.g. Standard Flat Rate, Store Pickup" />
                    </x-admin.field>

                    <x-admin.field name="cost" label="Cost (₱)" help="Set to 0 for free pickup or promotional methods">
                        <x-admin.input type="number" name="cost" step="0.01" min="0" required :value="$rate?->cost ?? '0.00'" class="w-40" />
                    </x-admin.field>

                    <div class="grid grid-cols-2 gap-4">
                        <x-admin.field name="geo_zone_id" label="Geo Zone">
                            <x-admin.select name="geo_zone_id">
                                <option value="">All zones</option>
                                @foreach ($geoZones as $zone)
                                    <option value="{{ $zone->id }}" {{ old('geo_zone_id', $rate?->geo_zone_id) == $zone->id ? 'selected' : '' }}>{{ $zone->name }}</option>
                                @endforeach
                            </x-admin.select>
                        </x-admin.field>
                        <x-admin.field name="customer_group_id" label="Customer Group">
                            <x-admin.select name="customer_group_id">
                                <option value="">All customers</option>
                                @foreach ($customerGroups as $group)
                                    <option value="{{ $group->id }}" {{ old('customer_group_id', $rate?->customer_group_id) == $group->id ? 'selected' : '' }}>{{ $group->name }}</option>
                                @endforeach
                            </x-admin.select>
                        </x-admin.field>
                    </div>

                    <div class="flex items-center gap-6">
                        <x-admin.field name="sort_order" label="Sort Order">
                            <x-admin.input type="number" name="sort_order" :value="$rate?->sort_order ?? 0" class="w-24" />
                        </x-admin.field>
                        <label class="flex items-center gap-2 cursor-pointer pt-5">
                            <x-admin.toggle name="is_active" :checked="$rate?->is_active ?? true" />
                            <span class="text-[13px] text-v-text">Active</span>
                        </label>
                    </div>

                    <x-admin.form-actions>
                        <x-admin.button variant="secondary" :href="route('admin.shipping-settings.index', ['tab' => 'flat-rate'])">Cancel</x-admin.button>
                        <x-admin.button type="submit">{{ $rate ? 'Update' : 'Create' }}</x-admin.button>
                    </x-admin.form-actions>
                </form>
            </x-admin.card>
        </div>
    </x-admin.page>
</x-admin.shell>
