<x-admin3.settings-shell title="General" active="general">
    @php
        $vStoreName = \App\Support\StoreIdentity::name();
        $vMarks = [
            ['key' => 'logo', 'label' => 'Logo', 'help' => 'PNG, JPG or WebP, up to '.\App\Support\IdentityAsset::LOGO_MAX_KB.' KB.', 'accept' => 'image/png,image/jpeg,image/webp', 'url' => \App\Support\StoreIdentity::logoUrl()],
            ['key' => 'favicon', 'label' => 'Favicon', 'help' => 'Square PNG, ICO or SVG, up to '.\App\Support\IdentityAsset::FAVICON_MAX_KB.' KB.', 'accept' => 'image/png,image/svg+xml,image/x-icon,image/vnd.microsoft.icon,.ico,.svg', 'url' => \App\Support\StoreIdentity::faviconUrl()],
        ];
    @endphp

    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">General</h1>
            <p class="v3-head__standing">The store's identity and the defaults every page falls back to.</p>
        </div>
    </div>

    <x-admin3.form :action="route('admin.settings.identity.update')" enctype="multipart/form-data" class="v3-settle">
        <x-admin3.panel title="Identity">
            <x-admin3.field name="site_name" label="Site name">
                <input type="text" name="site_name" class="v3-control" value="{{ old('site_name', $settings['identity']['site_name'] ?? '') }}">
            </x-admin3.field>

            <div class="v3-grid v3-grid--2 v3-gap">
                @foreach ($vMarks as $mark)
                    <x-admin3.field :name="$mark['key']" :label="$mark['label']" :hint="$mark['help']">
                        <div class="v3-markrow v3-gap-sm">
                            <div class="v3-markbox {{ $mark['url'] ? '' : 'v3-markbox--empty' }}">
                                @if ($mark['url'])
                                    <img src="{{ $mark['url'] }}" alt="{{ $vStoreName }} {{ strtolower($mark['label']) }}">
                                @else
                                    None set
                                @endif
                            </div>
                            @if ($mark['url'])
                                <button form="remove-{{ $mark['key'] }}" type="submit" class="v3-btn v3-btn--ghost v3-btn--sm">Remove {{ strtolower($mark['label']) }}</button>
                            @endif
                        </div>
                        <input type="file" name="{{ $mark['key'] }}" accept="{{ $mark['accept'] }}" class="v3-file">
                    </x-admin3.field>
                @endforeach
            </div>
        </x-admin3.panel>

        <x-admin3.panel title="Defaults">
            @php $currentTz = $settings['general']['timezone'] ?? config('app.timezone', 'Asia/Manila'); @endphp
            <x-admin3.field name="groups.general.timezone" label="Timezone">
                <select name="groups[general][timezone]" class="v3-control">
                    @foreach (timezone_identifiers_list() as $tz)
                        <option value="{{ $tz }}" @selected($currentTz === $tz)>{{ $tz }}</option>
                    @endforeach
                </select>
            </x-admin3.field>
            <x-admin3.field name="groups.general.default_meta_title" label="Default meta title" hint="The homepage title and the fallback for pages without their own.">
                <input type="text" name="groups[general][default_meta_title]" class="v3-control"
                       placeholder="{{ $settings['identity']['site_name'] ?? 'My Store' }}"
                       value="{{ $settings['general']['default_meta_title'] ?? '' }}">
            </x-admin3.field>
            <x-admin3.field name="groups.general.default_meta_description" label="Default meta description" hint="Shown in search results for the homepage. Keep under 155 characters.">
                <textarea name="groups[general][default_meta_description]" rows="2" class="v3-control">{{ $settings['general']['default_meta_description'] ?? '' }}</textarea>
            </x-admin3.field>
            <x-admin3.field name="groups.general.default_meta_keywords" label="Meta keywords" hint="Comma separated. Low impact on modern search.">
                <input type="text" name="groups[general][default_meta_keywords]" class="v3-control"
                       placeholder="guitar pedals, music gear, Philippines"
                       value="{{ $settings['general']['default_meta_keywords'] ?? '' }}">
            </x-admin3.field>
            <x-admin3.field name="groups.general.google_site_verification" label="Google site verification" hint="The content value from the Search Console verification meta tag.">
                <input type="text" name="groups[general][google_site_verification]" class="v3-control v3-mono"
                       placeholder="google-site-verification=..."
                       value="{{ $settings['general']['google_site_verification'] ?? '' }}">
            </x-admin3.field>
        </x-admin3.panel>
    </x-admin3.form>

    {{-- Mark removal is an immediate action, not a save; forms cannot
         nest, so the Remove buttons reach these by id. --}}
    @foreach ($vMarks as $mark)
        @if ($mark['url'])
            <form id="remove-{{ $mark['key'] }}" method="POST" action="{{ route('admin.settings.identity.destroy', $mark['key']) }}">
                @csrf
                @method('DELETE')
            </form>
        @endif
    @endforeach
</x-admin3.settings-shell>
