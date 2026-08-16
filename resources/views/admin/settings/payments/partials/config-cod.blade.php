<x-admin3.form :action="route('admin.settings.update')">
    <input type="hidden" name="group" value="payment">
    <input type="hidden" name="_redirect" value="{{ route('admin.payment-settings.show', 'cod') }}">

    <x-admin3.panel title="Cash on Delivery">
        {{-- The enable toggle is its own submission; forms cannot nest,
             so its inputs reference the hidden sibling form by id. --}}
        <x-slot:acts>
            <input type="hidden" form="cod-enable-form" name="group" value="payment">
            <input type="hidden" form="cod-enable-form" name="_redirect" value="{{ route('admin.payment-settings.show', 'cod') }}">
            <input type="hidden" form="cod-enable-form" name="settings[cod_enabled]" value="{{ ($settings['payment']['cod_enabled'] ?? '0') === '1' ? 0 : 1 }}">
            <button type="submit" form="cod-enable-form" class="v3-toggle v3-toggle--btn" role="switch"
                    aria-checked="{{ ($settings['payment']['cod_enabled'] ?? '0') === '1' ? 'true' : 'false' }}"
                    aria-label="Cash on Delivery enabled">
                <span class="v3-toggle__track" aria-hidden="true"></span>
            </button>
        </x-slot:acts>
        <div class="v3-narrow">
            <x-admin3.field name="settings.cod_display_name" label="Checkout label" hint="Shown at checkout. Blank uses the default.">
                <input type="text" name="settings[cod_display_name]" class="v3-control" placeholder="{{ $defaultName }}"
                       value="{{ $settings['payment']['cod_display_name'] ?? '' }}">
            </x-admin3.field>
            <div class="v3-gap">
                <x-admin3.field name="settings.cod_success_status_id" label="Order status on checkout">
                    <select name="settings[cod_success_status_id]" class="v3-control">
                        <option value="">Default (Pending)</option>
                        @foreach ($orderStatuses as $os)
                            <option value="{{ $os->id }}" @selected(($settings['payment']['cod_success_status_id'] ?? '') == $os->id)>{{ $os->name }}</option>
                        @endforeach
                    </select>
                </x-admin3.field>
            </div>
            <div class="v3-gap">
                <x-admin3.field name="settings.cod_sort_order" label="Sort order">
                    <input type="number" name="settings[cod_sort_order]" min="0" class="v3-control v3-mono v3-w-sort"
                           value="{{ $settings['payment']['cod_sort_order'] ?? '0' }}">
                </x-admin3.field>
            </div>
        </div>
    </x-admin3.panel>
</x-admin3.form>

<form id="cod-enable-form" method="POST" action="{{ route('admin.settings.update') }}" hidden>
    @csrf
</form>
