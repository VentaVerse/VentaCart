<div class="space-y-6">
    <x-admin.card title="Cash on Delivery">
        <x-slot name="headerActions">
            <form method="POST" action="{{ route('admin.settings.update') }}" data-v-form>
                @csrf
                <input type="hidden" name="group" value="payment">
                <input type="hidden" name="_redirect" value="{{ route('admin.payment-settings.show', 'cod') }}">
                <label class="flex items-center gap-2 text-[13px] text-v-secondary cursor-pointer">
                    <span>Enabled</span>
                    <x-admin.toggle name="settings[cod_enabled]"
                        :checked="($settings['payment']['cod_enabled'] ?? '0') === '1'"
                        x-on:change="$el.closest('form').submit()" />
                </label>
            </form>
        </x-slot>

        <form method="POST" action="{{ route('admin.settings.update') }}" data-v-form class="space-y-5">
            @csrf
            <input type="hidden" name="group" value="payment">
            <input type="hidden" name="_redirect" value="{{ route('admin.payment-settings.show', 'cod') }}">
            <x-admin.field name="settings[cod_display_name]" label="Checkout label" help="Shown at checkout. Leave blank for default.">
                <x-admin.input name="settings[cod_display_name]"
                    :value="$settings['payment']['cod_display_name'] ?? ''" placeholder="{{ $defaultName }}" />
            </x-admin.field>
            <x-admin.field name="settings[cod_success_status_id]" label="Order status on checkout">
                <x-admin.select name="settings[cod_success_status_id]" class="max-w-xs">
                    <option value="">Default (Pending)</option>
                    @foreach ($orderStatuses as $os)
                        <option value="{{ $os->id }}" @selected(($settings['payment']['cod_success_status_id'] ?? '') == $os->id)>{{ $os->name }}</option>
                    @endforeach
                </x-admin.select>
            </x-admin.field>
            <x-admin.field name="settings[cod_sort_order]" label="Sort order">
                <x-admin.input name="settings[cod_sort_order]" type="number" min="0"
                    :value="$settings['payment']['cod_sort_order'] ?? '0'" class="max-w-[8rem]" />
            </x-admin.field>
            <x-admin.form-actions>
                <x-admin.button variant="primary" type="submit">Save</x-admin.button>
            </x-admin.form-actions>
        </form>
    </x-admin.card>
</div>
