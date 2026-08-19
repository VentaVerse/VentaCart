<x-admin.shell title="Theme">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <span>Theme</span>
            </div>
            <h1 class="v-head__title">Theme</h1>
            <p class="v-head__standing">How the storefront looks. Each section saves on its own.</p>
        </div>
        <div class="v-head__actions">
            <x-admin.extension-switch name="theme" />
        </div>
    </div>


    <div x-data="themeSections(@js(session('tab', request('tab', 'presets'))))"
         @keydown.escape.window="leaving = null"
         class="v-gap-lg">

        {{-- Asked at the switch, where the edits can still be saved, rather
             than by the browser on the way out of some later navigation. --}}
        <template x-if="leaving">
            <div class="v-overlay" @click.self="leaving = null">
                <div class="v-modal" role="dialog" aria-modal="true" aria-labelledby="v-theme-leave">
                    <h2 class="v-modal__title" id="v-theme-leave">Save this section first?</h2>
                    <p class="v-modal__body">This section has changes that have not been saved. Each section saves on its own, so moving on without saving loses them.</p>
                    <div class="v-modal__acts">
                        <button type="button" class="v-btn v-btn--ghost" @click="leaving = null">Stay here</button>
                        <button type="button" class="v-btn v-btn--danger" @click="discardAndGo()">Discard and move on</button>
                        <button type="button" class="v-btn v-btn--primary" @click="saveAndGo()">Save and move on</button>
                    </div>
                </div>
            </div>
        </template>

        <div class="v-modgrid">
        <nav class="v-snav" aria-label="Theme sections">
            @php
                // Grouped by subject, which only became honest once every
                // section saves itself: Header used to sit apart from
                // Navigation and Footer purely because it lived in a
                // different form. Presets leads, ungrouped: it is a
                // snapshot of everything below it, not a peer of it.
                $vThemeNav = [
                    '' => ['presets' => ['Presets', 'tags']],
                    'Look' => ['colors' => ['Colors', 'theme'], 'fonts' => ['Fonts', 'text'], 'badges' => ['Badges', 'check']],
                    'Layout' => ['header' => ['Header', 'menu'], 'navigation' => ['Navigation', 'link'], 'footer' => ['Footer', 'layers']],
                    'Pages' => ['homepage' => ['Homepage', 'home'], 'product_page' => ['Product page', 'products'], 'category_page' => ['Category page', 'folder'], 'blog' => ['Blog', 'content']],
                    'Advanced' => ['theme-settings' => ['Theme options', 'settings']],
                ];
            @endphp
            @foreach ($vThemeNav as $vGroup => $vItems)
                @if ($vGroup !== '')<div class="v-snav__group">{{ $vGroup }}</div>@endif
                @foreach ($vItems as $tid => [$tlabel, $ticon])
                    <button type="button" class="v-snav__item" :aria-current="tab === @js($tid) ? 'page' : false" @click="pick(@js($tid))">
                        <x-admin.icon :name="$ticon" />
                        {{ $tlabel }}
                    </button>
                @endforeach
            @endforeach
        </nav>
        <div class="v-modmain">

        {{-- ========== PRESETS ========== --}}
        <div x-show="tab === 'presets'" x-cloak>
            <x-admin.panel title="Presets" flush>
                <x-slot:acts>
                    <button type="button" class="v-btn v-btn--secondary v-btn--sm"
                            onclick="document.getElementById('preset-import-form').querySelector('input[type=file]').click()">Import preset</button>
                </x-slot:acts>
                <div class="v-tablewrap">
                    <table class="v-table">
                        <thead>
                            <tr><th>Preset</th><th>Palette</th><th class="v-cell-act"><span class="v-sr">Actions</span></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($installedPresets as $slug => $meta)
                                <tr>
                                    <td>
                                        <span class="v-namerow">
                                            <strong>{{ $meta['name'] ?? $slug }}</strong>
                                            @if ($slug === $activePreset)
                                                <span class="v-badge v-badge--good">Live</span>
                                            @endif
                                        </span>
                                        <span class="v-cell-quiet v-block">{{ $meta['description'] ?? '' }}</span>
                                    </td>
                                    <td>
                                        <span class="v-palrow">
                                            @foreach (['bg', 'primary', 'secondary', 'text_heading', 'card_bg'] as $chipKey)
                                                @if (!empty($meta['colors'][$chipKey]))
                                                    {{-- The color is a record's own data, carried as an SVG
                                                         presentation attribute rather than a stylesheet rule. --}}
                                                    <svg class="v-palchip" viewBox="0 0 10 10" role="img" aria-label="{{ $chipKey }} {{ $meta['colors'][$chipKey] }}"><title>{{ $chipKey }}: {{ $meta['colors'][$chipKey] }}</title><rect width="10" height="10" rx="3" fill="{{ $meta['colors'][$chipKey] }}"/></svg>
                                                @endif
                                            @endforeach
                                        </span>
                                    </td>
                                    <td class="v-cell-act">
                                        @if ($slug !== $activePreset)
                                            <button type="button" class="v-btn v-btn--secondary v-btn--sm"
                                                    @click="$dispatch('v-confirm', 'apply-preset-{{ $slug }}')">Apply</button>
                                        @endif
                                        <a href="{{ route('admin.theme.presets.export', $slug) }}" class="v-btn v-btn--ghost v-btn--sm">Export</a>
                                        @if ($slug !== $activePreset)
                                            <button type="button" class="v-btn v-btn--danger v-btn--sm"
                                                    @click="$dispatch('v-confirm', 'delete-preset-{{ $slug }}')">Delete</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-admin.panel>

            @foreach ($installedPresets as $slug => $meta)
                @if ($slug !== $activePreset)
                    <x-admin.confirm name="apply-preset-{{ $slug }}" title="Switch to {{ $meta['name'] ?? $slug }}?"
                        message="Shoppers see the new look immediately. Colors, fonts, and options saved for it are restored; the current look's are kept for switching back."
                        confirm="Switch look" :danger="false"
                        :action="route('admin.theme.switch')" method="POST">
                        <input type="hidden" name="preset" value="{{ $slug }}">
                    </x-admin.confirm>
                    <x-admin.confirm name="delete-preset-{{ $slug }}" title="Delete {{ $meta['name'] ?? $slug }}?"
                        message="The preset file and its saved edits are removed. The live look is untouched."
                        confirm="Delete"
                        :action="route('admin.theme.presets.destroy', $slug)" method="DELETE" />
                @endif
            @endforeach

            {{-- Capture the settings as they stand into a new named preset. --}}
            <x-admin.panel title="New preset">
                <form method="POST" action="{{ route('admin.theme.presets.store') }}" class="v-addrow v-addrow--3">
                    @csrf
                    <input type="text" name="name" required maxlength="60" placeholder="Preset name" aria-label="Preset name" class="v-control">
                    <input type="text" name="description" maxlength="200" placeholder="Description" aria-label="Preset description" class="v-control">
                    <button type="submit" class="v-btn v-btn--primary">Save as preset</button>
                </form>
            </x-admin.panel>

            <form id="preset-import-form" method="POST" action="{{ route('admin.theme.presets.import') }}" enctype="multipart/form-data" hidden>
                @csrf
                <input type="file" name="preset_file" accept="application/json,.json" aria-label="Preset JSON file" onchange="this.form.submit()">
            </form>
        </div>


            {{-- ========== COLORS ========== --}}
            <div x-show="tab === 'colors'">
            <x-admin.form :header-save="false" :action="route('admin.theme.settings.update')">
                <x-admin.panel title="Colors">
                    <x-slot:acts>
                        <button type="button" class="v-btn v-btn--danger v-btn--sm" @click="$dispatch('v-confirm', 'revert-colors')">Revert colors &amp; fonts to defaults</button>
                    </x-slot:acts>
                    @php
                        $colorGroups = \Extensions\Theme\ColorGroups::all();
                    @endphp
                    <div class="v-wells">
                        @foreach ($colorGroups as $groupLabel => $fields)
                            <div class="v-well">
                                <div class="v-label v-gap-sm">{{ $groupLabel }}</div>
                                @foreach ($fields as $key => $label)
                                    <div class="v-colorrow">
                                        <label for="color-{{ $key }}" class="v-colorrow__label">{{ $label }}</label>
                                        <span class="v-colorrow__pair">
                                            <input type="color" value="{{ $settings['colors'][$key] ?? '#000000' }}" class="v-swatch"
                                                   aria-label="{{ $label }} color picker"
                                                   oninput="this.nextElementSibling.value = this.value; this.nextElementSibling.dispatchEvent(new Event('input', { bubbles: true }))">
                                            <input type="text" id="color-{{ $key }}" name="groups[colors][{{ $key }}]"
                                                   value="{{ $settings['colors'][$key] ?? '#000000' }}" placeholder="#000000"
                                                   class="v-control v-mono v-w-hex"
                                                   oninput="this.previousElementSibling.value = this.value">
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </x-admin.panel>
            </x-admin.form>
            </div>

            {{-- ========== FONTS ========== --}}
            <div x-show="tab === 'fonts'" x-cloak
                 x-data="{
                    headingFont: @js($settings['fonts']['heading'] ?? 'Oswald'),
                    bodyFont: @js($settings['fonts']['body'] ?? 'Roboto'),
                    loadPreviewFont(font) {
                        if (!document.querySelector('link[data-font=\'' + font + '\']')) {
                            const link = document.createElement('link');
                            link.rel = 'stylesheet';
                            link.dataset.font = font;
                            link.href = 'https://fonts.googleapis.com/css2?family=' + encodeURIComponent(font) + ':wght@400;600;700&display=swap';
                            document.head.appendChild(link);
                        }
                    }
                 }">
            <x-admin.form :header-save="false" :action="route('admin.theme.settings.update')">
                <x-admin.panel title="Fonts">
                    @php
                        $fontOptions = ['Anton','Archivo','Barlow','Bebas Neue','Bitter','Bricolage Grotesque','Cormorant Garamond','DM Sans','DM Serif Display','Figtree','Fira Sans','IBM Plex Sans','Inter','Josefin Sans','Karla','Lato','Libre Baskerville','Libre Franklin','Lora','Merriweather','Montserrat','Nunito','Nunito Sans','Open Sans','Oswald','Playfair Display','Poppins','PT Sans','PT Serif','Rajdhani','Raleway','Roboto','Roboto Slab','Rubik','Saira','Saira Condensed','Source Sans 3','Space Grotesk','Space Mono','Spline Sans','Spline Sans Mono','Ubuntu','Work Sans'];
                        $sizeFields = [
                            'size_logo' => ['label' => 'Logo / Store Name', 'default' => '32', 'min' => 18, 'max' => 60],
                            'size_section_title' => ['label' => 'Section Titles', 'default' => '24', 'min' => 16, 'max' => 48],
                            'size_product_name' => ['label' => 'Product Name (card)', 'default' => '14', 'min' => 11, 'max' => 20],
                            'size_price' => ['label' => 'Product Price', 'default' => '16', 'min' => 12, 'max' => 24],
                            'size_body' => ['label' => 'Body Text', 'default' => '14', 'min' => 12, 'max' => 20],
                            'size_nav' => ['label' => 'Navigation Menu', 'default' => '14', 'min' => 11, 'max' => 20],
                        ];
                    @endphp
                    <div class="v-grid v-grid--2">
                        <div>
                            <x-admin.field name="groups.fonts.heading" label="Heading font">
                                <select name="groups[fonts][heading]" class="v-control" x-model="headingFont" @change="loadPreviewFont(headingFont)">
                                    @foreach ($fontOptions as $font)
                                        <option value="{{ $font }}" @selected(($settings['fonts']['heading'] ?? '') === $font)>{{ $font }}</option>
                                    @endforeach
                                </select>
                            </x-admin.field>
                            <div class="v-gap">
                                <x-admin.field name="groups.fonts.body" label="Body font">
                                    <select name="groups[fonts][body]" class="v-control" x-model="bodyFont" @change="loadPreviewFont(bodyFont)">
                                        @foreach ($fontOptions as $font)
                                            <option value="{{ $font }}" @selected(($settings['fonts']['body'] ?? '') === $font)>{{ $font }}</option>
                                        @endforeach
                                    </select>
                                </x-admin.field>
                            </div>
                            <div class="v-gap">
                                <div class="v-label v-gap-sm">Letter spacing <x-admin.help text="CSS letter-spacing for the nav links and section headings, e.g. 0.05em. 0 is normal." /></div>
                                <div class="v-grid v-grid--2">
                                    <x-admin.field name="groups.vars.track_nav" label="Navigation">
                                        <input type="text" name="groups[vars][track_nav]" value="{{ $settings['vars']['track_nav'] ?? '0' }}" placeholder="0" class="v-control v-mono">
                                    </x-admin.field>
                                    <x-admin.field name="groups.vars.track_heading" label="Headings">
                                        <input type="text" name="groups[vars][track_heading]" value="{{ $settings['vars']['track_heading'] ?? '0' }}" placeholder="0" class="v-control v-mono">
                                    </x-admin.field>
                                </div>
                            </div>
                            <div class="v-gap">
                                <div class="v-label v-gap-sm">Font sizes</div>
                                @foreach ($sizeFields as $key => $cfg)
                                    <div class="v-colorrow">
                                        <label for="size-{{ $key }}" class="v-colorrow__label">{{ $cfg['label'] }}</label>
                                        <span class="v-colorrow__pair">
                                            <input type="number" id="size-{{ $key }}" name="groups[fonts][{{ $key }}]"
                                                   value="{{ $settings['fonts'][$key] ?? $cfg['default'] }}"
                                                   min="{{ $cfg['min'] }}" max="{{ $cfg['max'] }}"
                                                   class="v-control v-mono v-w-hex">
                                            <span class="v-cell-quiet">px</span>
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div>
                            <div class="v-label v-gap-sm">Preview</div>
                            <div class="v-well">
                                <div :style="'font-family: \'' + headingFont + '\', sans-serif'">
                                    <div class="v-preview-h" x-text="headingFont">Heading font</div>
                                    <div class="v-preview-sub">Section Title Example</div>
                                    <div class="v-preview-price">₱12,990.00</div>
                                </div>
                                <hr class="v-irail__rule v-preview-rule">
                                <div :style="'font-family: \'' + bodyFont + '\', sans-serif'">
                                    <div class="v-preview-body" x-text="bodyFont + ': body font'">Body font</div>
                                    <p class="v-preview-copy">This is how product descriptions, paragraphs, and general content read on the storefront. The quick brown fox jumps over the lazy dog.</p>
                                    <p class="v-preview-hint">Hints and small descriptions appear like this.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-admin.panel>
            </x-admin.form>
            </div>

            {{-- ========== HEADER ========== --}}
            <div x-show="tab === 'header'" x-cloak>
            <x-admin.form :header-save="false" :action="route('admin.theme.settings.update')">
                <x-admin.panel title="Announcement bar">
                    <label class="v-toggle">
                        <input type="hidden" name="groups[header][announcement_enabled]" value="0">
                        <input type="checkbox" name="groups[header][announcement_enabled]" value="1"
                               @checked(($settings['header']['announcement_enabled'] ?? '0') === '1')>
                        <span class="v-toggle__track" aria-hidden="true"></span>
                        Show the announcement bar
                    </label>
                    <div class="v-grid v-grid--2 v-gap">
                        <x-admin.field name="groups.header.announcement_text" label="Announcement text">
                            <input type="text" name="groups[header][announcement_text]" class="v-control" value="{{ $settings['header']['announcement_text'] ?? '' }}">
                        </x-admin.field>
                        <x-admin.field name="groups.header.announcement_link" label="Announcement link">
                            <input type="text" name="groups[header][announcement_link]" class="v-control v-mono" placeholder="/page-url or https://…" value="{{ $settings['header']['announcement_link'] ?? '' }}">
                        </x-admin.field>
                    </div>
                </x-admin.panel>

                <x-admin.panel title="Top bar links">
                    <div x-data="{ links: @js(json_decode($settings['header']['topbar_links'] ?? '[]', true) ?: []) }">
                        <template x-for="(link, idx) in links" :key="idx">
                            <div class="v-row">
                                <input type="text" x-model="link.label" aria-label="Topbar link label" placeholder="Label" class="v-control">
                                <input type="text" x-model="link.url" aria-label="Topbar link URL" placeholder="/url" class="v-control v-mono">
                                <button type="button" class="v-row__x" @click="links.splice(idx, 1); touch()" aria-label="Remove link">
                                    <x-admin.icon name="trash" />
                                </button>
                            </div>
                        </template>
                        <div class="v-gap">
                            <button type="button" class="v-btn v-btn--secondary v-btn--sm" @click="links.push({label:'',url:''}); touch()">
                                <x-admin.icon name="plus" /> Add link
                            </button>
                        </div>
                        <input type="hidden" name="groups[header][topbar_links]" :value="JSON.stringify(links)">
                    </div>
                </x-admin.panel>
            </x-admin.form>
            </div>

            {{-- ========== PRODUCT PAGE ========== --}}
            <div x-show="tab === 'product_page'" x-cloak>
            <x-admin.form :header-save="false" :action="route('admin.theme.settings.update')">
                <x-admin.panel title="Product page">
                    @foreach (['show_ratings' => 'Show star ratings', 'show_stock' => 'Show stock and availability', 'show_brand' => 'Show the brand logo', 'show_badges' => 'Show product badges'] as $key => $label)
                        <div class="{{ $loop->first ? '' : 'v-gap' }}">
                            <label class="v-toggle">
                                <input type="hidden" name="groups[product_page][{{ $key }}]" value="0">
                                <input type="checkbox" name="groups[product_page][{{ $key }}]" value="1"
                                       @checked(($settings['product_page'][$key] ?? '0') === '1')>
                                <span class="v-toggle__track" aria-hidden="true"></span>
                                {{ $label }}
                            </label>
                        </div>
                    @endforeach
                    <div class="v-gap v-narrow">
                        <x-admin.field name="groups.product_page.thumbnail_position" label="Thumbnail position">
                            <select name="groups[product_page][thumbnail_position]" class="v-control">
                                <option value="left" @selected(($settings['product_page']['thumbnail_position'] ?? 'left') === 'left')>Left</option>
                                <option value="below" @selected(($settings['product_page']['thumbnail_position'] ?? '') === 'below')>Below</option>
                            </select>
                        </x-admin.field>
                    </div>
                </x-admin.panel>
            </x-admin.form>
            </div>

            {{-- ========== CATEGORY PAGE ========== --}}
            <div x-show="tab === 'category_page'" x-cloak>
            <x-admin.form :header-save="false" :action="route('admin.theme.settings.update')">
                <x-admin.panel title="Category page">
                    <div class="v-narrow">
                        <x-admin.field name="groups.category_page.products_per_page" label="Products per page">
                            <input type="number" name="groups[category_page][products_per_page]" min="4" max="60" class="v-control v-mono v-w-rate" value="{{ $settings['category_page']['products_per_page'] ?? '12' }}">
                        </x-admin.field>
                    </div>
                    <div class="v-grid v-grid--3 v-gap">
                        <x-admin.field name="groups.category_page.columns_mobile" label="Mobile columns">
                            <input type="number" name="groups[category_page][columns_mobile]" min="1" max="3" class="v-control v-mono" value="{{ $settings['category_page']['columns_mobile'] ?? '2' }}">
                        </x-admin.field>
                        <x-admin.field name="groups.category_page.columns_tablet" label="Tablet columns">
                            <input type="number" name="groups[category_page][columns_tablet]" min="2" max="4" class="v-control v-mono" value="{{ $settings['category_page']['columns_tablet'] ?? '3' }}">
                        </x-admin.field>
                        <x-admin.field name="groups.category_page.columns_desktop" label="Desktop columns">
                            <input type="number" name="groups[category_page][columns_desktop]" min="3" max="8" class="v-control v-mono" value="{{ $settings['category_page']['columns_desktop'] ?? '4' }}">
                        </x-admin.field>
                    </div>
                    <div class="v-gap v-narrow">
                        <x-admin.field name="groups.category_page.default_sort" label="Default sort">
                            <select name="groups[category_page][default_sort]" class="v-control">
                                @foreach (['default' => 'Default', 'name_asc' => 'Name A-Z', 'name_desc' => 'Name Z-A', 'price_asc' => 'Price low to high', 'price_desc' => 'Price high to low', 'newest' => 'Newest'] as $v => $label)
                                    <option value="{{ $v }}" @selected(($settings['category_page']['default_sort'] ?? '') === $v)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-admin.field>
                    </div>
                </x-admin.panel>

                <x-admin.panel title="Sidebar filters">
                    @foreach (['price_enabled' => 'Price range', 'availability_enabled' => 'Availability', 'brand_enabled' => 'Brand', 'rating_enabled' => 'Rating', 'on_sale_enabled' => 'On sale', 'tags_enabled' => 'Tags'] as $key => $label)
                        <div class="{{ $loop->first ? '' : 'v-gap' }}">
                            <label class="v-toggle">
                                <input type="hidden" name="groups[filters][{{ $key }}]" value="0">
                                <input type="checkbox" name="groups[filters][{{ $key }}]" value="1"
                                       @checked(($settings['filters'][$key] ?? '0') === '1')>
                                <span class="v-toggle__track" aria-hidden="true"></span>
                                {{ $label }}
                            </label>
                        </div>
                    @endforeach
                </x-admin.panel>
            </x-admin.form>
            </div>

            {{-- ========== BADGES ========== --}}
            <div x-show="tab === 'badges'" x-cloak>
            <x-admin.form :header-save="false" :action="route('admin.theme.settings.update')">
                <x-admin.panel title="Badges">
                    <div class="v-narrow">
                        <x-admin.field name="groups.badges.new_count" label="NEW badge: last X products" hint="Products added most recently. 0 disables it.">
                            <input type="number" name="groups[badges][new_count]" min="0" max="100" class="v-control v-mono v-w-rate" value="{{ $settings['badges']['new_count'] ?? '10' }}">
                        </x-admin.field>
                        <div class="v-gap">
                            <x-admin.field name="groups.badges.hot_count" label="HOT badge: top X best sellers" hint="Products with the most orders. 0 disables it.">
                                <input type="number" name="groups[badges][hot_count]" min="0" max="100" class="v-control v-mono v-w-rate" value="{{ $settings['badges']['hot_count'] ?? '5' }}">
                            </x-admin.field>
                        </div>
                    </div>
                </x-admin.panel>
            </x-admin.form>
            </div>

            {{-- ========== BLOG ========== --}}
            <div x-show="tab === 'blog'" x-cloak>
            <x-admin.form :header-save="false" :action="route('admin.theme.settings.update')">
                <x-admin.panel title="Blog">
                    <div class="v-narrow">
                        <x-admin.field name="groups.blog.posts_per_page" label="Posts per page">
                            <input type="number" name="groups[blog][posts_per_page]" min="4" max="48" class="v-control v-mono v-w-rate" value="{{ $settings['blog']['posts_per_page'] ?? '12' }}">
                        </x-admin.field>
                        <div class="v-gap">
                            <x-admin.field name="groups.blog.default_view" label="Default view">
                                <select name="groups[blog][default_view]" class="v-control">
                                    <option value="grid" @selected(($settings['blog']['default_view'] ?? 'grid') === 'grid')>Grid</option>
                                    <option value="list" @selected(($settings['blog']['default_view'] ?? '') === 'list')>List</option>
                                </select>
                            </x-admin.field>
                        </div>
                        <div class="v-gap">
                            <x-admin.field name="groups.blog.excerpt_length" label="Excerpt length (characters)">
                                <input type="number" name="groups[blog][excerpt_length]" min="50" max="500" class="v-control v-mono v-w-rate" value="{{ $settings['blog']['excerpt_length'] ?? '150' }}">
                            </x-admin.field>
                        </div>
                        <div class="v-gap">
                            <label class="v-toggle">
                                <input type="hidden" name="groups[blog][show_view_count]" value="0">
                                <input type="checkbox" name="groups[blog][show_view_count]" value="1" @checked(($settings['blog']['show_view_count'] ?? '0') === '1')>
                                <span class="v-toggle__track" aria-hidden="true"></span>
                                Show view counts
                            </label>
                        </div>
                        <div class="v-gap">
                            <label class="v-toggle">
                                <input type="hidden" name="groups[blog][show_related_articles]" value="0">
                                <input type="checkbox" name="groups[blog][show_related_articles]" value="1" @checked(($settings['blog']['show_related_articles'] ?? '0') === '1')>
                                <span class="v-toggle__track" aria-hidden="true"></span>
                                Show related articles on product pages
                            </label>
                        </div>
                    </div>
                </x-admin.panel>
            </x-admin.form>
            </div>

        <x-admin.confirm name="revert-colors" title="Revert to theme defaults?"
            message="Reverts BOTH colors and fonts to this theme's defaults. Unsaved edits on those tabs are lost."
            confirm="Revert" :action="route('admin.theme.revert-colors')" method="POST" />

        {{-- ========== NAVIGATION (records) ========== --}}
        <div x-show="tab === 'navigation'" x-cloak>
            <x-admin.panel title="Menu items">
                <div x-init="initSortable($el.querySelector('[data-sortable]'), @js(route('admin.theme.nav.reorder')))">
                    <div data-sortable>
                        @foreach ($navItems as $item)
                            <div class="v-dragrow" draggable="true" data-id="{{ $item->id }}">
                                <x-admin.icon name="menu" class="v-dragrow__grip drag-handle" />
                                <span class="v-dir__body">
                                    <span class="v-dir__name">{{ $item->label }}</span>
                                    <span class="v-dir__line v-block">{{ $item->type === 'category' ? 'Category: ' . ($item->category?->name ?? 'N/A') : 'Custom: ' . $item->url }}</span>
                                </span>
                                <span class="v-badge {{ $item->is_enabled ? 'v-badge--good' : 'v-badge--quiet' }}">{{ $item->is_enabled ? 'Active' : 'Disabled' }}</span>
                                <a href="{{ route('admin.theme.nav.edit', $item) }}" class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                                <button type="button" class="v-row__x" aria-label="Delete {{ $item->label }}" @click="$dispatch('v-confirm', 'delete-nav-{{ $item->id }}')">
                                    <x-admin.icon name="trash" />
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="v-gap-lg" x-data="{ newType: 'category' }">
                    <div class="v-label v-gap-sm">Add menu item</div>
                    <form method="POST" action="{{ route('admin.theme.nav.store') }}">
                        @csrf
                        <div class="v-grid v-grid--2 v-narrow">
                            <x-admin.field name="type" label="Type" required>
                                <select name="type" x-model="newType" class="v-control">
                                    <option value="category">Category</option>
                                    <option value="custom">Custom link</option>
                                </select>
                            </x-admin.field>
                            <x-admin.field name="label" label="Label" required>
                                <input type="text" name="label" required placeholder="Menu label" class="v-control">
                            </x-admin.field>
                        </div>
                        <div class="v-gap v-narrow" x-show="newType === 'category'">
                            <x-admin.field name="category_id" label="Category">
                                <select name="category_id" class="v-control">
                                    <option value="">Select…</option>
                                    @foreach ($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            </x-admin.field>
                        </div>
                        <div class="v-gap v-narrow" x-show="newType === 'custom'" x-cloak>
                            <x-admin.field name="url" label="URL">
                                <input type="text" name="url" placeholder="/blog or https://…" class="v-control v-mono">
                            </x-admin.field>
                        </div>
                        <div class="v-gap v-actrow">
                            <label class="v-check">
                                <input type="checkbox" name="new_tab" value="1">
                                Open in a new tab
                            </label>
                            <button type="submit" class="v-btn v-btn--primary">Add item</button>
                        </div>
                    </form>
                </div>
            </x-admin.panel>

            @foreach ($navItems as $item)
                <x-admin.confirm name="delete-nav-{{ $item->id }}" title="Delete {{ $item->label }}?"
                    message="The menu item leaves the storefront header immediately."
                    confirm="Delete" :action="route('admin.theme.nav.destroy', $item)" method="DELETE" />
            @endforeach
        </div>

        {{-- ========== HOMEPAGE (records) ========== --}}
        <div x-show="tab === 'homepage'" x-cloak>
            <x-admin.panel title="Homepage sections">
                <div x-init="initSortable($el.querySelector('[data-sortable]'), @js(route('admin.theme.sections.reorder')))">
                    <div data-sortable>
                        @foreach ($sections as $section)
                            <div class="v-dragrow" draggable="true" data-id="{{ $section->id }}">
                                <x-admin.icon name="menu" class="v-dragrow__grip drag-handle" />
                                <span class="v-badge v-badge--quiet">{{ $section->type }}</span>
                                <span class="v-dir__name">{{ $section->title }}</span>
                                <span class="v-dragrow__end">
                                    <span class="v-badge {{ $section->is_enabled ? 'v-badge--good' : 'v-badge--quiet' }}">{{ $section->is_enabled ? 'Active' : 'Disabled' }}</span>
                                    <a href="{{ route('admin.theme.sections.edit', $section) }}" class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                                    <button type="button" class="v-row__x" aria-label="Delete {{ $section->title }}" @click="$dispatch('v-confirm', 'delete-section-{{ $section->id }}')">
                                        <x-admin.icon name="trash" />
                                    </button>
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="v-gap-lg">
                    <div class="v-label v-gap-sm">Add section</div>
                    <form method="POST" action="{{ route('admin.theme.sections.store') }}" class="v-addrow v-addrow--3">
                        @csrf
                        <x-admin.field name="type" label="Type" required>
                            <select name="type" class="v-control">
                                @foreach (['banner' => 'Banner/Slider', 'products' => 'Products Carousel', 'brands' => 'Brands Carousel', 'recently_viewed' => 'Recently Viewed', 'category_tabs' => 'Category Tabs', 'text_block' => 'Text Block', 'blog_posts' => 'Blog Posts'] as $v => $label)
                                    <option value="{{ $v }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-admin.field>
                        <x-admin.field name="title" label="Title" required>
                            <input type="text" name="title" required placeholder="Section title" class="v-control">
                        </x-admin.field>
                        <div class="v-addrow__opts">
                            <button type="submit" class="v-btn v-btn--primary">Add section</button>
                        </div>
                    </form>
                </div>
            </x-admin.panel>

            @foreach ($sections as $section)
                <x-admin.confirm name="delete-section-{{ $section->id }}" title="Delete {{ $section->title }}?"
                    message="The section leaves the homepage immediately."
                    confirm="Delete" :action="route('admin.theme.sections.destroy', $section)" method="DELETE" />
            @endforeach
        </div>

        {{-- ========== FOOTER (records, bulk) ========== --}}
        <div x-show="tab === 'footer'" x-cloak>
            <x-admin.panel title="Footer columns">
                <div x-init="initSortable($el.querySelector('[data-sortable]'), @js(route('admin.theme.footer.reorder')))">
                    <div data-sortable>
                        @foreach ($footerColumns as $col)
                            <div class="v-dragrow" draggable="true" data-id="{{ $col->id }}">
                                <x-admin.icon name="menu" class="v-dragrow__grip drag-handle" />
                                <span class="v-badge v-badge--quiet">{{ $col->type }}</span>
                                <span class="v-dir__name">{{ $col->title }}</span>
                                <span class="v-dragrow__end">
                                    <span class="v-badge {{ $col->is_enabled ? 'v-badge--good' : 'v-badge--quiet' }}">{{ $col->is_enabled ? 'Active' : 'Disabled' }}</span>
                                    <a href="{{ route('admin.theme.footer.edit', $col) }}" class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                                    <button type="button" class="v-row__x" @click="$dispatch('v-confirm', 'delete-footer-col-{{ $col->id }}')" aria-label="Delete {{ $col->title }} footer column">
                                        <x-admin.icon name="trash" />
                                    </button>
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="v-gap-lg">
                    <div class="v-label v-gap-sm">Add footer column</div>
                    <form method="POST" action="{{ route('admin.theme.footer.store') }}" class="v-addrow v-addrow--3">
                        @csrf
                        <x-admin.field name="type" label="Type" required>
                            <select name="type" class="v-control">
                                <option value="links">Links</option>
                                <option value="payment_icons">Payment icons</option>
                                <option value="social">Social links</option>
                                <option value="text">Free text</option>
                            </select>
                        </x-admin.field>
                        <x-admin.field name="title" label="Title" required>
                            <input type="text" name="title" required class="v-control">
                        </x-admin.field>
                        <div class="v-addrow__opts">
                            <button type="submit" class="v-btn v-btn--primary">Add column</button>
                        </div>
                    </form>
                </div>
            </x-admin.panel>

            @foreach ($footerColumns as $col)
                <x-admin.confirm name="delete-footer-col-{{ $col->id }}" title="Delete {{ $col->title }}?"
                    message="The column leaves the storefront footer immediately."
                    confirm="Delete" :action="route('admin.theme.footer.destroy', $col)" method="DELETE" />
            @endforeach
        </div>

        {{-- ========== THEME OPTIONS (per-theme schema) ========== --}}
        <div x-show="tab === 'theme-settings'" x-cloak>
            <x-admin.panel title="Theme options" note="Settings this theme's own manifest declares.">
                @if (count($themeSettingsSchema) > 0)
                    <x-admin.form :header-save="false" :action="route('admin.theme.update-theme-settings')">
                        <div class="v-narrow">
                            @foreach ($themeSettingsSchema as $key => $schema)
                                <div class="{{ $loop->first ? '' : 'v-gap' }}">
                                    <x-admin.field name="theme_settings.{{ $key }}" :label="$schema['label'] ?? $key">
                                        @if (($schema['type'] ?? '') === 'layout')
                                            <x-admin.layout-pick :kind="$key" name="theme_settings[{{ $key }}]"
                                                :options="$schema['options'] ?? []"
                                                :value="$themeSettingValues[$key] ?? ($schema['default'] ?? '')" />
                                        @elseif (($schema['type'] ?? '') === 'select')
                                            <select name="theme_settings[{{ $key }}]" class="v-control">
                                                @foreach ($schema['options'] ?? [] as $option)
                                                    <option value="{{ $option }}" @selected(($themeSettingValues[$key] ?? ($schema['default'] ?? '')) == $option)>{{ ucfirst($option) }}</option>
                                                @endforeach
                                            </select>
                                        @elseif (($schema['type'] ?? '') === 'toggle')
                                            <label class="v-toggle">
                                                <input type="hidden" name="theme_settings[{{ $key }}]" value="0">
                                                <input type="checkbox" name="theme_settings[{{ $key }}]" value="1" @checked(($themeSettingValues[$key] ?? ($schema['default'] ?? false)) ? true : false)>
                                                <span class="v-toggle__track" aria-hidden="true"></span>
                                                Enabled
                                            </label>
                                        @elseif (($schema['type'] ?? '') === 'color')
                                            <input type="color" name="theme_settings[{{ $key }}]" value="{{ $themeSettingValues[$key] ?? ($schema['default'] ?? '#000000') }}" class="v-swatch v-swatch--lg">
                                        @elseif (($schema['type'] ?? '') === 'number')
                                            <input type="number" name="theme_settings[{{ $key }}]" value="{{ $themeSettingValues[$key] ?? ($schema['default'] ?? '') }}" class="v-control v-mono v-w-rate">
                                        @else
                                            <input type="text" name="theme_settings[{{ $key }}]" value="{{ $themeSettingValues[$key] ?? ($schema['default'] ?? '') }}" class="v-control">
                                        @endif
                                    </x-admin.field>
                                </div>
                            @endforeach
                        </div>
                    </x-admin.form>
                @else
                    <div class="v-empty">
                        <p class="v-empty__title">This theme has no configurable options</p>
                    </div>
                @endif
            </x-admin.panel>
        </div>
        </div>
        </div>
    </div>

    @push('scripts')
    <script>
        /**
         * The section menu, and the one thing it has to protect against.
         *
         * Each section is its own save scope now, so leaving one with
         * unsaved edits abandons them. Before the split that was
         * impossible: seven sections shared a form, and one Save carried
         * all of them. Now the browser would catch it, but only at the
         * worst possible moment, as an unstyled "Leave site?" on the way
         * out of an unrelated save. So the switch is what asks, in the
         * app, while the edits are still recoverable.
         */
        function themeSections(initial) {
            return {
                tab: initial,
                leaving: null,          // the section a dirty switch is headed for

                // The visible section's form, if it has one (Presets and
                // the record editors do not carry a doctrine form).
                currentForm() {
                    const pane = this.$el.querySelector('[x-show*="\'' + this.tab + '\'"]');
                    return pane ? pane.querySelector('form[x-data]') : null;
                },
                isDirty() {
                    const f = this.currentForm();
                    try { return !!(f && Alpine.$data(f).dirty); } catch (e) { return false; }
                },

                pick(t) {
                    if (t !== this.tab && this.isDirty()) { this.leaving = t; return; }
                    this.go(t);
                },
                go(t) {
                    this.leaving = null;
                    this.tab = t;
                    const u = new URL(window.location);
                    u.searchParams.set('tab', t);
                    history.replaceState(null, '', u);
                },

                // Save first, then land on the section that was asked for:
                // the URL is rewritten BEFORE submitting so the server's
                // back() returns to the new section rather than this one.
                saveAndGo() {
                    const f = this.currentForm();
                    const t = this.leaving;
                    const u = new URL(window.location);
                    u.searchParams.set('tab', t);
                    history.replaceState(null, '', u);
                    this.leaving = null;
                    f.requestSubmit();
                },
                // Discard reloads, for the same reason the save doctrine
                // does: the server's copy is the only clean state worth
                // restoring.
                discardAndGo() {
                    const u = new URL(window.location);
                    u.searchParams.set('tab', this.leaving);
                    window.location.href = u.toString();
                },
            };
        }

        function initSortable(container, reorderUrl) {
            if (!container) return;
            let dragEl = null;

            container.addEventListener('dragstart', function (e) {
                const item = e.target.closest('[data-id]');
                if (!item) return;
                dragEl = item;
                item.classList.add('is-dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', item.dataset.id);
            });

            container.addEventListener('dragover', function (e) {
                e.preventDefault();
                if (!dragEl) return;
                const target = e.target.closest('[data-id]');
                if (!target || target === dragEl) return;
                const rect = target.getBoundingClientRect();
                if (e.clientY < rect.top + rect.height / 2) target.before(dragEl);
                else target.after(dragEl);
            });

            container.addEventListener('drop', function (e) {
                e.preventDefault();
                if (!dragEl) return;
                const order = [...container.querySelectorAll(':scope > [data-id]')]
                    .map((el, idx) => ({ id: parseInt(el.dataset.id), sort_order: idx }));
                fetch(reorderUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ order }),
                }).then(r => {
                    window.Alpine?.store('toasts')?.push(r.ok ? 'Order saved.' : 'Could not save the new order.', r.ok ? 'good' : 'bad');
                });
            });

            container.addEventListener('dragend', function () {
                if (dragEl) dragEl.classList.remove('is-dragging');
                dragEl = null;
            });
        }
    </script>
    @endpush
</x-admin.shell>
