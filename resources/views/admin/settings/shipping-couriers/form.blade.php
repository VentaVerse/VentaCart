<x-admin.settings-shell :title="$courier ? 'Edit ' . $courier->name : 'Add courier'" active="couriers">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.shipping-couriers.index') }}">Couriers & Tracking</a>
                <span aria-hidden="true">/</span>
                <span>{{ $courier?->name ?? 'New courier' }}</span>
            </div>
            <h1 class="v-head__title">{{ $courier?->name ?? 'Add courier' }}</h1>
        </div>
    </div>

    <x-admin.form :action="$courier ? route('admin.shipping-couriers.update', $courier) : route('admin.shipping-couriers.store')"
                   :method="$courier ? 'PUT' : 'POST'" class="v-settle">
        <x-admin.panel title="Courier">
            <div class="v-grid v-grid--2">
                <x-admin.field name="name" label="Courier" required>
                    <input type="text" name="name" required class="v-control"
                           value="{{ old('name', $courier?->name) }}">
                </x-admin.field>
                <x-admin.field name="sort_order" label="Sort order">
                    <input type="number" name="sort_order" min="0" class="v-control v-mono v-w-sort"
                           value="{{ old('sort_order', $courier?->sort_order ?? 0) }}">
                </x-admin.field>
            </div>
            <div class="v-gap">
                <x-admin.field name="tracking_url" label="Tracking URL template"
                    hint="Put {tracking_number} where the code belongs. Example: https://example.com/track/{tracking_number}">
                    <input type="text" name="tracking_url" class="v-control v-mono"
                           value="{{ old('tracking_url', $courier?->tracking_url) }}">
                </x-admin.field>
            </div>
            <div class="v-gap">
                <label class="v-toggle">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $courier?->is_active ?? true))>
                    <span class="v-toggle__track" aria-hidden="true"></span>
                    Active
                </label>
            </div>
        </x-admin.panel>
    </x-admin.form>
</x-admin.settings-shell>
