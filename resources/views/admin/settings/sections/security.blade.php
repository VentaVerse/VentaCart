<x-admin.settings-shell title="Security" active="security">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">Security</h1>
            <p class="v-head__standing">Where the admin lives, and the challenge in front of the public forms.</p>
        </div>
    </div>

    <x-admin.form :action="route('admin.settings.update')" class="v-settle">
        <input type="hidden" name="_redirect" value="{{ route('admin.settings.section', 'security') }}">
        <x-admin.panel title="Admin URL">
            <x-admin.field name="groups.security.admin_prefix" label="Admin path"
                hint="Lowercase letters, numbers, and hyphens, starting with a letter. Saving a new path redirects the whole admin there.">
                <div class="v-adorn">
                    <span class="v-adorn__fix" aria-hidden="true">/</span>
                    <input type="text" name="groups[security][admin_prefix]" class="v-control v-mono"
                           pattern="[a-z][a-z0-9\-]*" required
                           value="{{ $settings['security']['admin_prefix'] ?? 'admin' }}">
                </div>
            </x-admin.field>
        </x-admin.panel>

        <x-admin.panel title="Turnstile" note="Challenges login, registration, contact, and stock notification forms. Keys come from dash.cloudflare.com/turnstile.">
            <label class="v-toggle">
                <input type="hidden" name="groups[security][turnstile_enabled]" value="0">
                <input type="checkbox" name="groups[security][turnstile_enabled]" value="1"
                       @checked(($settings['security']['turnstile_enabled'] ?? '0') === '1')>
                <span class="v-toggle__track" aria-hidden="true"></span>
                Enable Turnstile
            </label>
            <div class="v-gap">
                <x-admin.field name="groups.security.turnstile_site_key" label="Site key">
                    <input type="text" name="groups[security][turnstile_site_key]" class="v-control v-mono"
                           placeholder="0x4AAAAAAA..." autocomplete="off"
                           value="{{ $settings['security']['turnstile_site_key'] ?? '' }}">
                </x-admin.field>
            </div>
            <div class="v-gap">
                <x-admin.field name="groups.security.turnstile_secret_key" label="Secret key">
                    <x-admin.secret name="groups[security][turnstile_secret_key]"
                        :masked="$maskedKeys['turnstile_secret_key'] ?? null"
                        :reveal-url="route('admin.settings.reveal-secret', ['group' => 'security', 'key' => 'turnstile_secret_key'])"
                        placeholder="Enter secret key" />
                </x-admin.field>
            </div>
        </x-admin.panel>
    </x-admin.form>
</x-admin.settings-shell>
