<x-admin.settings-shell title="General" active="general">
    @php
        $vStoreName = \App\Support\StoreIdentity::name();
        $vMarks = [
            ['key' => 'logo', 'label' => 'Logo', 'help' => 'PNG, JPG or WebP, up to '.\App\Support\IdentityAsset::LOGO_MAX_KB.' KB.', 'accept' => 'image/png,image/jpeg,image/webp', 'url' => \App\Support\StoreIdentity::logoUrl()],
            ['key' => 'favicon', 'label' => 'Favicon', 'help' => 'Square PNG, ICO or SVG, up to '.\App\Support\IdentityAsset::FAVICON_MAX_KB.' KB.', 'accept' => 'image/png,image/svg+xml,image/x-icon,image/vnd.microsoft.icon,.ico,.svg', 'url' => \App\Support\StoreIdentity::faviconUrl()],
        ];
    @endphp

    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">General</h1>
            <p class="v-head__standing">The store's identity and the defaults every page falls back to.</p>
        </div>
    </div>

    <x-admin.form :action="route('admin.settings.identity.update')" enctype="multipart/form-data" class="v-settle">
        <x-admin.panel title="Identity">
            <x-admin.field name="site_name" label="Site name">
                <input type="text" name="site_name" class="v-control" value="{{ old('site_name', $settings['identity']['site_name'] ?? '') }}">
            </x-admin.field>

            <div class="v-grid v-grid--2 v-gap">
                @foreach ($vMarks as $mark)
                    <x-admin.field :name="$mark['key']" :label="$mark['label']" :hint="$mark['help']">
                        {{-- Removal happens in place: no navigation, so typed
                             edits elsewhere on the page survive it. Two-step
                             confirm; the second click within 4s executes. --}}
                        <div class="v-markrow v-gap-sm"
                             x-data="{ url: @js($mark['url']), arm: false, busy: false,
                                async remove() {
                                    if (!this.arm) { this.arm = true; setTimeout(() => this.arm = false, 4000); return; }
                                    this.busy = true;
                                    const r = await vPost(@js(route('admin.settings.identity.remove', $mark['key'])));
                                    if (r.ok) { this.url = null; }
                                    this.busy = false; this.arm = false;
                                } }">
                            <div class="v-markbox" :class="!url && 'v-markbox--empty'">
                                <template x-if="url">
                                    <img :src="url" alt="{{ $vStoreName }} {{ strtolower($mark['label']) }}">
                                </template>
                                <template x-if="!url">
                                    <span>None set</span>
                                </template>
                            </div>
                            <template x-if="url">
                                <button type="button" class="v-btn v-btn--sm"
                                        :class="arm ? 'v-btn--danger' : 'v-btn--ghost'"
                                        :disabled="busy"
                                        @click="remove()"
                                        x-text="arm ? 'Really remove?' : 'Remove {{ strtolower($mark['label']) }}'">Remove {{ strtolower($mark['label']) }}</button>
                            </template>
                        </div>
                        <input type="file" name="{{ $mark['key'] }}" accept="{{ $mark['accept'] }}" class="v-file">
                    </x-admin.field>
                @endforeach
            </div>
        </x-admin.panel>

        <x-admin.panel title="Defaults">
            @php $currentTz = $settings['general']['timezone'] ?? config('app.timezone', 'Asia/Manila'); @endphp
            <x-admin.field name="groups.general.timezone" label="Timezone">
                <select name="groups[general][timezone]" class="v-control">
                    @foreach (timezone_identifiers_list() as $tz)
                        <option value="{{ $tz }}" @selected($currentTz === $tz)>{{ $tz }}</option>
                    @endforeach
                </select>
            </x-admin.field>
            <x-admin.field name="groups.general.default_meta_title" label="Default meta title" hint="The homepage title and the fallback for pages without their own.">
                <input type="text" name="groups[general][default_meta_title]" class="v-control"
                       placeholder="{{ $settings['identity']['site_name'] ?? 'My Store' }}"
                       value="{{ $settings['general']['default_meta_title'] ?? '' }}">
            </x-admin.field>
            <x-admin.field name="groups.general.default_meta_description" label="Default meta description" hint="Shown in search results for the homepage. Keep under 155 characters.">
                <textarea name="groups[general][default_meta_description]" rows="2" class="v-control">{{ $settings['general']['default_meta_description'] ?? '' }}</textarea>
            </x-admin.field>
            <x-admin.field name="groups.general.default_meta_keywords" label="Meta keywords" hint="Comma separated. Low impact on modern search.">
                <input type="text" name="groups[general][default_meta_keywords]" class="v-control"
                      
                       value="{{ $settings['general']['default_meta_keywords'] ?? '' }}">
            </x-admin.field>
            <x-admin.field name="groups.general.google_site_verification" label="Google site verification" hint="The content value from the Search Console verification meta tag.">
                <input type="text" name="groups[general][google_site_verification]" class="v-control v-mono"
                      
                       value="{{ $settings['general']['google_site_verification'] ?? '' }}">
            </x-admin.field>
        </x-admin.panel>
    </x-admin.form>

</x-admin.settings-shell>
