<x-admin.settings-shell title="General">
    <div class="space-y-6">
        @php
            // Resolved, not read raw: a settings row can outlive its file, and
            // StoreIdentity is the one place that stats the disk. A path with
            // nothing behind it reads here exactly as it reads in the slab.
            $vStoreName = \App\Support\StoreIdentity::name();
            $vMarks = [
                [
                    'key' => 'logo',
                    'label' => 'Logo',
                    'help' => 'PNG, JPG or WebP, up to '.\App\Support\IdentityAsset::LOGO_MAX_KB.' KB.',
                    'accept' => 'image/png,image/jpeg,image/webp',
                    'url' => \App\Support\StoreIdentity::logoUrl(),
                    // A wordmark is wide; an icon is square. The preview is
                    // shaped like the thing it holds so neither is squeezed.
                    'box' => 'h-14 w-32',
                ],
                [
                    'key' => 'favicon',
                    'label' => 'Favicon',
                    'help' => 'Square PNG, ICO or SVG, up to '.\App\Support\IdentityAsset::FAVICON_MAX_KB.' KB.',
                    'accept' => 'image/png,image/svg+xml,image/x-icon,image/vnd.microsoft.icon,.ico,.svg',
                    'url' => \App\Support\StoreIdentity::faviconUrl(),
                    'box' => 'h-14 w-14',
                ],
            ];
        @endphp
        <x-admin.card title="Identity">
            <form method="POST" action="{{ route('admin.settings.identity.update') }}" enctype="multipart/form-data" data-v-form class="space-y-5">
                @csrf
                <x-admin.field name="site_name" label="Site name">
                    <x-admin.input name="site_name" :value="$settings['identity']['site_name'] ?? ''" />
                </x-admin.field>

                <div class="grid gap-5 sm:grid-cols-2">
                    @foreach ($vMarks as $mark)
                        <x-admin.field :name="$mark['key']" :label="$mark['label']" :help="$mark['help']">
                            <div class="space-y-2.5">
                                <div class="flex items-center gap-3">
                                    {{-- Plated when a mark is present, for the same reason the
                                         slab plates it: a dark-ink logo on a dark preview is
                                         no preview at all. The empty state keeps the sunken
                                         tile, whose text follows the theme. --}}
                                    <div class="{{ $mark['box'] }} shrink-0 overflow-hidden border border-v-border {{ $mark['url'] ? 'v-logo-plate' : 'grid place-items-center rounded-v-md bg-v-sunken' }}">
                                        @if ($mark['url'])
                                            <img src="{{ $mark['url'] }}" alt="{{ $vStoreName }} {{ strtolower($mark['label']) }}" class="max-h-full max-w-full object-contain">
                                        @else
                                            <span class="text-[11px] leading-tight text-v-secondary">None set</span>
                                        @endif
                                    </div>
                                    @if ($mark['url'])
                                        <x-admin.button form="remove-{{ $mark['key'] }}" type="submit" variant="ghost" size="sm" class="-ml-1.5">
                                            Remove {{ strtolower($mark['label']) }}
                                        </x-admin.button>
                                    @endif
                                </div>
                                <input type="file" name="{{ $mark['key'] }}" id="{{ $mark['key'] }}" accept="{{ $mark['accept'] }}"
                                       class="block w-full cursor-pointer text-xs text-v-secondary file:mr-2 file:cursor-pointer file:rounded-v-sm file:border-0 file:bg-v-accent-weak file:px-2 file:py-1 file:text-xs file:font-medium file:text-v-accent hover:file:bg-v-hover">
                            </div>
                        </x-admin.field>
                    @endforeach
                </div>

                <x-admin.form-actions>
                    <x-admin.button variant="primary" type="submit">Save identity</x-admin.button>
                </x-admin.form-actions>
            </form>

            {{-- Removal is its own submission; forms cannot nest, so the
                 buttons above reach these by id. --}}
            @foreach ($vMarks as $mark)
                @if ($mark['url'])
                    <form id="remove-{{ $mark['key'] }}" method="POST" action="{{ route('admin.settings.identity.destroy', $mark['key']) }}" data-v-form class="hidden">
                        @csrf
                        @method('DELETE')
                    </form>
                @endif
            @endforeach
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
