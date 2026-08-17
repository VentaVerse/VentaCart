<x-admin3.settings-shell title="Theme" active="theme">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Theme</h1>
            <p class="v3-head__standing">How the storefront looks. One save commits every settings tab below.</p>
        </div>
    </div>

    <x-admin3.panel title="Active look" :note="$installedPresets[$activePreset]['description'] ?? ''">
        <x-slot:acts>
            <form method="POST" action="{{ route('admin.theme.switch') }}" id="v3-theme-switch" class="v3-actrow">
                @csrf
                <select name="preset" aria-label="Storefront look" class="v3-control v3-w-name">
                    @foreach ($installedPresets as $slug => $meta)
                        <option value="{{ $slug }}" @selected($slug === $activePreset)>{{ $meta['name'] ?? $slug }}</option>
                    @endforeach
                </select>
                <button type="button" class="v3-btn v3-btn--secondary v3-btn--sm" @click="$dispatch('v3-confirm', 'switch-theme')">Switch</button>
            </form>
        </x-slot:acts>
        <p class="v3-cell-quiet">{{ $installedPresets[$activePreset]['name'] ?? 'No preset' }} is live on the storefront now.</p>
    </x-admin3.panel>

    {{-- The switch restyles the live storefront the moment it lands. --}}
    <div x-data="{ open: false }" x-on:v3-confirm.window="if ($event.detail === 'switch-theme') open = true" x-on:keydown.escape.window="open = false">
        <template x-if="open">
            <div class="v3-overlay" @click.self="open = false">
                <div class="v3-modal" role="dialog" aria-modal="true" aria-labelledby="v3c-switch">
                    <h2 class="v3-modal__title" id="v3c-switch">Switch the storefront look?</h2>
                    <p class="v3-modal__body">Shoppers see the new look immediately. Colors, fonts, and options saved for it are restored; the current look's are kept for switching back.</p>
                    <div class="v3-modal__acts">
                        <button type="button" class="v3-btn v3-btn--ghost" @click="open = false">Cancel</button>
                        <button type="submit" form="v3-theme-switch" class="v3-btn v3-btn--primary">Switch look</button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <div x-data="{ tab: @js(session('tab', request('tab', 'colors'))),
            pick(t) { this.tab = t; const u = new URL(window.location); u.searchParams.set('tab', t); history.replaceState(null, '', u); } }"
         class="v3-gap-lg">
        <div class="v3-tabs v3-tabs--wrap" role="tablist">
            @foreach (['colors' => 'Colors', 'fonts' => 'Fonts', 'header' => 'Header', 'navigation' => 'Navigation', 'homepage' => 'Homepage', 'product_page' => 'Product page', 'category_page' => 'Category page', 'badges' => 'Badges', 'footer' => 'Footer', 'blog' => 'Blog', 'theme-settings' => 'Theme options'] as $tid => $tlabel)
                <button type="button" class="v3-tab" role="tab" :aria-selected="tab === @js($tid) ? 'true' : 'false'" @click="pick(@js($tid))">{{ $tlabel }}</button>
            @endforeach
        </div>

        {{-- ============ THE ONE SETTINGS SAVE ============
             Every settings tab lives in this form; the record tabs
             (navigation, homepage, footer, theme options) are sibling
             panels below with their own record-scoped forms. --}}
        <x-admin3.form :action="route('admin.theme.settings.update')" id="v3-theme-form">

            {{-- ========== COLORS ========== --}}
            <div x-show="tab === 'colors'">
                <x-admin3.panel title="Colors">
                    <x-slot:acts>
                        <button type="button" class="v3-btn v3-btn--danger v3-btn--sm" @click="$dispatch('v3-confirm', 'revert-colors')">Revert colors &amp; fonts to defaults</button>
                    </x-slot:acts>
                    @php
                        $colorGroups = [
                            'Storefront' => ['primary' => 'Buttons & Links', 'secondary' => 'Sale Badges & Accents', 'btn_text' => 'Text on Buttons', 'price' => 'Product Prices'],
                            'Product Cards' => ['badge_new' => '"New" Badge', 'badge_hot' => '"Hot" Badge', 'badge_free_shipping' => '"Free Shipping" Badge', 'star_rating' => 'Star Ratings', 'sale_price' => 'Sale Price', 'card_hover' => 'Card Hover Effect'],
                            'Page' => ['bg' => 'Page Background', 'card_bg' => 'Product Card Background', 'border' => 'Lines & Dividers', 'input_bg' => 'Form Field Background', 'input_border' => 'Form Field Borders', 'breadcrumb_bg' => 'Breadcrumb Bar', 'dropdown_bg' => 'Dropdown & Popup Background', 'sidebar_bg' => 'Sidebar Background', 'success' => 'Success / In Stock', 'error' => 'Error / Out of Stock'],
                            'Checkout' => ['checkout_option_bg' => 'Payment & Shipping Cards', 'review_bg' => 'Review Cards'],
                            'Text' => ['text' => 'Paragraph Text', 'text_heading' => 'Titles & Headings', 'text_muted' => 'Hints & Small Descriptions', 'content_text' => 'Product Description Text'],
                            'Header' => ['nav_bg' => 'Background', 'nav_text' => 'Cart, Menu & Account Text', 'search_bg' => 'Search Bar', 'announcement_bg' => 'Announcement Background', 'announcement_text' => 'Announcement Text', 'logo_text' => 'Logo / Store Name'],
                            'Footer' => ['footer_bg' => 'Background', 'footer_text' => 'Links & Text', 'footer_heading' => 'Section Titles'],
                        ];
                    @endphp
                    <div class="v3-wells">
                        @foreach ($colorGroups as $groupLabel => $fields)
                            <div class="v3-well">
                                <div class="v3-label v3-gap-sm">{{ $groupLabel }}</div>
                                @foreach ($fields as $key => $label)
                                    <div class="v3-colorrow">
                                        <label for="color-{{ $key }}" class="v3-colorrow__label">{{ $label }}</label>
                                        <span class="v3-colorrow__pair">
                                            <input type="color" value="{{ $settings['colors'][$key] ?? '#000000' }}" class="v3-swatch"
                                                   aria-label="{{ $label }} color picker"
                                                   oninput="this.nextElementSibling.value = this.value; this.nextElementSibling.dispatchEvent(new Event('input', { bubbles: true }))">
                                            <input type="text" id="color-{{ $key }}" name="groups[colors][{{ $key }}]"
                                                   value="{{ $settings['colors'][$key] ?? '#000000' }}" placeholder="#000000"
                                                   class="v3-control v3-mono v3-w-hex"
                                                   oninput="this.previousElementSibling.value = this.value">
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </x-admin3.panel>
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
                <x-admin3.panel title="Fonts">
                    @php
                        $fontOptions = ['Barlow','Bebas Neue','Bitter','Cormorant Garamond','DM Sans','DM Serif Display','Fira Sans','IBM Plex Sans','Inter','Josefin Sans','Lato','Libre Baskerville','Lora','Merriweather','Montserrat','Nunito','Nunito Sans','Open Sans','Oswald','Playfair Display','Poppins','PT Sans','PT Serif','Rajdhani','Raleway','Roboto','Roboto Slab','Rubik','Source Sans 3','Space Grotesk','Space Mono','Ubuntu','Work Sans'];
                        $sizeFields = [
                            'size_logo' => ['label' => 'Logo / Store Name', 'default' => '32', 'min' => 18, 'max' => 60],
                            'size_section_title' => ['label' => 'Section Titles', 'default' => '24', 'min' => 16, 'max' => 48],
                            'size_product_name' => ['label' => 'Product Name (card)', 'default' => '14', 'min' => 11, 'max' => 20],
                            'size_price' => ['label' => 'Product Price', 'default' => '16', 'min' => 12, 'max' => 24],
                            'size_body' => ['label' => 'Body Text', 'default' => '14', 'min' => 12, 'max' => 20],
                            'size_nav' => ['label' => 'Navigation Menu', 'default' => '14', 'min' => 11, 'max' => 20],
                        ];
                    @endphp
                    <div class="v3-grid v3-grid--2">
                        <div>
                            <x-admin3.field name="groups.fonts.heading" label="Heading font">
                                <select name="groups[fonts][heading]" class="v3-control" x-model="headingFont" @change="loadPreviewFont(headingFont)">
                                    @foreach ($fontOptions as $font)
                                        <option value="{{ $font }}" @selected(($settings['fonts']['heading'] ?? '') === $font)>{{ $font }}</option>
                                    @endforeach
                                </select>
                            </x-admin3.field>
                            <div class="v3-gap">
                                <x-admin3.field name="groups.fonts.body" label="Body font">
                                    <select name="groups[fonts][body]" class="v3-control" x-model="bodyFont" @change="loadPreviewFont(bodyFont)">
                                        @foreach ($fontOptions as $font)
                                            <option value="{{ $font }}" @selected(($settings['fonts']['body'] ?? '') === $font)>{{ $font }}</option>
                                        @endforeach
                                    </select>
                                </x-admin3.field>
                            </div>
                            <div class="v3-gap">
                                <div class="v3-label v3-gap-sm">Font sizes</div>
                                @foreach ($sizeFields as $key => $cfg)
                                    <div class="v3-colorrow">
                                        <label for="size-{{ $key }}" class="v3-colorrow__label">{{ $cfg['label'] }}</label>
                                        <span class="v3-colorrow__pair">
                                            <input type="number" id="size-{{ $key }}" name="groups[fonts][{{ $key }}]"
                                                   value="{{ $settings['fonts'][$key] ?? $cfg['default'] }}"
                                                   min="{{ $cfg['min'] }}" max="{{ $cfg['max'] }}"
                                                   class="v3-control v3-mono v3-w-hex">
                                            <span class="v3-cell-quiet">px</span>
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div>
                            <div class="v3-label v3-gap-sm">Preview</div>
                            <div class="v3-well">
                                <div :style="'font-family: \'' + headingFont + '\', sans-serif'">
                                    <div class="v3-preview-h" x-text="headingFont">Heading font</div>
                                    <div class="v3-preview-sub">Section Title Example</div>
                                    <div class="v3-preview-price">₱12,990.00</div>
                                </div>
                                <hr class="v3-irail__rule v3-preview-rule">
                                <div :style="'font-family: \'' + bodyFont + '\', sans-serif'">
                                    <div class="v3-preview-body" x-text="bodyFont + ': body font'">Body font</div>
                                    <p class="v3-preview-copy">This is how product descriptions, paragraphs, and general content read on the storefront. The quick brown fox jumps over the lazy dog.</p>
                                    <p class="v3-preview-hint">Hints and small descriptions appear like this.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-admin3.panel>
            </div>

            {{-- ========== HEADER ========== --}}
            <div x-show="tab === 'header'" x-cloak>
                <x-admin3.panel title="Announcement bar">
                    <label class="v3-toggle">
                        <input type="hidden" name="groups[header][announcement_enabled]" value="0">
                        <input type="checkbox" name="groups[header][announcement_enabled]" value="1"
                               @checked(($settings['header']['announcement_enabled'] ?? '0') === '1')>
                        <span class="v3-toggle__track" aria-hidden="true"></span>
                        Show the announcement bar
                    </label>
                    <div class="v3-grid v3-grid--2 v3-gap">
                        <x-admin3.field name="groups.header.announcement_text" label="Announcement text">
                            <input type="text" name="groups[header][announcement_text]" class="v3-control" value="{{ $settings['header']['announcement_text'] ?? '' }}">
                        </x-admin3.field>
                        <x-admin3.field name="groups.header.announcement_link" label="Announcement link">
                            <input type="text" name="groups[header][announcement_link]" class="v3-control v3-mono" placeholder="/page-url or https://…" value="{{ $settings['header']['announcement_link'] ?? '' }}">
                        </x-admin3.field>
                    </div>
                </x-admin3.panel>

                <x-admin3.panel title="Top bar links">
                    <div x-data="{ links: @js(json_decode($settings['header']['topbar_links'] ?? '[]', true) ?: []) }">
                        <template x-for="(link, idx) in links" :key="idx">
                            <div class="v3-row">
                                <input type="text" x-model="link.label" aria-label="Topbar link label" placeholder="Label" class="v3-control">
                                <input type="text" x-model="link.url" aria-label="Topbar link URL" placeholder="/url" class="v3-control v3-mono">
                                <button type="button" class="v3-row__x" @click="links.splice(idx, 1); touch()" aria-label="Remove link">
                                    <x-admin3.icon name="trash" />
                                </button>
                            </div>
                        </template>
                        <div class="v3-gap">
                            <button type="button" class="v3-btn v3-btn--secondary v3-btn--sm" @click="links.push({label:'',url:''}); touch()">
                                <x-admin3.icon name="plus" /> Add link
                            </button>
                        </div>
                        <input type="hidden" name="groups[header][topbar_links]" :value="JSON.stringify(links)">
                    </div>
                </x-admin3.panel>
            </div>

            {{-- ========== PRODUCT PAGE ========== --}}
            <div x-show="tab === 'product_page'" x-cloak>
                <x-admin3.panel title="Product page">
                    @foreach (['show_ratings' => 'Show star ratings', 'show_stock' => 'Show stock and availability', 'show_brand' => 'Show the brand logo', 'show_badges' => 'Show product badges'] as $key => $label)
                        <div class="{{ $loop->first ? '' : 'v3-gap' }}">
                            <label class="v3-toggle">
                                <input type="hidden" name="groups[product_page][{{ $key }}]" value="0">
                                <input type="checkbox" name="groups[product_page][{{ $key }}]" value="1"
                                       @checked(($settings['product_page'][$key] ?? '0') === '1')>
                                <span class="v3-toggle__track" aria-hidden="true"></span>
                                {{ $label }}
                            </label>
                        </div>
                    @endforeach
                    <div class="v3-gap v3-narrow">
                        <x-admin3.field name="groups.product_page.thumbnail_position" label="Thumbnail position">
                            <select name="groups[product_page][thumbnail_position]" class="v3-control">
                                <option value="left" @selected(($settings['product_page']['thumbnail_position'] ?? 'left') === 'left')>Left</option>
                                <option value="below" @selected(($settings['product_page']['thumbnail_position'] ?? '') === 'below')>Below</option>
                            </select>
                        </x-admin3.field>
                    </div>
                </x-admin3.panel>
            </div>

            {{-- ========== CATEGORY PAGE ========== --}}
            <div x-show="tab === 'category_page'" x-cloak>
                <x-admin3.panel title="Category page">
                    <div class="v3-narrow">
                        <x-admin3.field name="groups.category_page.products_per_page" label="Products per page">
                            <input type="number" name="groups[category_page][products_per_page]" min="4" max="60" class="v3-control v3-mono v3-w-rate" value="{{ $settings['category_page']['products_per_page'] ?? '12' }}">
                        </x-admin3.field>
                    </div>
                    <div class="v3-grid v3-grid--3 v3-gap">
                        <x-admin3.field name="groups.category_page.columns_mobile" label="Mobile columns">
                            <input type="number" name="groups[category_page][columns_mobile]" min="1" max="3" class="v3-control v3-mono" value="{{ $settings['category_page']['columns_mobile'] ?? '2' }}">
                        </x-admin3.field>
                        <x-admin3.field name="groups.category_page.columns_tablet" label="Tablet columns">
                            <input type="number" name="groups[category_page][columns_tablet]" min="2" max="4" class="v3-control v3-mono" value="{{ $settings['category_page']['columns_tablet'] ?? '3' }}">
                        </x-admin3.field>
                        <x-admin3.field name="groups.category_page.columns_desktop" label="Desktop columns">
                            <input type="number" name="groups[category_page][columns_desktop]" min="3" max="8" class="v3-control v3-mono" value="{{ $settings['category_page']['columns_desktop'] ?? '4' }}">
                        </x-admin3.field>
                    </div>
                    <div class="v3-gap v3-narrow">
                        <x-admin3.field name="groups.category_page.default_sort" label="Default sort">
                            <select name="groups[category_page][default_sort]" class="v3-control">
                                @foreach (['default' => 'Default', 'name_asc' => 'Name A-Z', 'name_desc' => 'Name Z-A', 'price_asc' => 'Price low to high', 'price_desc' => 'Price high to low', 'newest' => 'Newest'] as $v => $label)
                                    <option value="{{ $v }}" @selected(($settings['category_page']['default_sort'] ?? '') === $v)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-admin3.field>
                    </div>
                </x-admin3.panel>

                <x-admin3.panel title="Sidebar filters">
                    @foreach (['price_enabled' => 'Price range', 'availability_enabled' => 'Availability', 'brand_enabled' => 'Brand', 'rating_enabled' => 'Rating', 'on_sale_enabled' => 'On sale', 'tags_enabled' => 'Tags'] as $key => $label)
                        <div class="{{ $loop->first ? '' : 'v3-gap' }}">
                            <label class="v3-toggle">
                                <input type="hidden" name="groups[filters][{{ $key }}]" value="0">
                                <input type="checkbox" name="groups[filters][{{ $key }}]" value="1"
                                       @checked(($settings['filters'][$key] ?? '0') === '1')>
                                <span class="v3-toggle__track" aria-hidden="true"></span>
                                {{ $label }}
                            </label>
                        </div>
                    @endforeach
                </x-admin3.panel>
            </div>

            {{-- ========== BADGES ========== --}}
            <div x-show="tab === 'badges'" x-cloak>
                <x-admin3.panel title="Badges">
                    <div class="v3-narrow">
                        <x-admin3.field name="groups.badges.new_count" label="NEW badge: last X products" hint="Products added most recently. 0 disables it.">
                            <input type="number" name="groups[badges][new_count]" min="0" max="100" class="v3-control v3-mono v3-w-rate" value="{{ $settings['badges']['new_count'] ?? '10' }}">
                        </x-admin3.field>
                        <div class="v3-gap">
                            <x-admin3.field name="groups.badges.hot_count" label="HOT badge: top X best sellers" hint="Products with the most orders. 0 disables it.">
                                <input type="number" name="groups[badges][hot_count]" min="0" max="100" class="v3-control v3-mono v3-w-rate" value="{{ $settings['badges']['hot_count'] ?? '5' }}">
                            </x-admin3.field>
                        </div>
                    </div>
                </x-admin3.panel>
            </div>

            {{-- ========== BLOG ========== --}}
            <div x-show="tab === 'blog'" x-cloak>
                <x-admin3.panel title="Blog">
                    <div class="v3-narrow">
                        <x-admin3.field name="groups.blog.posts_per_page" label="Posts per page">
                            <input type="number" name="groups[blog][posts_per_page]" min="4" max="48" class="v3-control v3-mono v3-w-rate" value="{{ $settings['blog']['posts_per_page'] ?? '12' }}">
                        </x-admin3.field>
                        <div class="v3-gap">
                            <x-admin3.field name="groups.blog.default_view" label="Default view">
                                <select name="groups[blog][default_view]" class="v3-control">
                                    <option value="grid" @selected(($settings['blog']['default_view'] ?? 'grid') === 'grid')>Grid</option>
                                    <option value="list" @selected(($settings['blog']['default_view'] ?? '') === 'list')>List</option>
                                </select>
                            </x-admin3.field>
                        </div>
                        <div class="v3-gap">
                            <x-admin3.field name="groups.blog.excerpt_length" label="Excerpt length (characters)">
                                <input type="number" name="groups[blog][excerpt_length]" min="50" max="500" class="v3-control v3-mono v3-w-rate" value="{{ $settings['blog']['excerpt_length'] ?? '150' }}">
                            </x-admin3.field>
                        </div>
                        <div class="v3-gap">
                            <label class="v3-toggle">
                                <input type="hidden" name="groups[blog][show_view_count]" value="0">
                                <input type="checkbox" name="groups[blog][show_view_count]" value="1" @checked(($settings['blog']['show_view_count'] ?? '0') === '1')>
                                <span class="v3-toggle__track" aria-hidden="true"></span>
                                Show view counts
                            </label>
                        </div>
                        <div class="v3-gap">
                            <label class="v3-toggle">
                                <input type="hidden" name="groups[blog][show_related_articles]" value="0">
                                <input type="checkbox" name="groups[blog][show_related_articles]" value="1" @checked(($settings['blog']['show_related_articles'] ?? '0') === '1')>
                                <span class="v3-toggle__track" aria-hidden="true"></span>
                                Show related articles on product pages
                            </label>
                        </div>
                    </div>
                </x-admin3.panel>
            </div>
        </x-admin3.form>

        <x-admin3.confirm name="revert-colors" title="Revert to theme defaults?"
            message="Reverts BOTH colors and fonts to this theme's defaults. Unsaved edits on those tabs are lost."
            confirm="Revert" :action="route('admin.theme.revert-colors')" method="POST" />

        {{-- ========== NAVIGATION (records) ========== --}}
        <div x-show="tab === 'navigation'" x-cloak>
            <x-admin3.panel title="Menu items">
                <div x-init="initSortable($el.querySelector('[data-sortable]'), @js(route('admin.theme.nav.reorder')))">
                    <div data-sortable>
                        @foreach ($navItems as $item)
                            <div class="v3-dragrow" draggable="true" data-id="{{ $item->id }}">
                                <x-admin3.icon name="menu" class="v3-dragrow__grip drag-handle" />
                                <span class="v3-dir__body">
                                    <span class="v3-dir__name">{{ $item->label }}</span>
                                    <span class="v3-dir__line v3-block">{{ $item->type === 'category' ? 'Category: ' . ($item->category?->name ?? 'N/A') : 'Custom: ' . $item->url }}</span>
                                </span>
                                <span class="v3-badge {{ $item->is_enabled ? 'v3-badge--good' : 'v3-badge--quiet' }}">{{ $item->is_enabled ? 'Active' : 'Disabled' }}</span>
                                <button type="button" class="v3-row__x" aria-label="Delete {{ $item->label }}" @click="$dispatch('v3-confirm', 'delete-nav-{{ $item->id }}')">
                                    <x-admin3.icon name="trash" />
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="v3-gap-lg" x-data="{ newType: 'category' }">
                    <div class="v3-label v3-gap-sm">Add menu item</div>
                    <form method="POST" action="{{ route('admin.theme.nav.store') }}">
                        @csrf
                        <div class="v3-grid v3-grid--2 v3-narrow">
                            <x-admin3.field name="type" label="Type" required>
                                <select name="type" x-model="newType" class="v3-control">
                                    <option value="category">Category</option>
                                    <option value="custom">Custom link</option>
                                </select>
                            </x-admin3.field>
                            <x-admin3.field name="label" label="Label" required>
                                <input type="text" name="label" required placeholder="Menu label" class="v3-control">
                            </x-admin3.field>
                        </div>
                        <div class="v3-gap v3-narrow" x-show="newType === 'category'">
                            <x-admin3.field name="category_id" label="Category">
                                <select name="category_id" class="v3-control">
                                    <option value="">Select…</option>
                                    @foreach ($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            </x-admin3.field>
                        </div>
                        <div class="v3-gap v3-narrow" x-show="newType === 'custom'" x-cloak>
                            <x-admin3.field name="url" label="URL">
                                <input type="text" name="url" placeholder="/blog or https://…" class="v3-control v3-mono">
                            </x-admin3.field>
                        </div>
                        <div class="v3-gap v3-actrow">
                            <label class="v3-check">
                                <input type="checkbox" name="new_tab" value="1">
                                Open in a new tab
                            </label>
                            <button type="submit" class="v3-btn v3-btn--primary">Add item</button>
                        </div>
                    </form>
                </div>
            </x-admin3.panel>

            @foreach ($navItems as $item)
                <x-admin3.confirm name="delete-nav-{{ $item->id }}" title="Delete {{ $item->label }}?"
                    message="The menu item leaves the storefront header immediately."
                    confirm="Delete" :action="route('admin.theme.nav.destroy', $item)" method="DELETE" />
            @endforeach
        </div>

        {{-- ========== HOMEPAGE (records) ========== --}}
        <div x-show="tab === 'homepage'" x-cloak>
            <x-admin3.panel title="Homepage sections">
                <div x-init="initSortable($el.querySelector('[data-sortable]'), @js(route('admin.theme.sections.reorder')))">
                    <div data-sortable>
                        @foreach ($sections as $section)
                            <div class="v3-dragcard" x-data="{ expanded: false }" draggable="true" data-id="{{ $section->id }}">
                                <div class="v3-dragrow v3-dragrow--bare">
                                    <x-admin3.icon name="menu" class="v3-dragrow__grip drag-handle" />
                                    <span class="v3-badge v3-badge--quiet">{{ $section->type }}</span>
                                    <span class="v3-dir__name">{{ $section->title }}</span>
                                    <span class="v3-dragrow__end">
                                        <span class="v3-badge {{ $section->is_enabled ? 'v3-badge--good' : 'v3-badge--quiet' }}">{{ $section->is_enabled ? 'Active' : 'Disabled' }}</span>
                                        <button type="button" class="v3-btn v3-btn--ghost v3-btn--sm" @click="expanded = !expanded"
                                                :aria-expanded="expanded ? 'true' : 'false'" x-text="expanded ? 'Close' : 'Edit'">Edit</button>
                                        <button type="button" class="v3-row__x" aria-label="Delete {{ $section->title }}" @click="$dispatch('v3-confirm', 'delete-section-{{ $section->id }}')">
                                            <x-admin3.icon name="trash" />
                                        </button>
                                    </span>
                                </div>
                                <div x-show="expanded" x-cloak class="v3-dragcard__body">
                                    <x-admin3.form :action="route('admin.theme.sections.update', $section)" method="PUT">
                                        <div class="v3-grid v3-grid--2">
                                            <x-admin3.field name="title" label="Title" required>
                                                <input type="text" name="title" value="{{ $section->title }}" class="v3-control">
                                            </x-admin3.field>
                                            <div class="v3-addrow__opts">
                                                <label class="v3-toggle">
                                                    <input type="hidden" name="is_enabled" value="0">
                                                    <input type="checkbox" name="is_enabled" value="1" @checked($section->is_enabled)>
                                                    <span class="v3-toggle__track" aria-hidden="true"></span>
                                                    Enabled
                                                </label>
                                            </div>
                                        </div>
                                        <div class="v3-gap">
                                            @include('admin.settings.theme.section-config', ['section' => $section])
                                        </div>
                                    </x-admin3.form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="v3-gap-lg">
                    <div class="v3-label v3-gap-sm">Add section</div>
                    <form method="POST" action="{{ route('admin.theme.sections.store') }}" class="v3-addrow v3-addrow--3">
                        @csrf
                        <x-admin3.field name="type" label="Type" required>
                            <select name="type" class="v3-control">
                                @foreach (['banner' => 'Banner/Slider', 'products' => 'Products Carousel', 'brands' => 'Brands Carousel', 'recently_viewed' => 'Recently Viewed', 'category_tabs' => 'Category Tabs', 'text_block' => 'Text Block', 'blog_posts' => 'Blog Posts'] as $v => $label)
                                    <option value="{{ $v }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-admin3.field>
                        <x-admin3.field name="title" label="Title" required>
                            <input type="text" name="title" required placeholder="Section title" class="v3-control">
                        </x-admin3.field>
                        <div class="v3-addrow__opts">
                            <button type="submit" class="v3-btn v3-btn--primary">Add section</button>
                        </div>
                    </form>
                </div>
            </x-admin3.panel>

            @foreach ($sections as $section)
                <x-admin3.confirm name="delete-section-{{ $section->id }}" title="Delete {{ $section->title }}?"
                    message="The section leaves the homepage immediately."
                    confirm="Delete" :action="route('admin.theme.sections.destroy', $section)" method="DELETE" />
            @endforeach
        </div>

        {{-- ========== FOOTER (records, bulk) ========== --}}
        <div x-show="tab === 'footer'" x-cloak>
            <x-admin3.panel title="Footer columns" note="One save commits every column below.">
                <x-admin3.form :action="route('admin.theme.footer.bulk-update')" enctype="multipart/form-data">
                    <div x-init="initSortable($el.querySelector('[data-sortable]'), @js(route('admin.theme.footer.reorder')))">
                        <div data-sortable>
                            @foreach ($footerColumns as $col)
                                <div class="v3-dragcard" data-id="{{ $col->id }}" draggable="true"
                                     x-data="{
                                        open: false,
                                        links: @js($col->config['links'] ?? []),
                                        socials: @js($col->type === 'social' ? ($col->config['links'] ?? []) : []),
                                        payIcons: @js(collect($col->config['icons'] ?? [])->map(fn ($icon) => is_string($icon) ? ['type' => 'text', 'value' => $icon] : $icon)->values()->toArray()),
                                     }">
                                    <input type="hidden" name="columns[{{ $col->id }}][id]" value="{{ $col->id }}">
                                    <div class="v3-dragrow v3-dragrow--bare">
                                        <x-admin3.icon name="menu" class="v3-dragrow__grip drag-handle" />
                                        <span class="v3-badge v3-badge--quiet">{{ $col->type }}</span>
                                        <input type="text" name="columns[{{ $col->id }}][title]" value="{{ $col->title }}" aria-label="Column title" class="v3-control v3-w-name">
                                        <span class="v3-dragrow__end">
                                            <label class="v3-check" title="Column shown in the footer">
                                                <input type="hidden" name="columns[{{ $col->id }}][is_enabled]" value="0">
                                                <input type="checkbox" name="columns[{{ $col->id }}][is_enabled]" value="1" @checked($col->is_enabled) aria-label="Show this footer column">
                                            </label>
                                            <button type="button" class="v3-btn v3-btn--ghost v3-btn--sm" @click="open = !open" :aria-expanded="open ? 'true' : 'false'" x-text="open ? 'Close' : 'Edit'">Edit</button>
                                            <button type="button" class="v3-row__x" @click="$dispatch('v3-confirm', 'delete-footer-col-{{ $col->id }}')" aria-label="Delete {{ $col->title }} footer column">
                                                <x-admin3.icon name="trash" />
                                            </button>
                                        </span>
                                    </div>

                                    <div x-show="open" x-cloak class="v3-dragcard__body">
                                        @if ($col->type === 'links')
                                            <template x-for="(link, idx) in links" :key="idx">
                                                <div class="v3-row">
                                                    <input type="text" :name="'columns[{{ $col->id }}][config][links]['+idx+'][label]'" x-model="link.label" aria-label="Link label" placeholder="Label" class="v3-control">
                                                    <input type="text" :name="'columns[{{ $col->id }}][config][links]['+idx+'][url]'" x-model="link.url" aria-label="Link URL" placeholder="/slug" class="v3-control v3-mono">
                                                    <button type="button" class="v3-row__x" @click="links.splice(idx, 1)" aria-label="Remove link"><x-admin3.icon name="x" /></button>
                                                </div>
                                            </template>
                                            <div class="v3-gap">
                                                <button type="button" class="v3-btn v3-btn--secondary v3-btn--sm" @click="links.push({label: '', url: ''})"><x-admin3.icon name="plus" /> Add link</button>
                                            </div>
                                        @elseif ($col->type === 'social')
                                            <template x-for="(social, idx) in socials" :key="idx">
                                                <div class="v3-row">
                                                    <select :name="'columns[{{ $col->id }}][config][links]['+idx+'][platform]'" x-model="social.platform" aria-label="Social platform" class="v3-control v3-w-name">
                                                        <option value="facebook">Facebook</option>
                                                        <option value="instagram">Instagram</option>
                                                        <option value="youtube">YouTube</option>
                                                        <option value="tiktok">TikTok</option>
                                                    </select>
                                                    <input type="text" :name="'columns[{{ $col->id }}][config][links]['+idx+'][url]'" x-model="social.url" aria-label="Social profile URL" class="v3-control v3-mono">
                                                    <button type="button" class="v3-row__x" @click="socials.splice(idx, 1)" aria-label="Remove social link"><x-admin3.icon name="x" /></button>
                                                </div>
                                            </template>
                                            <div class="v3-gap">
                                                <button type="button" class="v3-btn v3-btn--secondary v3-btn--sm" @click="socials.push({platform: 'facebook', url: ''})"><x-admin3.icon name="plus" /> Add social link</button>
                                            </div>
                                        @elseif ($col->type === 'payment_icons')
                                            <template x-for="(icon, idx) in payIcons" :key="idx">
                                                <div class="v3-row">
                                                    <select :name="'columns[{{ $col->id }}][config][icons]['+idx+'][type]'" x-model="icon.type" aria-label="Payment mark kind" class="v3-control v3-w-sort">
                                                        <option value="text">Text</option>
                                                        <option value="image">Image</option>
                                                    </select>
                                                    <template x-if="icon.type === 'text'">
                                                        <input type="text" :name="'columns[{{ $col->id }}][config][icons]['+idx+'][value]'" x-model="icon.value" aria-label="Payment mark label" class="v3-control">
                                                    </template>
                                                    <template x-if="icon.type === 'image'">
                                                        <span class="v3-actrow">
                                                            <template x-if="icon.value">
                                                                <img :src="icon.value.startsWith('http') || icon.value.startsWith('/') ? icon.value : '/storage/' + icon.value" class="v3-payicon" alt="">
                                                            </template>
                                                            <input type="hidden" :name="'columns[{{ $col->id }}][config][icons]['+idx+'][value]'" :value="icon.value">
                                                            <input type="file" :name="'icon_uploads[{{ $col->id }}]['+idx+']'" accept="image/*" aria-label="Payment mark image" class="v3-file">
                                                        </span>
                                                    </template>
                                                    <button type="button" class="v3-row__x" @click="payIcons.splice(idx, 1)" aria-label="Remove payment icon"><x-admin3.icon name="x" /></button>
                                                </div>
                                            </template>
                                            <div class="v3-gap">
                                                <button type="button" class="v3-btn v3-btn--secondary v3-btn--sm" @click="payIcons.push({type: 'text', value: ''})"><x-admin3.icon name="plus" /> Add payment method</button>
                                            </div>
                                        @elseif ($col->type === 'text')
                                            <textarea name="columns[{{ $col->id }}][config][content]" rows="3" aria-label="Column text" class="v3-control" placeholder="Free text content…">{{ $col->config['content'] ?? '' }}</textarea>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </x-admin3.form>

                <div class="v3-gap-lg">
                    <div class="v3-label v3-gap-sm">Add footer column</div>
                    <form method="POST" action="{{ route('admin.theme.footer.store') }}" class="v3-addrow v3-addrow--3">
                        @csrf
                        <x-admin3.field name="type" label="Type" required>
                            <select name="type" class="v3-control">
                                <option value="links">Links</option>
                                <option value="payment_icons">Payment icons</option>
                                <option value="social">Social links</option>
                                <option value="text">Free text</option>
                            </select>
                        </x-admin3.field>
                        <x-admin3.field name="title" label="Title" required>
                            <input type="text" name="title" required class="v3-control">
                        </x-admin3.field>
                        <div class="v3-addrow__opts">
                            <button type="submit" class="v3-btn v3-btn--primary">Add column</button>
                        </div>
                    </form>
                </div>
            </x-admin3.panel>

            @foreach ($footerColumns as $col)
                <x-admin3.confirm name="delete-footer-col-{{ $col->id }}" title="Delete {{ $col->title }}?"
                    message="The column leaves the storefront footer immediately."
                    confirm="Delete" :action="route('admin.theme.footer.destroy', $col)" method="DELETE" />
            @endforeach
        </div>

        {{-- ========== THEME OPTIONS (per-theme schema) ========== --}}
        <div x-show="tab === 'theme-settings'" x-cloak>
            <x-admin3.panel title="Theme options" note="Settings this theme's own manifest declares.">
                @if (count($themeSettingsSchema) > 0)
                    <x-admin3.form :action="route('admin.theme.update-theme-settings')">
                        <div class="v3-narrow">
                            @foreach ($themeSettingsSchema as $key => $schema)
                                <div class="{{ $loop->first ? '' : 'v3-gap' }}">
                                    <x-admin3.field name="theme_settings.{{ $key }}" :label="$schema['label'] ?? $key">
                                        @if (($schema['type'] ?? '') === 'select')
                                            <select name="theme_settings[{{ $key }}]" class="v3-control">
                                                @foreach ($schema['options'] ?? [] as $option)
                                                    <option value="{{ $option }}" @selected(($themeSettingValues[$key] ?? ($schema['default'] ?? '')) == $option)>{{ ucfirst($option) }}</option>
                                                @endforeach
                                            </select>
                                        @elseif (($schema['type'] ?? '') === 'toggle')
                                            <label class="v3-toggle">
                                                <input type="hidden" name="theme_settings[{{ $key }}]" value="0">
                                                <input type="checkbox" name="theme_settings[{{ $key }}]" value="1" @checked(($themeSettingValues[$key] ?? ($schema['default'] ?? false)) ? true : false)>
                                                <span class="v3-toggle__track" aria-hidden="true"></span>
                                                Enabled
                                            </label>
                                        @elseif (($schema['type'] ?? '') === 'color')
                                            <input type="color" name="theme_settings[{{ $key }}]" value="{{ $themeSettingValues[$key] ?? ($schema['default'] ?? '#000000') }}" class="v3-swatch v3-swatch--lg">
                                        @elseif (($schema['type'] ?? '') === 'number')
                                            <input type="number" name="theme_settings[{{ $key }}]" value="{{ $themeSettingValues[$key] ?? ($schema['default'] ?? '') }}" class="v3-control v3-mono v3-w-rate">
                                        @else
                                            <input type="text" name="theme_settings[{{ $key }}]" value="{{ $themeSettingValues[$key] ?? ($schema['default'] ?? '') }}" class="v3-control">
                                        @endif
                                    </x-admin3.field>
                                </div>
                            @endforeach
                        </div>
                    </x-admin3.form>
                @else
                    <div class="v3-empty">
                        <p class="v3-empty__title">This theme has no configurable options</p>
                    </div>
                @endif
            </x-admin3.panel>
        </div>
    </div>

    @push('scripts')
    <script>
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
</x-admin3.settings-shell>
