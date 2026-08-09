<x-admin.settings-shell title="Theme Settings" :wide="true">
        {{-- Theme Selector --}}
        <x-admin.card class="mb-6">
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div>
                    <h3 class="text-[13px] font-semibold text-v-text">Active Theme</h3>
                    <p class="text-[13px] text-v-secondary mt-1">{{ $installedThemes[$activeTheme]['name'] ?? $activeTheme }}: {{ $installedThemes[$activeTheme]['description'] ?? '' }}</p>
                </div>
                <form method="POST" action="{{ route('admin.theme.switch') }}" data-v-form class="flex items-center gap-3">
                    @csrf
                    <x-admin.select name="theme">
                        @foreach ($installedThemes as $slug => $meta)
                            <option value="{{ $slug }}" {{ $slug === $activeTheme ? 'selected' : '' }}>{{ $meta['name'] }}</option>
                        @endforeach
                    </x-admin.select>
                    <x-admin.button type="submit">Switch</x-admin.button>
                </form>
            </div>
        </x-admin.card>

        <div x-data="themeAdmin()">
            <x-admin.tabs default="{{ session('tab', 'colors') }}" param="tab"
                :names="['colors', 'fonts', 'header', 'navigation', 'homepage', 'product_page', 'category_page', 'badges', 'footer', 'blog', 'theme-settings']">
                <x-slot:nav>
                    <template x-for="t in tabs" :key="t.id">
                        <button type="button" role="tab" @click="pick(t.id)"
                            :aria-selected="tab === t.id ? 'true' : 'false'"
                            :class="tab === t.id ? 'border-v-accent text-v-accent' : 'border-transparent text-v-secondary hover:text-v-text'"
                            class="-mb-px whitespace-nowrap border-b-2 px-3 py-2 text-[13px] font-medium transition-colors" x-text="t.label"></button>
                    </template>
                </x-slot:nav>

                {{-- ==================== COLORS ==================== --}}
                <x-admin.tabs.panel name="colors">
                    <x-admin.card>
                        <form method="POST" action="{{ route('admin.theme.settings.update') }}" data-v-form>
                            @csrf
                            <input type="hidden" name="group" value="colors">

                            @php
                                $colorGroups = [
                                    'Storefront' => [
                                        'primary' => 'Buttons & Links',
                                        'secondary' => 'Sale Badges & Accents',
                                        'btn_text' => 'Text on Buttons',
                                        'price' => 'Product Prices',
                                    ],
                                    'Product Cards' => [
                                        'badge_new' => '"New" Badge',
                                        'badge_hot' => '"Hot" Badge',
                                        'badge_free_shipping' => '"Free Shipping" Badge',
                                        'star_rating' => 'Star Ratings',
                                        'sale_price' => 'Sale Price',
                                        'card_hover' => 'Card Hover Effect',
                                    ],
                                    'Page' => [
                                        'bg' => 'Page Background',
                                        'card_bg' => 'Product Card Background',
                                        'border' => 'Lines & Dividers',
                                        'input_bg' => 'Form Field Background',
                                        'input_border' => 'Form Field Borders',
                                        'breadcrumb_bg' => 'Breadcrumb Bar',
                                        'dropdown_bg' => 'Dropdown & Popup Background',
                                        'sidebar_bg' => 'Sidebar Background',
                                        'success' => 'Success / In Stock',
                                        'error' => 'Error / Out of Stock',
                                    ],
                                    'Checkout' => [
                                        'checkout_option_bg' => 'Payment & Shipping Cards',
                                        'review_bg' => 'Review Cards',
                                    ],
                                    'Text' => [
                                        'text' => 'Paragraph Text',
                                        'text_heading' => 'Titles & Headings',
                                        'text_muted' => 'Hints & Small Descriptions',
                                        'content_text' => 'Product Description Text',
                                    ],
                                    'Header' => [
                                        'nav_bg' => 'Background',
                                        'nav_text' => 'Cart, Menu & Account Text',
                                        'search_bg' => 'Search Bar',
                                        'announcement_bg' => 'Announcement Background',
                                        'announcement_text' => 'Announcement Text',
                                        'logo_text' => 'Logo / Store Name',
                                    ],
                                    'Footer' => [
                                        'footer_bg' => 'Background',
                                        'footer_text' => 'Links & Text',
                                        'footer_heading' => 'Section Titles',
                                    ],
                                ];
                            @endphp

                            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                                @foreach ($colorGroups as $groupLabel => $fields)
                                    <div class="bg-v-sunken rounded-v-lg p-4">
                                        <h4 class="v-label mb-3">{{ $groupLabel }}</h4>
                                        <div class="space-y-3">
                                            @foreach ($fields as $key => $label)
                                                <div class="flex items-center justify-between gap-3">
                                                    <label class="text-[13px] text-v-secondary min-w-0">{{ $label }}</label>
                                                    <div class="flex items-center gap-1.5 shrink-0">
                                                        <input type="color" value="{{ $settings['colors'][$key] ?? '#000000' }}" class="h-8 w-10 rounded-v-sm border border-v-border cursor-pointer bg-v-surface"
                                                            oninput="this.nextElementSibling.value = this.value">
                                                        <input type="text" name="settings[{{ $key }}]" value="{{ $settings['colors'][$key] ?? '#000000' }}"
                                                            class="v-control w-20 h-[var(--control-h-sm)] px-2 text-xs font-mono bg-v-surface text-v-text border border-v-border rounded-v-md"
                                                            oninput="this.previousElementSibling.value = this.value"
                                                            placeholder="#000000">
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="mt-6 flex items-center justify-between">
                                <button type="button" class="text-xs text-v-muted hover:text-v-danger font-medium" @click="$dispatch('open-modal', 'revert-colors')">Revert to Default</button>
                                <x-admin.button type="submit">Save Colors</x-admin.button>
                            </div>
                        </form>

                        <x-admin.confirm-dialog name="revert-colors" title="Revert to theme defaults?"
                            message="Revert all colors and fonts to theme defaults?" confirm="Revert"
                            :action="route('admin.theme.revert-colors')" />
                    </x-admin.card>
                </x-admin.tabs.panel>

                {{-- ==================== FONTS ==================== --}}
                <x-admin.tabs.panel name="fonts">
                    <x-admin.card>
                        <div x-data="{
                            headingFont: '{{ $settings['fonts']['heading'] ?? 'Oswald' }}',
                            bodyFont: '{{ $settings['fonts']['body'] ?? 'Roboto' }}',
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
                            <form method="POST" action="{{ route('admin.theme.settings.update') }}" data-v-form>
                                @csrf
                                <input type="hidden" name="group" value="fonts">
                                @php
                                    $fontOptions = ['Barlow','Bebas Neue','Bitter','Cormorant Garamond','DM Sans','DM Serif Display','Fira Sans','IBM Plex Sans','Inter','Josefin Sans','Lato','Libre Baskerville','Lora','Merriweather','Montserrat','Nunito','Nunito Sans','Open Sans','Oswald','Playfair Display','Poppins','PT Sans','PT Serif','Rajdhani','Raleway','Roboto','Roboto Slab','Rubik','Source Sans 3','Space Grotesk','Space Mono','Ubuntu','Work Sans'];
                                @endphp

                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                                    {{-- Font Selection --}}
                                    <div class="space-y-6">
                                        <h4 class="v-label">Font Family</h4>
                                        <x-admin.field name="settings[heading]" label="Heading Font">
                                            <select name="settings[heading]" x-model="headingFont" @change="loadPreviewFont(headingFont)"
                                                class="v-control w-full h-[var(--control-h)] px-2.5 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                                @foreach ($fontOptions as $font)
                                                    <option value="{{ $font }}" {{ ($settings['fonts']['heading'] ?? '') === $font ? 'selected' : '' }}>{{ $font }}</option>
                                                @endforeach
                                            </select>
                                        </x-admin.field>
                                        <x-admin.field name="settings[body]" label="Body Font">
                                            <select name="settings[body]" x-model="bodyFont" @change="loadPreviewFont(bodyFont)"
                                                class="v-control w-full h-[var(--control-h)] px-2.5 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                                @foreach ($fontOptions as $font)
                                                    <option value="{{ $font }}" {{ ($settings['fonts']['body'] ?? '') === $font ? 'selected' : '' }}>{{ $font }}</option>
                                                @endforeach
                                            </select>
                                        </x-admin.field>

                                        <h4 class="v-label pt-4">Font Sizes</h4>
                                        @php
                                            $sizeFields = [
                                                'size_logo' => ['label' => 'Logo / Store Name', 'default' => '32', 'min' => 18, 'max' => 60],
                                                'size_section_title' => ['label' => 'Section Titles', 'default' => '24', 'min' => 16, 'max' => 48],
                                                'size_product_name' => ['label' => 'Product Name (card)', 'default' => '14', 'min' => 11, 'max' => 20],
                                                'size_price' => ['label' => 'Product Price', 'default' => '16', 'min' => 12, 'max' => 24],
                                                'size_body' => ['label' => 'Body Text', 'default' => '14', 'min' => 12, 'max' => 20],
                                                'size_nav' => ['label' => 'Navigation Menu', 'default' => '14', 'min' => 11, 'max' => 20],
                                            ];
                                        @endphp
                                        <div class="space-y-3">
                                            @foreach ($sizeFields as $key => $cfg)
                                                <div class="flex items-center justify-between gap-3">
                                                    <label class="text-[13px] text-v-secondary">{{ $cfg['label'] }}</label>
                                                    <div class="flex items-center gap-2 shrink-0">
                                                        <input type="number" name="settings[{{ $key }}]"
                                                            value="{{ $settings['fonts'][$key] ?? $cfg['default'] }}"
                                                            min="{{ $cfg['min'] }}" max="{{ $cfg['max'] }}"
                                                            class="v-control w-16 h-[var(--control-h-sm)] text-center text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                                        <span class="text-xs text-v-muted">px</span>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>

                                    {{-- Live Preview --}}
                                    <div>
                                        <h4 class="v-label mb-4">Preview</h4>
                                        <div class="bg-v-sunken rounded-v-lg p-6 border border-v-border space-y-4">
                                            <div :style="'font-family: \'' + headingFont + '\', sans-serif'" class="space-y-2">
                                                <div class="text-3xl font-bold text-v-text" x-text="headingFont">Heading Font</div>
                                                <div class="text-xl font-semibold text-v-secondary">Section Title Example</div>
                                                <div class="text-lg font-bold text-v-accent">₱12,990.00</div>
                                            </div>
                                            <hr class="border-v-border">
                                            <div :style="'font-family: \'' + bodyFont + '\', sans-serif'" class="space-y-2">
                                                <div class="text-[13px] text-v-text" x-text="bodyFont + ': Body Font'">Body Font</div>
                                                <p class="text-[13px] text-v-secondary leading-relaxed">This is how your product descriptions, paragraphs, and general content will look on the storefront. The quick brown fox jumps over the lazy dog.</p>
                                                <p class="text-xs text-v-muted">Hints and small descriptions appear like this.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-6 flex justify-end">
                                    <x-admin.button type="submit">Save Fonts</x-admin.button>
                                </div>
                            </form>
                        </div>
                    </x-admin.card>
                </x-admin.tabs.panel>

                {{-- ==================== HEADER ==================== --}}
                <x-admin.tabs.panel name="header">
                    <x-admin.card>
                        <form method="POST" action="{{ route('admin.theme.settings.update') }}" data-v-form>
                            @csrf
                            <input type="hidden" name="group" value="header">
                            <div class="space-y-6 max-w-lg">
                                <h3 class="text-[13px] font-semibold text-v-text border-b border-v-border pb-2">Announcement Bar</h3>
                                <x-admin.field name="settings[announcement_enabled]">
                                    <label class="flex items-center gap-3 text-[13px] text-v-text cursor-pointer">
                                        <x-admin.toggle name="settings[announcement_enabled]" :checked="($settings['header']['announcement_enabled'] ?? '0') === '1'" />
                                        Enable Announcement Bar
                                    </label>
                                </x-admin.field>
                                <x-admin.field name="settings[announcement_text]" label="Announcement Text">
                                    <x-admin.input name="settings[announcement_text]" :value="$settings['header']['announcement_text'] ?? ''" />
                                </x-admin.field>
                                <x-admin.field name="settings[announcement_link]" label="Announcement Link (optional)">
                                    <x-admin.input name="settings[announcement_link]" :value="$settings['header']['announcement_link'] ?? ''" placeholder="/page-url or https://..." />
                                </x-admin.field>

                                <h3 class="text-[13px] font-semibold text-v-text border-b border-v-border pb-2 mt-8">Top Bar Links</h3>
                                <div x-data="topbarLinks()" class="space-y-2">
                                    <template x-for="(link, idx) in links" :key="idx">
                                        <div class="flex items-center gap-2">
                                            <input type="text" x-model="link.label" class="v-control flex-1 h-[var(--control-h)] px-3 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md" placeholder="Label">
                                            <input type="text" x-model="link.url" class="v-control flex-1 h-[var(--control-h)] px-3 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md" placeholder="/url">
                                            <button type="button" @click="links.splice(idx, 1)" class="text-v-danger hover:opacity-80">
                                                <x-admin.icon name="trash" class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </template>
                                    <button type="button" @click="links.push({label:'',url:''})" class="text-[13px] text-v-accent hover:opacity-80">+ Add Link</button>
                                    <input type="hidden" name="settings[topbar_links]" :value="JSON.stringify(links)">
                                </div>
                            </div>
                            <div class="mt-6 flex justify-end">
                                <x-admin.button type="submit">Save Header</x-admin.button>
                            </div>
                        </form>
                    </x-admin.card>
                </x-admin.tabs.panel>

                {{-- ==================== NAVIGATION ==================== --}}
                <x-admin.tabs.panel name="navigation">
                    <x-admin.card title="Menu Items">
                        <div class="space-y-2" x-init="initSortable($el, '{{ route('admin.theme.nav.reorder') }}')">
                            @foreach ($navItems as $item)
                                <div class="flex items-center gap-3 bg-v-sunken rounded-v-lg p-3"
                                     draggable="true" data-id="{{ $item->id }}">
                                    <svg class="w-5 h-5 text-v-muted cursor-grab shrink-0 drag-handle" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/></svg>
                                    <div class="flex-1 min-w-0">
                                        <span class="text-[13px] font-medium text-v-text">{{ $item->label }}</span>
                                        <span class="text-xs text-v-muted ml-2">
                                            ({{ $item->type === 'category' ? 'Category: ' . ($item->category?->name ?? 'N/A') : 'Custom: ' . $item->url }})
                                        </span>
                                    </div>
                                    <x-admin.badge :tone="$item->is_enabled ? 'success' : 'neutral'">{{ $item->is_enabled ? 'Active' : 'Disabled' }}</x-admin.badge>
                                    <button type="button" class="text-v-danger hover:opacity-80" @click="$dispatch('open-modal', 'delete-nav-{{ $item->id }}')">
                                        <x-admin.icon name="trash" class="w-4 h-4" />
                                    </button>
                                    <x-admin.confirm-dialog name="delete-nav-{{ $item->id }}" title="Delete menu item"
                                        message="Delete this menu item?" confirm="Delete"
                                        :action="route('admin.theme.nav.destroy', $item)" method="DELETE" />
                                </div>
                            @endforeach
                        </div>

                        {{-- Add new item --}}
                        <div class="border-t border-v-border pt-4 mt-4" x-data="{ newType: 'category' }">
                            <h4 class="text-[13px] font-medium text-v-text mb-3">Add Menu Item</h4>
                            <form method="POST" action="{{ route('admin.theme.nav.store') }}" data-v-form class="space-y-3">
                                @csrf
                                <div class="grid grid-cols-2 gap-3 max-w-lg">
                                    <x-admin.field name="type" label="Type">
                                        <x-admin.select name="type" x-model="newType">
                                            <option value="category">Category</option>
                                            <option value="custom">Custom Link</option>
                                        </x-admin.select>
                                    </x-admin.field>
                                    <x-admin.field name="label" label="Label">
                                        <x-admin.input name="label" required placeholder="Menu label" />
                                    </x-admin.field>
                                </div>
                                <div x-show="newType === 'category'" class="max-w-lg">
                                    <x-admin.field name="category_id" label="Category">
                                        <x-admin.select name="category_id">
                                            <option value="">-- Select --</option>
                                            @foreach ($categories as $cat)
                                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                            @endforeach
                                        </x-admin.select>
                                    </x-admin.field>
                                </div>
                                <div x-show="newType === 'custom'" class="max-w-lg">
                                    <x-admin.field name="url" label="URL">
                                        <x-admin.input name="url" placeholder="/blog or https://..." />
                                    </x-admin.field>
                                </div>
                                <x-admin.checkbox name="new_tab" label="Open in new tab" />
                                <x-admin.button type="submit">Add Item</x-admin.button>
                            </form>
                        </div>
                    </x-admin.card>
                </x-admin.tabs.panel>

                {{-- ==================== HOMEPAGE ==================== --}}
                <x-admin.tabs.panel name="homepage">
                    <x-admin.card title="Homepage Sections">
                        <div class="space-y-2" x-init="initSortable($el, '{{ route('admin.theme.sections.reorder') }}')">
                            @foreach ($sections as $section)
                                <div class="bg-v-sunken rounded-v-lg p-4" x-data="{ expanded: false }"
                                     draggable="true" data-id="{{ $section->id }}">
                                    <div class="flex items-center gap-3">
                                        <svg class="w-5 h-5 text-v-muted cursor-grab shrink-0 drag-handle" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/></svg>
                                        <x-admin.badge tone="info">{{ $section->type }}</x-admin.badge>
                                        <span class="text-[13px] font-medium text-v-text flex-1">{{ $section->title }}</span>
                                        <x-admin.badge :tone="$section->is_enabled ? 'success' : 'neutral'">{{ $section->is_enabled ? 'Active' : 'Disabled' }}</x-admin.badge>
                                        <button type="button" @click="expanded = !expanded" class="text-v-secondary hover:text-v-text">
                                            <x-admin.icon name="chevron-down" class="w-4 h-4 transition-transform" x-bind:class="expanded && 'rotate-180'" />
                                        </button>
                                        <button type="button" class="text-v-danger hover:opacity-80" @click="$dispatch('open-modal', 'delete-section-{{ $section->id }}')">
                                            <x-admin.icon name="trash" class="w-4 h-4" />
                                        </button>
                                        <x-admin.confirm-dialog name="delete-section-{{ $section->id }}" title="Delete section"
                                            message="Delete this section?" confirm="Delete"
                                            :action="route('admin.theme.sections.destroy', $section)" method="DELETE" />
                                    </div>

                                    <div x-show="expanded" x-transition class="mt-4 border-t border-v-border pt-4">
                                        <form method="POST" action="{{ route('admin.theme.sections.update', $section) }}" data-v-form class="space-y-3">
                                            @csrf @method('PUT')
                                            <div class="grid grid-cols-2 gap-3">
                                                <x-admin.field name="title" label="Title">
                                                    <x-admin.input name="title" :value="$section->title" />
                                                </x-admin.field>
                                                <div class="flex items-end pb-1.5">
                                                    <x-admin.checkbox name="is_enabled" :checked="$section->is_enabled" label="Enabled" />
                                                </div>
                                            </div>

                                            @include('admin.settings.theme.section-config', ['section' => $section])

                                            <div class="flex justify-end">
                                                <x-admin.button size="sm" type="submit">Update Section</x-admin.button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Add new section --}}
                        <div class="border-t border-v-border pt-4 mt-4">
                            <h4 class="text-[13px] font-medium text-v-text mb-3">Add Section</h4>
                            <form method="POST" action="{{ route('admin.theme.sections.store') }}" data-v-form class="flex items-end gap-3 max-w-lg">
                                @csrf
                                <div class="flex-1">
                                    <x-admin.field name="type" label="Type">
                                        <x-admin.select name="type">
                                            <option value="banner">Banner/Slider</option>
                                            <option value="products">Products Carousel</option>
                                            <option value="brands">Brands Carousel</option>
                                            <option value="recently_viewed">Recently Viewed</option>
                                            <option value="category_tabs">Category Tabs</option>
                                            <option value="text_block">Text Block</option>
                                            <option value="blog_posts">Blog Posts</option>
                                        </x-admin.select>
                                    </x-admin.field>
                                </div>
                                <div class="flex-1">
                                    <x-admin.field name="title" label="Title">
                                        <x-admin.input name="title" required placeholder="Section title" />
                                    </x-admin.field>
                                </div>
                                <x-admin.button type="submit">Add</x-admin.button>
                            </form>
                        </div>
                    </x-admin.card>
                </x-admin.tabs.panel>

                {{-- ==================== PRODUCT PAGE ==================== --}}
                <x-admin.tabs.panel name="product_page">
                    <x-admin.card>
                        <form method="POST" action="{{ route('admin.theme.settings.update') }}" data-v-form>
                            @csrf
                            <input type="hidden" name="group" value="product_page">
                            <div class="space-y-4 max-w-lg">
                                @php
                                    $ppToggles = [
                                        'show_ratings' => 'Show Star Ratings',
                                        'show_stock' => 'Show Stock/Availability',
                                        'show_brand' => 'Show Brand Logo',
                                        'show_badges' => 'Show Product Badges',
                                    ];
                                @endphp
                                @foreach ($ppToggles as $key => $label)
                                    <x-admin.field name="settings[{{ $key }}]">
                                        <label class="flex items-center gap-3 text-[13px] text-v-text cursor-pointer">
                                            <x-admin.toggle name="settings[{{ $key }}]" :checked="($settings['product_page'][$key] ?? '0') === '1'" />
                                            {{ $label }}
                                        </label>
                                    </x-admin.field>
                                @endforeach

                                <x-admin.field name="settings[thumbnail_position]" label="Thumbnail Position">
                                    <x-admin.select name="settings[thumbnail_position]">
                                        <option value="left" {{ ($settings['product_page']['thumbnail_position'] ?? 'left') === 'left' ? 'selected' : '' }}>Left</option>
                                        <option value="below" {{ ($settings['product_page']['thumbnail_position'] ?? '') === 'below' ? 'selected' : '' }}>Below</option>
                                    </x-admin.select>
                                </x-admin.field>
                            </div>
                            <div class="mt-6 flex justify-end">
                                <x-admin.button type="submit">Save Product Page</x-admin.button>
                            </div>
                        </form>
                    </x-admin.card>
                </x-admin.tabs.panel>

                {{-- ==================== CATEGORY PAGE ==================== --}}
                <x-admin.tabs.panel name="category_page">
                    <x-admin.card>
                        <form method="POST" action="{{ route('admin.theme.settings.update') }}" data-v-form>
                            @csrf
                            <input type="hidden" name="group" value="category_page">
                            <div class="space-y-4 max-w-lg">
                                <x-admin.field name="settings[products_per_page]" label="Products Per Page">
                                    <x-admin.input type="number" name="settings[products_per_page]" :value="$settings['category_page']['products_per_page'] ?? '12'" min="4" max="60" class="w-32" />
                                </x-admin.field>
                                <div class="grid grid-cols-3 gap-4">
                                    <x-admin.field name="settings[columns_mobile]" label="Mobile Columns">
                                        <x-admin.input type="number" name="settings[columns_mobile]" :value="$settings['category_page']['columns_mobile'] ?? '2'" min="1" max="3" />
                                    </x-admin.field>
                                    <x-admin.field name="settings[columns_tablet]" label="Tablet Columns">
                                        <x-admin.input type="number" name="settings[columns_tablet]" :value="$settings['category_page']['columns_tablet'] ?? '3'" min="2" max="4" />
                                    </x-admin.field>
                                    <x-admin.field name="settings[columns_desktop]" label="Desktop Columns">
                                        <x-admin.input type="number" name="settings[columns_desktop]" :value="$settings['category_page']['columns_desktop'] ?? '4'" min="3" max="8" />
                                    </x-admin.field>
                                </div>
                                <x-admin.field name="settings[default_sort]" label="Default Sort">
                                    <x-admin.select name="settings[default_sort]">
                                        <option value="default" {{ ($settings['category_page']['default_sort'] ?? '') === 'default' ? 'selected' : '' }}>Default</option>
                                        <option value="name_asc" {{ ($settings['category_page']['default_sort'] ?? '') === 'name_asc' ? 'selected' : '' }}>Name A-Z</option>
                                        <option value="name_desc" {{ ($settings['category_page']['default_sort'] ?? '') === 'name_desc' ? 'selected' : '' }}>Name Z-A</option>
                                        <option value="price_asc" {{ ($settings['category_page']['default_sort'] ?? '') === 'price_asc' ? 'selected' : '' }}>Price Low-High</option>
                                        <option value="price_desc" {{ ($settings['category_page']['default_sort'] ?? '') === 'price_desc' ? 'selected' : '' }}>Price High-Low</option>
                                        <option value="newest" {{ ($settings['category_page']['default_sort'] ?? '') === 'newest' ? 'selected' : '' }}>Newest</option>
                                    </x-admin.select>
                                </x-admin.field>
                            </div>

                            <div class="mt-8 border-t border-v-border pt-6">
                                <h3 class="text-[13px] font-semibold text-v-text mb-4">Sidebar Filters</h3>
                                <div class="space-y-3 max-w-md">
                                    @php
                                        $filterToggles = [
                                            'price_enabled' => 'Price Range',
                                            'availability_enabled' => 'Availability',
                                            'brand_enabled' => 'Brand',
                                            'rating_enabled' => 'Rating',
                                            'on_sale_enabled' => 'On Sale',
                                            'tags_enabled' => 'Tags',
                                        ];
                                    @endphp
                                    @foreach ($filterToggles as $key => $label)
                                        <x-admin.field name="filters[{{ $key }}]">
                                            <label class="flex items-center gap-3 text-[13px] text-v-text cursor-pointer">
                                                <x-admin.toggle name="filters[{{ $key }}]" :checked="($settings['filters'][$key] ?? '0') === '1'" />
                                                {{ $label }}
                                            </label>
                                        </x-admin.field>
                                    @endforeach
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end">
                                <x-admin.button type="submit">Save Category</x-admin.button>
                            </div>
                        </form>
                    </x-admin.card>
                </x-admin.tabs.panel>

                {{-- ==================== BADGES ==================== --}}
                <x-admin.tabs.panel name="badges">
                    <x-admin.card>
                        <form method="POST" action="{{ route('admin.theme.settings.update') }}" data-v-form>
                            @csrf
                            <input type="hidden" name="group" value="badges">
                            <div class="space-y-4 max-w-md">
                                <x-admin.field name="settings[new_count]" label="NEW Badge: Last X products" help="Products added most recently. Set 0 to disable.">
                                    <x-admin.input type="number" name="settings[new_count]" :value="$settings['badges']['new_count'] ?? '10'" min="0" max="100" class="w-32" />
                                </x-admin.field>
                                <x-admin.field name="settings[hot_count]" label="HOT Badge: Top X best sellers" help="Products with the most orders. Set 0 to disable.">
                                    <x-admin.input type="number" name="settings[hot_count]" :value="$settings['badges']['hot_count'] ?? '5'" min="0" max="100" class="w-32" />
                                </x-admin.field>
                            </div>
                            <div class="mt-6 flex justify-end">
                                <x-admin.button type="submit">Save Badges</x-admin.button>
                            </div>
                        </form>
                    </x-admin.card>
                </x-admin.tabs.panel>

                {{-- ==================== FOOTER ==================== --}}
                <x-admin.tabs.panel name="footer">
                    <x-admin.card>
                        <form method="POST" action="{{ route('admin.theme.footer.bulk-update') }}" enctype="multipart/form-data" data-v-form>
                            @csrf
                            <div class="space-y-4">
                                <h3 class="text-[13px] font-semibold text-v-text">Footer Columns</h3>

                                <div x-init="initSortable($el, '{{ route('admin.theme.footer.reorder') }}')">
                                @foreach ($footerColumns as $colIdx => $col)
                                    <div class="bg-v-sunken rounded-v-lg overflow-hidden mb-2" data-id="{{ $col->id }}"
                                         draggable="true"
                                         x-data="{
                                        open: false,
                                        links: {{ json_encode($col->config['links'] ?? []) }},
                                        socials: {{ json_encode($col->type === 'social' ? ($col->config['links'] ?? []) : []) }},
                                        payIcons: {{ json_encode(collect($col->config['icons'] ?? [])->map(function ($icon) { return is_string($icon) ? ['type' => 'text', 'value' => $icon] : $icon; })->values()->toArray()) }},
                                    }">
                                        <input type="hidden" name="columns[{{ $col->id }}][id]" value="{{ $col->id }}">

                                        {{-- Header --}}
                                        <div class="flex items-center gap-3 p-4 cursor-pointer" @click="open = !open">
                                            <svg class="w-5 h-5 text-v-muted cursor-grab shrink-0 drag-handle" @click.stop fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/></svg>
                                            <svg class="w-4 h-4 text-v-muted transition-transform" :class="open && 'rotate-90'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                            <x-admin.badge tone="info">{{ $col->type }}</x-admin.badge>
                                            <input type="text" name="columns[{{ $col->id }}][title]" value="{{ $col->title }}" class="text-[13px] font-medium text-v-text flex-1 bg-transparent border-0 p-0 focus:ring-0" @click.stop>
                                            <label class="flex items-center gap-1" @click.stop>
                                                <input type="hidden" name="columns[{{ $col->id }}][is_enabled]" value="0">
                                                <input type="checkbox" name="columns[{{ $col->id }}][is_enabled]" value="1" {{ $col->is_enabled ? 'checked' : '' }} class="w-3.5 h-3.5 rounded-v-sm border-v-strong text-v-accent focus:ring-v-accent bg-v-surface">
                                            </label>
                                            <button type="button" @click.stop="$dispatch('open-modal', 'delete-footer-col-{{ $col->id }}')" class="text-v-muted hover:text-v-danger">
                                                <x-admin.icon name="trash" class="w-4 h-4" />
                                            </button>
                                        </div>

                                        {{-- Expanded edit --}}
                                        <div x-show="open" x-transition class="border-t border-v-border p-4 bg-v-surface" @click.stop>
                                            @if ($col->type === 'links')
                                                <div class="space-y-2 mb-3">
                                                    <template x-for="(link, idx) in links" :key="idx">
                                                        <div class="flex items-center gap-2">
                                                            <input type="text" :name="'columns[{{ $col->id }}][config][links]['+idx+'][label]'" x-model="link.label" placeholder="Label" class="v-control flex-1 h-[var(--control-h)] px-3 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                                            <input type="text" :name="'columns[{{ $col->id }}][config][links]['+idx+'][url]'" x-model="link.url" placeholder="/slug" class="v-control flex-1 h-[var(--control-h)] px-3 text-[13px] font-mono bg-v-surface text-v-text border border-v-border rounded-v-md">
                                                            <button type="button" @click="links.splice(idx, 1)" class="text-v-muted hover:text-v-danger p-1">
                                                                <x-admin.icon name="x" class="w-4 h-4" />
                                                            </button>
                                                        </div>
                                                    </template>
                                                </div>
                                                <button type="button" @click="links.push({label: '', url: ''})" class="text-xs text-v-accent hover:opacity-80 font-medium">+ Add Link</button>

                                            @elseif ($col->type === 'social')
                                                <div class="space-y-2 mb-3">
                                                    <template x-for="(social, idx) in socials" :key="idx">
                                                        <div class="flex items-center gap-2">
                                                            <select :name="'columns[{{ $col->id }}][config][links]['+idx+'][platform]'" x-model="social.platform" class="v-control w-32 h-[var(--control-h)] px-2.5 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                                                <option value="facebook">Facebook</option>
                                                                <option value="instagram">Instagram</option>
                                                                <option value="youtube">YouTube</option>
                                                                <option value="tiktok">TikTok</option>
                                                            </select>
                                                            <input type="text" :name="'columns[{{ $col->id }}][config][links]['+idx+'][url]'" x-model="social.url" placeholder="https://..." class="v-control flex-1 h-[var(--control-h)] px-3 text-[13px] font-mono bg-v-surface text-v-text border border-v-border rounded-v-md">
                                                            <button type="button" @click="socials.splice(idx, 1)" class="text-v-muted hover:text-v-danger p-1">
                                                                <x-admin.icon name="x" class="w-4 h-4" />
                                                            </button>
                                                        </div>
                                                    </template>
                                                </div>
                                                <button type="button" @click="socials.push({platform: 'facebook', url: ''})" class="text-xs text-v-accent hover:opacity-80 font-medium">+ Add Social</button>

                                            @elseif ($col->type === 'payment_icons')
                                                <div class="space-y-2 mb-3">
                                                    <template x-for="(icon, idx) in payIcons" :key="idx">
                                                        <div class="flex items-center gap-2 bg-v-sunken rounded-v-md p-2">
                                                            <select :name="'columns[{{ $col->id }}][config][icons]['+idx+'][type]'" x-model="icon.type" class="v-control w-20 h-[var(--control-h-sm)] px-2 text-xs bg-v-surface text-v-text border border-v-border rounded-v-md">
                                                                <option value="text">Text</option>
                                                                <option value="image">Image</option>
                                                            </select>
                                                            <template x-if="icon.type === 'text'">
                                                                <input type="text" :name="'columns[{{ $col->id }}][config][icons]['+idx+'][value]'" x-model="icon.value" placeholder="e.g. Visa" class="v-control flex-1 h-[var(--control-h)] px-3 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                                            </template>
                                                            <template x-if="icon.type === 'image'">
                                                                <div class="flex-1 flex items-center gap-2">
                                                                    <template x-if="icon.value">
                                                                        <img :src="icon.value.startsWith('http') || icon.value.startsWith('/') ? icon.value : '/storage/' + icon.value" class="h-6 rounded-v-sm" alt="">
                                                                    </template>
                                                                    <input type="hidden" :name="'columns[{{ $col->id }}][config][icons]['+idx+'][value]'" :value="icon.value">
                                                                    <input type="file" :name="'icon_uploads[{{ $col->id }}]['+idx+']'" accept="image/*" class="text-xs text-v-muted file:mr-2 file:py-1 file:px-2 file:rounded-v-sm file:border-0 file:text-xs file:bg-v-accent-weak file:text-v-accent">
                                                                </div>
                                                            </template>
                                                            <button type="button" @click="payIcons.splice(idx, 1)" class="text-v-muted hover:text-v-danger p-1">
                                                                <x-admin.icon name="x" class="w-4 h-4" />
                                                            </button>
                                                        </div>
                                                    </template>
                                                </div>
                                                <button type="button" @click="payIcons.push({type: 'text', value: ''})" class="text-xs text-v-accent hover:opacity-80 font-medium">+ Add Payment Method</button>

                                            @elseif ($col->type === 'text')
                                                <textarea name="columns[{{ $col->id }}][config][content]" rows="3" class="v-control w-full px-3 py-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md" placeholder="Free text content...">{{ $col->config['content'] ?? '' }}</textarea>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end">
                                <x-admin.button type="submit">Save Footer</x-admin.button>
                            </div>
                        </form>

                        @foreach ($footerColumns as $col)
                            <x-admin.confirm-dialog name="delete-footer-col-{{ $col->id }}" title="Delete footer column"
                                message="Delete this column?" confirm="Delete"
                                :action="route('admin.theme.footer.destroy', $col)" method="DELETE" />
                        @endforeach

                        <div class="border-t border-v-border pt-4 mt-6">
                            <h4 class="text-[13px] font-medium text-v-text mb-3">Add Footer Column</h4>
                            <form method="POST" action="{{ route('admin.theme.footer.store') }}" data-v-form class="flex items-end gap-3 max-w-lg">
                                @csrf
                                <div class="flex-1">
                                    <x-admin.field name="type" label="Type">
                                        <x-admin.select name="type">
                                            <option value="links">Links</option>
                                            <option value="payment_icons">Payment Icons</option>
                                            <option value="social">Social Links</option>
                                            <option value="text">Free Text</option>
                                        </x-admin.select>
                                    </x-admin.field>
                                </div>
                                <div class="flex-1">
                                    <x-admin.field name="title" label="Title">
                                        <x-admin.input name="title" required />
                                    </x-admin.field>
                                </div>
                                <x-admin.button type="submit">Add</x-admin.button>
                            </form>
                        </div>
                    </x-admin.card>
                </x-admin.tabs.panel>

                {{-- ==================== BLOG ==================== --}}
                <x-admin.tabs.panel name="blog">
                    <x-admin.card>
                        <form method="POST" action="{{ route('admin.theme.settings.update') }}" data-v-form>
                            @csrf
                            <input type="hidden" name="group" value="blog">
                            <div class="space-y-4 max-w-md">
                                <x-admin.field name="settings[posts_per_page]" label="Posts Per Page">
                                    <x-admin.input type="number" name="settings[posts_per_page]" :value="$settings['blog']['posts_per_page'] ?? '12'" min="4" max="48" class="w-32" />
                                </x-admin.field>
                                <x-admin.field name="settings[default_view]" label="Default View">
                                    <x-admin.select name="settings[default_view]">
                                        <option value="grid" {{ ($settings['blog']['default_view'] ?? 'grid') === 'grid' ? 'selected' : '' }}>Grid</option>
                                        <option value="list" {{ ($settings['blog']['default_view'] ?? '') === 'list' ? 'selected' : '' }}>List</option>
                                    </x-admin.select>
                                </x-admin.field>
                                <x-admin.field name="settings[excerpt_length]" label="Excerpt Length (characters)">
                                    <x-admin.input type="number" name="settings[excerpt_length]" :value="$settings['blog']['excerpt_length'] ?? '150'" min="50" max="500" class="w-32" />
                                </x-admin.field>
                                <x-admin.field name="settings[show_view_count]">
                                    <label class="flex items-center gap-3 text-[13px] text-v-text cursor-pointer">
                                        <x-admin.toggle name="settings[show_view_count]" :checked="($settings['blog']['show_view_count'] ?? '0') === '1'" />
                                        Show View Count
                                    </label>
                                </x-admin.field>
                                <x-admin.field name="settings[show_related_articles]">
                                    <label class="flex items-center gap-3 text-[13px] text-v-text cursor-pointer">
                                        <x-admin.toggle name="settings[show_related_articles]" :checked="($settings['blog']['show_related_articles'] ?? '0') === '1'" />
                                        Show Related Articles on Product Pages
                                    </label>
                                </x-admin.field>
                            </div>
                            <div class="mt-6 flex justify-end">
                                <x-admin.button type="submit">Save Blog Settings</x-admin.button>
                            </div>
                        </form>
                    </x-admin.card>
                </x-admin.tabs.panel>

                {{-- ==================== THEME SETTINGS ==================== --}}
                <x-admin.tabs.panel name="theme-settings">
                    <x-admin.card>
                        @if (count($themeSettingsSchema) > 0)
                            <form method="POST" action="{{ route('admin.theme.update-theme-settings') }}" data-v-form>
                                @csrf
                                <div class="space-y-4 max-w-md">
                                    <p class="text-[13px] text-v-secondary mb-4">Settings specific to the <strong>{{ $installedThemes[$activeTheme]['name'] ?? $activeTheme }}</strong> theme.</p>
                                    @foreach ($themeSettingsSchema as $key => $schema)
                                        <x-admin.field name="theme_settings[{{ $key }}]" :label="$schema['label'] ?? $key">
                                            @if (($schema['type'] ?? '') === 'select')
                                                <x-admin.select name="theme_settings[{{ $key }}]">
                                                    @foreach ($schema['options'] ?? [] as $option)
                                                        <option value="{{ $option }}" {{ ($themeSettingValues[$key] ?? ($schema['default'] ?? '')) == $option ? 'selected' : '' }}>{{ ucfirst($option) }}</option>
                                                    @endforeach
                                                </x-admin.select>
                                            @elseif (($schema['type'] ?? '') === 'toggle')
                                                <label class="flex items-center gap-3 text-[13px] text-v-text cursor-pointer">
                                                    <x-admin.toggle name="theme_settings[{{ $key }}]" :checked="($themeSettingValues[$key] ?? ($schema['default'] ?? false)) ? true : false" />
                                                    Enabled
                                                </label>
                                            @elseif (($schema['type'] ?? '') === 'color')
                                                <input type="color" name="theme_settings[{{ $key }}]" value="{{ $themeSettingValues[$key] ?? ($schema['default'] ?? '#000000') }}" class="h-10 w-14 rounded-v-md border border-v-border cursor-pointer bg-v-surface">
                                            @elseif (($schema['type'] ?? '') === 'number')
                                                <x-admin.input type="number" name="theme_settings[{{ $key }}]" :value="$themeSettingValues[$key] ?? ($schema['default'] ?? '')" class="w-32" />
                                            @else
                                                <x-admin.input name="theme_settings[{{ $key }}]" :value="$themeSettingValues[$key] ?? ($schema['default'] ?? '')" />
                                            @endif
                                        </x-admin.field>
                                    @endforeach
                                </div>
                                <div class="mt-6 flex justify-end">
                                    <x-admin.button type="submit">Save Theme Settings</x-admin.button>
                                </div>
                            </form>
                        @else
                            <p class="text-[13px] text-v-secondary italic">This theme has no configurable settings.</p>
                        @endif
                    </x-admin.card>
                </x-admin.tabs.panel>
            </x-admin.tabs>
        </div>

        @push('scripts')
        <script>
            function themeAdmin() {
                return {
                    tabs: [
                        { id: 'colors', label: 'Colors' },
                        { id: 'fonts', label: 'Fonts' },
                        { id: 'header', label: 'Header' },
                        { id: 'navigation', label: 'Navigation' },
                        { id: 'homepage', label: 'Homepage' },
                        { id: 'product_page', label: 'Product Page' },
                        { id: 'category_page', label: 'Category Page' },
                        { id: 'badges', label: 'Badges' },
                        { id: 'footer', label: 'Footer' },
                        { id: 'blog', label: 'Blog' },
                        { id: 'theme-settings', label: 'Theme Settings' },
                    ],
                };
            }

            function topbarLinks() {
                return {
                    links: @json(json_decode($settings['header']['topbar_links'] ?? '[]', true) ?: []),
                };
            }
            function initSortable(container, reorderUrl) {
                let dragEl = null;

                container.addEventListener('dragstart', function(e) {
                    const item = e.target.closest('[data-id]');
                    if (!item) return;
                    dragEl = item;
                    item.style.opacity = '0.4';
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', item.dataset.id);
                });

                container.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    if (!dragEl) return;
                    const target = e.target.closest('[data-id]');
                    if (!target || target === dragEl) return;
                    const rect = target.getBoundingClientRect();
                    const midY = rect.top + rect.height / 2;
                    if (e.clientY < midY) {
                        target.before(dragEl);
                    } else {
                        target.after(dragEl);
                    }
                });

                container.addEventListener('drop', function(e) {
                    e.preventDefault();
                    if (!dragEl) return;
                    const items = [...container.querySelectorAll(':scope > [data-id]')];
                    const order = items.map(function(el, idx) {
                        return { id: parseInt(el.dataset.id), sort_order: idx };
                    });

                    fetch(reorderUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ order: order })
                    });
                });

                container.addEventListener('dragend', function() {
                    if (dragEl) dragEl.style.opacity = '1';
                    dragEl = null;
                });
            }
        </script>
        @endpush
</x-admin.settings-shell>
