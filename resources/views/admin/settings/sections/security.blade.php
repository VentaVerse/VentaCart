<x-admin.settings-shell title="Security">
    <div class="space-y-6">
        <x-admin.card title="Admin URL" description="Change the admin panel URL path for security.">
            <form method="POST" action="{{ route('admin.settings.update') }}" data-v-form class="space-y-5">
                @csrf
                <input type="hidden" name="group" value="security">
                <input type="hidden" name="_redirect" value="{{ route('admin.settings.section', 'security') }}">
                <x-admin.field name="settings[admin_prefix]" label="Admin path"
                    help="Current: /{{ $settings['security']['admin_prefix'] ?? 'admin' }}. Lowercase letters, numbers, and hyphens only, starting with a letter. You'll be redirected to the new URL after saving.">
                    <div class="flex items-center gap-1">
                        <span class="text-[13px] text-v-secondary">/</span>
                        <x-admin.input name="settings[admin_prefix]" class="font-mono" pattern="[a-z][a-z0-9\-]*" required
                            :value="$settings['security']['admin_prefix'] ?? 'admin'" placeholder="admin" />
                    </div>
                </x-admin.field>
                <x-admin.form-actions>
                    <x-admin.button variant="primary" type="submit">Save admin URL</x-admin.button>
                </x-admin.form-actions>
            </form>
        </x-admin.card>

        <x-admin.card title="Turnstile" description="Protects login, registration, contact, and stock notification forms from bots. Get your keys at dash.cloudflare.com/turnstile.">
            <form method="POST" action="{{ route('admin.settings.update') }}" data-v-form class="space-y-5">
                @csrf
                <input type="hidden" name="group" value="security">
                <input type="hidden" name="_redirect" value="{{ route('admin.settings.section', 'security') }}">
                <x-admin.field name="settings[turnstile_enabled]">
                    <label class="flex items-center gap-3 text-[13px] text-v-text cursor-pointer">
                        <x-admin.toggle name="settings[turnstile_enabled]" :checked="($settings['security']['turnstile_enabled'] ?? '0') === '1'" />
                        Enable Turnstile
                    </label>
                </x-admin.field>
                <x-admin.field name="settings[turnstile_site_key]" label="Site key">
                    <x-admin.input name="settings[turnstile_site_key]" class="font-mono" placeholder="0x4AAAAAAA..." :value="$settings['security']['turnstile_site_key'] ?? ''" />
                </x-admin.field>
                <x-admin.field name="settings[turnstile_secret_key]" label="Secret key"
                    :help="!empty($maskedKeys['turnstile_secret_key'] ?? null) ? 'Current: '.$maskedKeys['turnstile_secret_key'].'. Leave blank to keep it.' : 'Blank keeps no key set.'">
                    <x-admin.input name="settings[turnstile_secret_key]" type="password" autocomplete="new-password" class="font-mono"
                        :placeholder="!empty($maskedKeys['turnstile_secret_key'] ?? null) ? 'Leave blank to keep current' : 'Enter secret key'" />
                </x-admin.field>
                <x-admin.form-actions>
                    <x-admin.button variant="primary" type="submit">Save Turnstile</x-admin.button>
                </x-admin.form-actions>
            </form>
        </x-admin.card>
    </div>
</x-admin.settings-shell>
