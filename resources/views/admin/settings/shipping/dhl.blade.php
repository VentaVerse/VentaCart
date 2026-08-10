<x-admin.settings-shell title="DHL Express">
    <x-admin.card title="DHL Express">
        <div class="space-y-6">
            <form method="POST" action="{{ route('admin.settings.update') }}" data-v-form class="space-y-5 max-w-lg">
                @csrf
                <input type="hidden" name="group" value="shipping">
                <input type="hidden" name="_redirect" value="{{ route('admin.shipping-settings.dhl') }}">

                <x-admin.field name="settings[dhl_site_id]" label="Site ID"
                    :help="!empty($maskedKeys['dhl_site_id'] ?? null) ? 'Current key ends in ' . $maskedKeys['dhl_site_id'] . '. Leave blank to keep it.' : null">
                    <x-admin.input name="settings[dhl_site_id]" autocomplete="off" placeholder="Enter Site ID" />
                </x-admin.field>

                <x-admin.field name="settings[dhl_password]" label="Password"
                    :help="!empty($maskedKeys['dhl_password'] ?? null) ? 'Current password ends in ' . $maskedKeys['dhl_password'] . '. Leave blank to keep it.' : null">
                    <x-admin.input type="password" name="settings[dhl_password]" autocomplete="new-password" placeholder="Enter Password" />
                </x-admin.field>

                <x-admin.field name="settings[dhl_account_number]" label="Account number">
                    <x-admin.input name="settings[dhl_account_number]" :value="$settings['shipping']['dhl_account_number'] ?? ''" placeholder="e.g. 642344995" />
                </x-admin.field>

                <x-admin.form-actions>
                    <x-admin.button variant="primary" type="submit">Save credentials</x-admin.button>
                </x-admin.form-actions>
            </form>

            <div class="flex items-center gap-2 border-t border-v-border pt-5">
                <form method="POST" action="{{ route('admin.settings.test-dhl') }}" data-v-form>
                    @csrf
                    <x-admin.button variant="secondary" type="submit">Test connection</x-admin.button>
                </form>
                <x-admin.hint label="What the test does">Requests one live rate quote, Manila to Singapore, and reports what DHL answered.</x-admin.hint>
            </div>
        </div>
    </x-admin.card>
</x-admin.settings-shell>
