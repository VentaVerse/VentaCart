<x-admin.settings-shell title="Checkout">
    <div class="space-y-6">
        <x-admin.card title="Checkout">
            <form method="POST" action="{{ route('admin.settings.update') }}" data-v-form class="space-y-5">
                @csrf
                <input type="hidden" name="group" value="checkout">
                <input type="hidden" name="_redirect" value="{{ route('admin.settings.section', 'checkout') }}">
                <x-admin.field name="settings[guest_allowed]">
                    <x-admin.toggle name="settings[guest_allowed]" label="Allow guest checkout"
                        :checked="($settings['checkout']['guest_allowed'] ?? '0') === '1'" />
                </x-admin.field>
                <x-admin.field name="settings[block_out_of_stock]" help="A cart that went out of stock after the item was added is blocked too.">
                    <x-admin.toggle name="settings[block_out_of_stock]" label="Block checkout for out-of-stock items"
                        :checked="($settings['checkout']['block_out_of_stock'] ?? '1') === '1'" />
                </x-admin.field>
                <x-admin.form-actions>
                    <x-admin.button variant="primary" type="submit">Save checkout settings</x-admin.button>
                </x-admin.form-actions>
            </form>
        </x-admin.card>

        <x-admin.card title="Low-stock alerts">
            <form method="POST" action="{{ route('admin.settings.update') }}" data-v-form class="space-y-5">
                @csrf
                <input type="hidden" name="group" value="stock">
                <input type="hidden" name="_redirect" value="{{ route('admin.settings.section', 'checkout') }}">
                <x-admin.field name="settings[low_stock_alert_enabled]">
                    <x-admin.toggle name="settings[low_stock_alert_enabled]" label="Enable low stock email alerts"
                        :checked="($settings['stock']['low_stock_alert_enabled'] ?? '0') === '1'" />
                </x-admin.field>
                <x-admin.field name="settings[low_stock_threshold]" label="Stock threshold" help="Alert when a product's stock drops to or below this number.">
                    <x-admin.input name="settings[low_stock_threshold]" type="number" min="1" max="100" class="w-32" :value="$settings['stock']['low_stock_threshold'] ?? 5" />
                </x-admin.field>
                <x-admin.field name="settings[low_stock_alert_email]" label="Send alerts to" help="Leave blank to use admin email.">
                    <x-admin.input name="settings[low_stock_alert_email]" type="email" :placeholder="auth()->user()->email" :value="$settings['stock']['low_stock_alert_email'] ?? ''" />
                </x-admin.field>
                <x-admin.form-actions>
                    <x-admin.button variant="primary" type="submit">Save low-stock alerts</x-admin.button>
                </x-admin.form-actions>
            </form>
        </x-admin.card>
    </div>
</x-admin.settings-shell>
