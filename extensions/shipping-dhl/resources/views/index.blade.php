<x-admin.shell title="DHL Express">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.shipping-settings.index') }}">Shipping</a>
                <span aria-hidden="true">/</span>
                <span>DHL Express</span>
            </div>
            <h1 class="v-head__title">DHL Express</h1>
        </div>
        <div class="v-head__actions">
            <x-admin.extension-switch name="shipping-dhl" />
        </div>
    </div>

    <x-admin.form :action="route('admin.settings.update')" class="v-settle">
        <input type="hidden" name="group" value="shipping">
        <input type="hidden" name="_redirect" value="{{ route('admin.shipping-settings.dhl') }}">

        <x-admin.panel title="Credentials">
            <div class="v-narrow">
                <x-admin.field name="settings.dhl_site_id" label="Site ID"
                    :hint="!empty($maskedKeys['dhl_site_id'] ?? null) ? 'The current key ends in ' . $maskedKeys['dhl_site_id'] . '. Blank keeps it.' : null">
                    <input type="text" name="settings[dhl_site_id]" autocomplete="off" class="v-control v-mono" placeholder="Enter Site ID">
                </x-admin.field>
                <div class="v-gap">
                    <x-admin.field name="settings.dhl_password" label="Password"
                        :hint="!empty($maskedKeys['dhl_password'] ?? null) ? 'The current password ends in ' . $maskedKeys['dhl_password'] . '. Blank keeps it.' : null">
                        <input type="password" name="settings[dhl_password]" autocomplete="new-password" class="v-control v-mono" placeholder="Enter Password">
                    </x-admin.field>
                </div>
                <div class="v-gap">
                    <x-admin.field name="settings.dhl_account_number" label="Account number">
                        <input type="text" name="settings[dhl_account_number]" class="v-control v-mono"
                               value="{{ $settings['shipping']['dhl_account_number'] ?? '' }}">
                    </x-admin.field>
                </div>
            </div>
        </x-admin.panel>

        <x-admin.panel title="Test connection" note="Requests one live rate quote (Manila to Singapore) with the SAVED credentials, so save first.">
            <div x-data="{ testing: false }">
                <button type="button" class="v-btn v-btn--secondary" :disabled="testing"
                        @click="testing = true; vPost(@js(route('admin.settings.test-dhl'))).finally(() => testing = false)">
                    <span x-text="testing ? 'Testing…' : 'Test connection'">Test connection</span>
                </button>
            </div>
        </x-admin.panel>
    </x-admin.form>
</x-admin.shell>
