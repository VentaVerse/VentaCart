<x-admin.form :header-save="false" :action="route('admin.settings.update')" id="pay-cod-form">
    <input type="hidden" name="group" value="payment">
    <input type="hidden" name="_redirect" value="{{ route('admin.payment-settings.show', 'cod') }}">

    <x-admin.panel title="Cash on Delivery">
        {{-- The enable toggle is its own submission; forms cannot nest,
             so its inputs reference the hidden sibling form by id. --}}
        <div class="v-narrow">
            <x-admin.field name="settings.cod_display_name" label="Checkout label" hint="Shown at checkout. Blank uses the default.">
                <input type="text" name="settings[cod_display_name]" class="v-control" placeholder="{{ $defaultName }}"
                       value="{{ $settings['payment']['cod_display_name'] ?? '' }}">
            </x-admin.field>
            <div class="v-gap">
                <x-admin.field name="settings.cod_success_status_id" label="Order status on checkout">
                    <select name="settings[cod_success_status_id]" class="v-control">
                        <option value="">Default (Pending)</option>
                        @foreach ($orderStatuses as $os)
                            <option value="{{ $os->id }}" @selected(($settings['payment']['cod_success_status_id'] ?? '') == $os->id)>{{ $os->name }}</option>
                        @endforeach
                    </select>
                </x-admin.field>
            </div>
            <div class="v-gap">
                <x-admin.field name="settings.cod_sort_order" label="Sort order">
                    <input type="number" name="settings[cod_sort_order]" min="0" class="v-control v-mono v-w-sort"
                           value="{{ $settings['payment']['cod_sort_order'] ?? '0' }}">
                </x-admin.field>
            </div>
        </div>
    </x-admin.panel>
</x-admin.form>

