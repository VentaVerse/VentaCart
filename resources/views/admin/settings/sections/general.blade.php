<x-admin.settings-shell active="general" title="General">
    <div class="space-y-6">
        <x-admin.card title="Identity" description="Your store's name. Logo and favicon are managed in Theme.">
            <form method="POST" action="{{ route('admin.settings.update') }}" data-v-form class="space-y-5">
                @csrf
                <input type="hidden" name="group" value="identity">
                <input type="hidden" name="_redirect" value="{{ route('admin.settings.section', 'general') }}">
                <x-admin.field name="settings[site_name]" label="Site name">
                    <x-admin.input name="settings[site_name]" :value="$settings['identity']['site_name'] ?? ''" />
                </x-admin.field>
                <p class="text-xs text-v-muted">Logo and favicon are managed in <a href="{{ route('admin.theme.index') }}" class="text-v-accent hover:underline">Theme</a>.</p>
                <x-admin.form-actions>
                    <x-admin.button variant="primary" type="submit">Save identity</x-admin.button>
                </x-admin.form-actions>
            </form>
        </x-admin.card>

        <x-admin.card title="Defaults" description="Timezone and fallback SEO metadata used across the storefront.">
            <form method="POST" action="{{ route('admin.settings.update') }}" data-v-form class="space-y-5">
                @csrf
                <input type="hidden" name="group" value="general">
                <input type="hidden" name="_redirect" value="{{ route('admin.settings.section', 'general') }}">
                @php $currentTz = $settings['general']['timezone'] ?? config('app.timezone', 'Asia/Manila'); @endphp
                <x-admin.field name="settings[timezone]" label="Timezone">
                    <x-admin.select name="settings[timezone]">
                        @foreach (timezone_identifiers_list() as $tz)
                            <option value="{{ $tz }}" @selected($currentTz === $tz)>{{ $tz }}</option>
                        @endforeach
                    </x-admin.select>
                </x-admin.field>
                <x-admin.field name="settings[default_meta_title]" label="Default meta title" help="Used as the homepage title and fallback for pages without a specific meta title.">
                    <x-admin.input name="settings[default_meta_title]" :value="$settings['general']['default_meta_title'] ?? ''" :placeholder="$settings['identity']['site_name'] ?? 'My Store'" />
                </x-admin.field>
                <x-admin.field name="settings[default_meta_description]" label="Default meta description" help="Shown in search results for your homepage. Keep under 155 characters.">
                    <x-admin.textarea name="settings[default_meta_description]" rows="2" placeholder="Describe your store in 155 characters..." :value="$settings['general']['default_meta_description'] ?? ''" />
                </x-admin.field>
                <x-admin.field name="settings[default_meta_keywords]" label="Meta keywords" help="Comma-separated. Low impact on modern SEO but used by some search engines.">
                    <x-admin.input name="settings[default_meta_keywords]" placeholder="guitar pedals, music gear, Philippines" :value="$settings['general']['default_meta_keywords'] ?? ''" />
                </x-admin.field>
                <x-admin.field name="settings[google_site_verification]" label="Google site verification" help="The content value from your Google Search Console verification meta tag.">
                    <x-admin.input name="settings[google_site_verification]" placeholder="google-site-verification=..." class="font-mono" :value="$settings['general']['google_site_verification'] ?? ''" />
                </x-admin.field>
                <x-admin.form-actions>
                    <x-admin.button variant="primary" type="submit">Save defaults</x-admin.button>
                </x-admin.form-actions>
            </form>
        </x-admin.card>
    </div>
</x-admin.settings-shell>
