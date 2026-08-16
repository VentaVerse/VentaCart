<x-admin3.settings-shell :title="$courier ? 'Edit ' . $courier->name : 'Add courier'" active="couriers">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.shipping-couriers.index') }}">Couriers & Tracking</a>
                <span aria-hidden="true">/</span>
                <span>{{ $courier?->name ?? 'New courier' }}</span>
            </div>
            <h1 class="v3-head__title">{{ $courier?->name ?? 'Add courier' }}</h1>
        </div>
    </div>

    <x-admin3.form :action="$courier ? route('admin.shipping-couriers.update', $courier) : route('admin.shipping-couriers.store')"
                   :method="$courier ? 'PUT' : 'POST'" class="v3-settle">
        <x-admin3.panel title="Courier">
            <div class="v3-grid v3-grid--2">
                <x-admin3.field name="name" label="Courier" required>
                    <input type="text" name="name" required class="v3-control"
                           value="{{ old('name', $courier?->name) }}">
                </x-admin3.field>
                <x-admin3.field name="sort_order" label="Sort order">
                    <input type="number" name="sort_order" min="0" class="v3-control v3-mono v3-w-sort"
                           value="{{ old('sort_order', $courier?->sort_order ?? 0) }}">
                </x-admin3.field>
            </div>
            <div class="v3-gap">
                <x-admin3.field name="tracking_url" label="Tracking URL template"
                    hint="Put {tracking_number} where the code belongs. Example: https://example.com/track/{tracking_number}">
                    <input type="text" name="tracking_url" class="v3-control v3-mono"
                           value="{{ old('tracking_url', $courier?->tracking_url) }}">
                </x-admin3.field>
            </div>
            <div class="v3-gap">
                <label class="v3-toggle">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $courier?->is_active ?? true))>
                    <span class="v3-toggle__track" aria-hidden="true"></span>
                    Active
                </label>
            </div>
        </x-admin3.panel>
    </x-admin3.form>
</x-admin3.settings-shell>
