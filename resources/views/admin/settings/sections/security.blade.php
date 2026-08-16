<x-admin3.settings-shell title="Security" active="security">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Security</h1>
            <p class="v3-head__standing">Where the admin lives, and the challenge in front of the public forms.</p>
        </div>
    </div>

    <x-admin3.form :action="route('admin.settings.update')" class="v3-settle">
        <x-admin3.panel title="Admin URL">
            <x-admin3.field name="groups.security.admin_prefix" label="Admin path"
                hint="Lowercase letters, numbers, and hyphens, starting with a letter. Saving a new path redirects the whole admin there.">
                <div class="v3-adorn">
                    <span class="v3-adorn__fix" aria-hidden="true">/</span>
                    <input type="text" name="groups[security][admin_prefix]" class="v3-control v3-mono"
                           pattern="[a-z][a-z0-9\-]*" required
                           value="{{ $settings['security']['admin_prefix'] ?? 'admin' }}">
                </div>
            </x-admin3.field>
        </x-admin3.panel>

        <x-admin3.panel title="Turnstile" note="Challenges login, registration, contact, and stock notification forms. Keys come from dash.cloudflare.com/turnstile.">
            <label class="v3-toggle">
                <input type="hidden" name="groups[security][turnstile_enabled]" value="0">
                <input type="checkbox" name="groups[security][turnstile_enabled]" value="1"
                       @checked(($settings['security']['turnstile_enabled'] ?? '0') === '1')>
                <span class="v3-toggle__track" aria-hidden="true"></span>
                Enable Turnstile
            </label>
            <div class="v3-gap">
                <x-admin3.field name="groups.security.turnstile_site_key" label="Site key">
                    <input type="text" name="groups[security][turnstile_site_key]" class="v3-control v3-mono"
                           placeholder="0x4AAAAAAA..." autocomplete="off"
                           value="{{ $settings['security']['turnstile_site_key'] ?? '' }}">
                </x-admin3.field>
            </div>
            <div class="v3-gap">
                <x-admin3.field name="groups.security.turnstile_secret_key" label="Secret key">
                    <x-admin3.secret name="groups[security][turnstile_secret_key]"
                        :masked="$maskedKeys['turnstile_secret_key'] ?? null"
                        :value="$secretKeys['turnstile_secret_key'] ?? null"
                        placeholder="Enter secret key" />
                </x-admin3.field>
            </div>
        </x-admin3.panel>
    </x-admin3.form>
</x-admin3.settings-shell>
